#!/usr/bin/env python3
import os, sys, json, unicodedata
from datetime import datetime
from urllib.parse import urlsplit, urlunsplit, parse_qsl
from typing import Any, Dict, Iterable, List, Tuple, Optional

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
import pyodbc
from dotenv import load_dotenv

# ------------------------------------
# Carga .env
# ------------------------------------
ROOT_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
load_dotenv(os.path.join(ROOT_DIR, '.env'))

API_URL   = os.getenv('HUMAND_API_URL')          # puede traer query params
API_AUTH  = os.getenv('HUMAND_API_AUTH')

DB_HOST   = os.getenv('DB_HOST')
DB_PORT   = os.getenv('DB_PORT', '1433')
DB_NAME   = os.getenv('DB_DATABASE')
DB_USER   = os.getenv('DB_USERNAME')
DB_PASS   = os.getenv('DB_PASSWORD')

DB_DRIVER = os.getenv('DB_ODBC_DRIVER')
DB_ENCRYPT = os.getenv('DB_ENCRYPT')   # 'true'/'false'/'yes'/'no'
DB_TSC     = os.getenv('DB_TRUST_SERVER_CERTIFICATE', 'true')

# Si tu tabla tiene VARCHAR y quieres truncar preventivamente (en bytes o chars):
TRUNCATE_FOR_VARCHAR = os.getenv('TRUNCATE_FOR_VARCHAR', 'false').lower() in ('1','true','yes','y')
# Define límites si tu tabla no es NVARCHAR(MAX)
LIMITS = {
    "issuer_full_name": 200,    # ajusta a tu schema si es VARCHAR(200)
    "policy_name": 200,
    "description": 2000,        # ej. VARCHAR(2000). Si tienes NVARCHAR(MAX), puedes ignorar esto
}

# ------------------------------------
# Utilidades
# ------------------------------------
def bool_env(v, default=False):
    if v is None:
        return default
    return str(v).strip().lower() in ('1','true','yes','y','on')

def pick_sql_driver():
    if DB_DRIVER and DB_DRIVER.strip():
        return DB_DRIVER.strip()
    drivers = [d.strip() for d in pyodbc.drivers()]
    for name in ("ODBC Driver 18 for SQL Server",
                 "ODBC Driver 17 for SQL Server",
                 "SQL Server"):
        if name in drivers:
            return name
    raise RuntimeError(f"No se encontró un driver ODBC de SQL Server válido. Instalados: {drivers}")

def normalize_api_url_and_params(raw_url: str) -> Tuple[str, Dict[str,str]]:
    """
    Quita los query params de la URL base y los devuelve por separado como dict.
    """
    parts = urlsplit(raw_url)
    base_url = urlunsplit((parts.scheme, parts.netloc, parts.path, '', ''))
    base_params = dict(parse_qsl(parts.query, keep_blank_values=True))
    return base_url, base_params

def build_http_session() -> requests.Session:
    """
    Sesión con reintentos idempotentes y timeouts sensatos.
    """
    s = requests.Session()
    retries = Retry(
        total=5,
        connect=5,
        read=3,
        backoff_factor=0.8,
        status_forcelist=(429, 500, 502, 503, 504),
        allowed_methods=frozenset(["GET"])
    )
    adapter = HTTPAdapter(max_retries=retries, pool_connections=10, pool_maxsize=10)
    s.mount("http://", adapter)
    s.mount("https://", adapter)
    # Fuerza utf-8 si el servidor omite charset
    s.headers.update({
        'Accept': 'application/json',
        'Accept-Charset': 'utf-8',
        'Authorization': API_AUTH or ''
    })
    return s

def safe_json(resp: requests.Response) -> Dict[str, Any]:
    """
    Parseo JSON tolerante a respuestas con encoding dudoso.
    Intenta utf-8; si falla, cae a 'latin-1' y normaliza.
    """
    if not resp.content:
        return {}
    try:
        # Confía en el encoding declarado o en utf-8 por defecto
        resp.encoding = resp.encoding or 'utf-8'
        return resp.json()
    except Exception:
        # fallback manual
        try:
            txt = resp.content.decode('utf-8', errors='strict')
        except UnicodeDecodeError:
            txt = resp.content.decode('latin-1', errors='replace')
        try:
            return json.loads(txt)
        except json.JSONDecodeError as e:
            raise RuntimeError(f"Respuesta del API no es JSON válido (len={len(txt)}). Detalle: {e}") from e

