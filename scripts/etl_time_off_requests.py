#!/usr/bin/env python3
import os, sys, requests
from dotenv import load_dotenv
import pyodbc
from datetime import datetime
from urllib.parse import urlsplit, urlunsplit, parse_qsl

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

def normalize_api_url_and_params(raw_url: str):
    """
    Quita los query params de la URL base y los devuelve por separado como dict.
    Ej: '.../time-off/requests?page=1&policyTypeIds=9637'
    -> base_url='.../time-off/requests' , base_params={'page':'1','policyTypeIds':'9637'}
    """
    parts = urlsplit(raw_url)
    base_url = urlunsplit((parts.scheme, parts.netloc, parts.path, '', ''))
    base_params = dict(parse_qsl(parts.query, keep_blank_values=True))
    return base_url, base_params

# --------------------------
#  PAGINACIÓN AUTOMÁTICA
# --------------------------
def fetch_requests():
    """
    Descarga TODAS las páginas respetando el limit por defecto del API (10).
    - Conserva cualquier query param ya presente en API_URL (policyTypeIds, estados, fechas, etc.)
    - Evita duplicar claves como 'page'.
    """
    base_url, base_params = normalize_api_url_and_params(API_URL)

    all_items = []
    page = 1
    while True:
        params = dict(base_params)  # copiamos los que vengan en la URL
        params['page'] = page       # imponemos la página actual

        r = requests.get(
            base_url,
            headers={'accept': 'application/json', 'Authorization': API_AUTH},
            params=params,
            timeout=30
        )
        r.raise_for_status()
        data = r.json() if r.content else {}
        items = (data or {}).get('items', []) or []

        if not items:
            break

        all_items.extend(items)
        page += 1  # vamos a la siguiente; si ya no hay, la siguiente vendrá vacía y salimos

    return all_items

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

    conn_str = ';'.join(parts) + ';'
    return pyodbc.connect(conn_str, timeout=15)

# MERGE con issuer_full_name
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

def to_dt(value):
    if not value:
        return None
    try:
        return datetime.fromisoformat(value.replace('Z','+00:00'))
    except Exception:
        return value

def build_full_name(issuer: dict) -> str | None:
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

def upsert_requests(items):
    conn = connect_sqlserver()
    cur = conn.cursor()
    try:
        for it in items:
            issuer = it.get('issuer') or {}
            policy = it.get('policyType') or {}
            row = (
                it.get('id'),
                issuer.get('employeeInternalId'),
                build_full_name(issuer),
                policy.get('name'),
                (it.get('from') or {}).get('date'),
                (it.get('to') or {}).get('date'),
                it.get('amountRequested'),
                it.get('state'),
                it.get('stepState'),
                to_dt(it.get('createdAt')),
                to_dt(it.get('resolutionDate')),
                it.get('description'),
            )
            cur.execute(MERGE_SQL, row)
        conn.commit()
        return len(items)
    except Exception:
        conn.rollback()
        raise
    finally:
        cur.close()
        conn.close()

def main():
    if not all([API_URL, API_AUTH, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS]):
        print("Faltan variables de entorno requeridas. Revisa tu .env", file=sys.stderr)
        sys.exit(1)
    items = fetch_requests()  # paginación automática sin tocar el limit por defecto
    n = upsert_requests(items)
    print(f"ETL OK - filas procesadas: {n}")

if __name__ == "__main__":
    main()