def to_dt(value: Optional[str]):
    if not value:
        return None
    try:
        return datetime.fromisoformat(value.replace('Z','+00:00'))
    except Exception:
        return value

def build_full_name(issuer: dict) -> Optional[str]:
    if not issuer:
        return None
    first = (issuer.get('firstName') or '').strip()
    last  = (issuer.get('lastName') or '').strip()
    if first and last:
        return f"{first} {last}"
    if first or last:
        return first or last
    email = (issuer.get('email') or '').strip()
    return email.split('@', 1)[0] if email else None

def clean_text(s: Optional[str], max_len: Optional[int] = None) -> Optional[str]:
    """
    - Normaliza a NFC (evita combinaciones extrañas).
    - Elimina surrogates no emparejados y control chars problemáticos.
    - Trunca en número de caracteres si se indica (para VARCHAR).
    """
    if s is None:
        return None
    if not isinstance(s, str):
        s = str(s)
    # Normaliza acentos/emoji
    s = unicodedata.normalize('NFC', s)
    # Quita controles ASCII problemáticos excepto \n \t
    s = ''.join(ch for ch in s if ch == '\n' or ch == '\t' or (ord(ch) >= 32))
    # Elimina surrogates no válidos (evita errores de codificación raros)
    s = s.encode('utf-8', 'surrogatepass').decode('utf-8', 'ignore')
    if TRUNCATE_FOR_VARCHAR and max_len is not None and max_len > 0:
        if len(s) > max_len:
            s = s[:max_len]
    return s

# ------------------------------------
# API: paginación
# ------------------------------------
def fetch_requests() -> List[Dict[str, Any]]:
    """
    Descarga TODAS las páginas respetando el limit por defecto del API:
    - Conserva los query params de HUMAND_API_URL (policyTypeIds, estados, fechas, etc.)
    - Evita duplicar 'page'
    - JSON tolerante a encodings
    """
    if not API_URL:
        raise RuntimeError("HUMAND_API_URL no está configurada")

    base_url, base_params = normalize_api_url_and_params(API_URL)
    session = build_http_session()

    all_items: List[Dict[str, Any]] = []
    page = 1
    while True:
        params = dict(base_params)
        params['page'] = page

        r = session.get(base_url, params=params, timeout=30)
        # Si el backend falla con "Malformed UTF-8 ..." regresará 4xx/5xx: captura el cuerpo legible
        try:
            r.raise_for_status()
        except requests.HTTPError as e:
            body_preview = r.text[:500] if r.text else str(r.content[:500])
            raise RuntimeError(
                f"Error HTTP {r.status_code} en {r.url}. "
                f"Cuerpo (preview): {body_preview}"
            ) from e

        data = safe_json(r)
        items = (data or {}).get('items', []) or []

        if not items:
            break

        all_items.extend(items)
        page += 1

    return all_items

# ------------------------------------
# SQL Server
# ------------------------------------
def connect_sqlserver():
    driver = pick_sql_driver()
    encrypt = bool_env(DB_ENCRYPT, None)  # None => no incluir
    tsc = bool_env(DB_TSC, True)

    parts = [
        f"DRIVER={{{driver}}}",
        f"SERVER={DB_HOST},{DB_PORT}",
        f"DATABASE={DB_NAME}",
        f"UID={DB_USER}",
        f"PWD={DB_PASS}",
        f"TrustServerCertificate={'yes' if tsc else 'no'}",
    ]
    if encrypt is not None:
        parts.append(f"Encrypt={'yes' if encrypt else 'no'}")

    # Si usas SQL 2019+ con *_UTF8 y columnas VARCHAR UTF-8:
    # parts.append("ClientCharset=UTF-8")  # solo aplica para algunos drivers; en ODBC 18 no es necesario

    conn_str = ';'.join(parts) + ';'
    # autocommit False para manejar transacciones y habilitar fast_executemany
    return pyodbc.connect(conn_str, timeout=15, autocommit=False)

# MERGE parametrizado
MERGE_SQL = """
MERGE dbo.time_off_requests AS T
USING (SELECT
    ? AS request_id,
    ? AS issuer_employee_internal_id,
    ? AS issuer_full_name,
    ? AS policy_name,
    ? AS from_date,
    ? AS to_date,
    ? AS amount_requested,
    ? AS state,
    ? AS step_state,
    ? AS created_at,
    ? AS resolution_date,
    ? AS description
) AS S
ON (T.request_id = S.request_id)
WHEN MATCHED THEN UPDATE SET
    issuer_employee_internal_id = S.issuer_employee_internal_id,
    issuer_full_name            = S.issuer_full_name,
    policy_name                 = S.policy_name,
    from_date                   = S.from_date,
    to_date                     = S.to_date,
    amount_requested            = S.amount_requested,
    state                       = S.state,
    step_state                  = S.step_state,
    created_at                  = S.created_at,
    resolution_date             = S.resolution_date,
    description                 = S.description,
    etl_synced_at               = SYSUTCDATETIME()
WHEN NOT MATCHED BY TARGET THEN
    INSERT (request_id, issuer_employee_internal_id, issuer_full_name, policy_name, from_date, to_date, amount_requested, state, step_state, created_at, resolution_date, description)
    VALUES (S.request_id, S.issuer_employee_internal_id, S.issuer_full_name, S.policy_name, S.from_date, S.to_date, S.amount_requested, S.state, S.step_state, S.created_at, S.resolution_date, S.description);
"""

def build_rows(items: Iterable[Dict[str, Any]]) -> List[Tuple[Any,...]]:
    rows: List[Tuple[Any,...]] = []
    for it in items:
        issuer = it.get('issuer') or {}
        policy = it.get('policyType') or {}

        full_name  = clean_text(build_full_name(issuer), LIMITS.get("issuer_full_name"))
        policy_name = clean_text(policy.get('name'), LIMITS.get("policy_name"))
        description = clean_text(it.get('description'), LIMITS.get("description"))

        row = (
            it.get('id'),
            issuer.get('employeeInternalId'),
            full_name,
            policy_name,
            (it.get('from') or {}).get('date'),
            (it.get('to') or {}).get('date'),
            it.get('amountRequested'),
            it.get('state'),
            it.get('stepState'),
            to_dt(it.get('createdAt')),
            to_dt(it.get('resolutionDate')),
            description,
        )
        rows.append(row)
    return rows

def upsert_requests(items: List[Dict[str, Any]], batch_size: int = 500) -> int:
    """
    Inserta/actualiza en lotes con fast_executemany.
    """
    if not items:
        return 0

    rows = build_rows(items)

    conn = connect_sqlserver()
    try:
        cur = conn.cursor()
        # Acelera enormemente executemany en ODBC
        cur.fast_executemany = True

        # Ejecuta en lotes para no inflar paquetes TDS
        total = 0
        for i in range(0, len(rows), batch_size):
            chunk = rows[i:i+batch_size]
            cur.executemany(MERGE_SQL, chunk)
            total += len(chunk)

        conn.commit()
        return total
    except Exception:
        conn.rollback()
        raise
    finally:
        try:
            cur.close()
        except Exception:
            pass
        conn.close()

# ------------------------------------
# main
# ------------------------------------
def main():
    # Validaciones mínimas
    missing = [k for k,v in {
        'HUMAND_API_URL': API_URL,
        'HUMAND_API_AUTH': API_AUTH,
        'DB_HOST': DB_HOST,
        'DB_PORT': DB_PORT,
        'DB_DATABASE': DB_NAME,
        'DB_USERNAME': DB_USER,
        'DB_PASSWORD': DB_PASS,
    }.items() if not v]
    if missing:
        print("Faltan variables de entorno requeridas: " + ", ".join(missing), file=sys.stderr)
        sys.exit(1)

    try:
        items = fetch_requests()
    except Exception as e:
        print(f"Fallo leyendo API: {e}", file=sys.stderr)
        sys.exit(2)

    try:
        n = upsert_requests(items)
    except Exception as e:
        print(f"Fallo guardando en SQL Server: {e}", file=sys.stderr)
        sys.exit(3)

    print(f"ETL OK - filas procesadas: {n}")

if __name__ == "__main__":
    main()
