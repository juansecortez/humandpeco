#!/usr/bin/env python3
"""
ETL Humand → SQL Server (uso manual / cron desde terminal).
La sincronización desde la web usa HumandTimeOffEtlService.php (PHP).
"""
import os, sys, json, calendar, unicodedata, argparse
from datetime import datetime, date
from urllib.parse import urlsplit, urlunsplit, parse_qsl
from typing import Any, Dict, Iterable, List, Tuple, Optional

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
import pyodbc
from dotenv import load_dotenv

ROOT_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
load_dotenv(os.path.join(ROOT_DIR, '.env'))


def strip_quotes(s: Optional[str]) -> Optional[str]:
    if s is None:
        return None
    s = s.strip()
    if (s.startswith('"') and s.endswith('"')) or (s.startswith("'") and s.endswith("'")):
        return s[1:-1]
    return s


def getenv_clean(key: str, default: Optional[str] = None) -> Optional[str]:
    v = os.getenv(key, default)
    return strip_quotes(v) if v is not None else None


API_URL   = getenv_clean('HUMAND_API_URL')
API_AUTH  = getenv_clean('HUMAND_API_AUTH')

DB_HOST   = os.getenv('DB_HOST')
DB_PORT   = os.getenv('DB_PORT', '1433')
DB_NAME   = os.getenv('DB_DATABASE')
DB_USER   = os.getenv('DB_USERNAME')
DB_PASS   = os.getenv('DB_PASSWORD')

DB_DRIVER = os.getenv('DB_ODBC_DRIVER')
DB_ENCRYPT = os.getenv('DB_ENCRYPT')
DB_TSC     = os.getenv('DB_TRUST_SERVER_CERTIFICATE', 'true')

TRUNCATE_FOR_VARCHAR = os.getenv('TRUNCATE_FOR_VARCHAR', 'false').lower() in ('1', 'true', 'yes', 'y')
LIMITS = {
    "issuer_full_name": 200,
    "policy_name": 200,
    "description": 2000,
}


def bool_env(v, default=False):
    if v is None:
        return default
    return str(v).strip().lower() in ('1', 'true', 'yes', 'y', 'on')


def pick_sql_driver():
    if DB_DRIVER and DB_DRIVER.strip():
        return DB_DRIVER.strip()
    drivers = [d.strip() for d in pyodbc.drivers()]
    for name in ("ODBC Driver 18 for SQL Server", "ODBC Driver 17 for SQL Server", "SQL Server"):
        if name in drivers:
            return name
    raise RuntimeError(f"No se encontró driver ODBC SQL Server. Instalados: {drivers}")


def normalize_api_url_and_params(raw_url: str) -> Tuple[str, Dict[str, str]]:
    parts = urlsplit(raw_url)
    base_url = urlunsplit((parts.scheme, parts.netloc, parts.path, '', ''))
    base_params = dict(parse_qsl(parts.query, keep_blank_values=True))
    return base_url, base_params


def build_http_session() -> requests.Session:
    s = requests.Session()
    s.trust_env = False
    retries = Retry(
        total=5,
        connect=5,
        read=3,
        backoff_factor=0.8,
        status_forcelist=(429, 500, 502, 503, 504),
        allowed_methods=frozenset(["GET"]),
    )
    adapter = HTTPAdapter(max_retries=retries, pool_connections=10, pool_maxsize=10)
    s.mount("http://", adapter)
    s.mount("https://", adapter)
    s.headers.update({
        'Accept': 'application/json',
        'Authorization': API_AUTH or '',
    })
    return s


def safe_json(resp: requests.Response) -> Dict[str, Any]:
    if not resp.content:
        return {}
    try:
        resp.encoding = resp.encoding or 'utf-8'
        return resp.json()
    except Exception:
        try:
            txt = resp.content.decode('utf-8', errors='strict')
        except UnicodeDecodeError:
            txt = resp.content.decode('latin-1', errors='replace')
        return json.loads(txt)


def to_dt(value: Optional[str]):
    if not value:
        return None
    try:
        return datetime.fromisoformat(value.replace('Z', '+00:00'))
    except Exception:
        return value


def build_full_name(issuer: dict) -> Optional[str]:
    if not issuer:
        return None
    first = (issuer.get('firstName') or '').strip()
    last = (issuer.get('lastName') or '').strip()
    if first and last:
        return f"{first} {last}"
    if first or last:
        return first or last
    email = (issuer.get('email') or '').strip()
    return email.split('@', 1)[0] if email else None


def clean_text(s: Optional[str], max_len: Optional[int] = None) -> Optional[str]:
    if s is None:
        return None
    if not isinstance(s, str):
        s = str(s)
    s = unicodedata.normalize('NFC', s)
    s = ''.join(ch for ch in s if ch == '\n' or ch == '\t' or (ord(ch) >= 32))
    s = s.encode('utf-8', 'surrogatepass').decode('utf-8', 'ignore')
    if TRUNCATE_FOR_VARCHAR and max_len is not None and max_len > 0 and len(s) > max_len:
        s = s[:max_len]
    return s


def created_at_since_default() -> str:
    if since := getenv_clean('HUMAND_ETL_CREATED_AT_SINCE'):
        return since
    months = int(getenv_clean('HUMAND_ETL_LOOKBACK_MONTHS') or '2')
    today = date.today()
    y, m = today.year, today.month - months
    while m <= 0:
        m += 12
        y -= 1
    d = min(today.day, calendar.monthrange(y, m)[1])
    return date(y, m, d).isoformat()


def default_env_filters() -> Dict[str, str]:
    out: Dict[str, str] = {'createdAtSince': created_at_since_default()}
    if states := getenv_clean('HUMAND_ETL_STATES'):
        out['states'] = states
    return out


def fetch_requests(extra_params: Optional[Dict[str, str]] = None) -> List[Dict[str, Any]]:
    if not API_URL:
        raise RuntimeError("HUMAND_API_URL no está configurada")

    base_url, base_params = normalize_api_url_and_params(API_URL)
    session = build_http_session()
    all_items: List[Dict[str, Any]] = []
    page = 1

    while True:
        params = dict(base_params)
        params.update(default_env_filters())
        if extra_params:
            params.update(extra_params)
        params['page'] = str(page)

        r = session.get(base_url, params=params, timeout=60)
        try:
            r.raise_for_status()
        except requests.HTTPError as e:
            body_preview = r.text[:500] if r.text else str(r.content[:500])
            raise RuntimeError(
                f"Error HTTP {r.status_code} en {r.url}. Cuerpo (preview): {body_preview}"
            ) from e

        data = safe_json(r)
        items = (data or {}).get('items', []) or []
        if not items:
            break

        all_items.extend(items)
        page += 1

    return all_items


def connect_sqlserver():
    driver = pick_sql_driver()
    encrypt = bool_env(DB_ENCRYPT, None)
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
    return pyodbc.connect(';'.join(parts) + ';', timeout=15, autocommit=False)


MERGE_SQL = """
MERGE dbo.time_off_requests AS T
USING (SELECT
    ? AS request_id,
    ? AS issuer_employee_internal_id,
    ? AS issuer_full_name,
    ? AS policy_type_id,
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
    policy_type_id              = S.policy_type_id,
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
    INSERT (request_id, issuer_employee_internal_id, issuer_full_name, policy_type_id, policy_name, from_date, to_date, amount_requested, state, step_state, created_at, resolution_date, description)
    VALUES (S.request_id, S.issuer_employee_internal_id, S.issuer_full_name, S.policy_type_id, S.policy_name, S.from_date, S.to_date, S.amount_requested, S.state, S.step_state, S.created_at, S.resolution_date, S.description);
"""


def build_rows(items: Iterable[Dict[str, Any]]) -> List[Tuple[Any, ...]]:
    rows: List[Tuple[Any, ...]] = []
    for it in items:
        issuer = it.get('issuer') or {}
        policy = it.get('policyType') or {}
        rows.append((
            it.get('id'),
            issuer.get('employeeInternalId'),
            clean_text(build_full_name(issuer), LIMITS.get("issuer_full_name")),
            policy.get('id'),
            clean_text(policy.get('name'), LIMITS.get("policy_name")),
            (it.get('from') or {}).get('date'),
            (it.get('to') or {}).get('date'),
            it.get('amountRequested'),
            it.get('state'),
            it.get('stepState'),
            to_dt(it.get('createdAt')),
            to_dt(it.get('resolutionDate')),
            clean_text(it.get('description'), LIMITS.get("description")),
        ))
    return rows


def upsert_requests(items: List[Dict[str, Any]], batch_size: int = 500) -> int:
    if not items:
        return 0
    rows = build_rows(items)
    conn = connect_sqlserver()
    try:
        cur = conn.cursor()
        cur.fast_executemany = True
        total = 0
        for i in range(0, len(rows), batch_size):
            chunk = rows[i:i + batch_size]
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


def parse_cli_args(argv: Optional[List[str]] = None) -> argparse.Namespace:
    p = argparse.ArgumentParser(description='ETL solicitudes time-off desde Humand (CLI)')
    p.add_argument('--policy-type-ids', help='IDs policyType separados por coma')
    p.add_argument('--states', help='Estados separados por coma')
    p.add_argument('--from-date', dest='from_date', help='YYYY-MM-DD')
    p.add_argument('--to-date', dest='to_date', help='YYYY-MM-DD')
    p.add_argument('--created-at-since', dest='created_at_since', help='YYYY-MM-DD')
    p.add_argument('--limit', type=int, help='Registros por página')
    return p.parse_args(argv)


def cli_to_api_params(args: argparse.Namespace) -> Dict[str, str]:
    mapping = {
        'policy_type_ids': 'policyTypeIds',
        'states': 'states',
        'from_date': 'fromDate',
        'to_date': 'toDate',
        'created_at_since': 'createdAtSince',
    }
    out: Dict[str, str] = {}
    for cli_key, api_key in mapping.items():
        val = getattr(args, cli_key, None)
        if val is not None and str(val).strip():
            out[api_key] = str(val).strip()
    if args.limit is not None:
        out['limit'] = str(args.limit)
    return out


def main():
    args = parse_cli_args()
    missing = [k for k, v in {
        'HUMAND_API_URL': API_URL,
        'HUMAND_API_AUTH': API_AUTH,
        'DB_HOST': DB_HOST,
        'DB_DATABASE': DB_NAME,
        'DB_USERNAME': DB_USER,
        'DB_PASSWORD': DB_PASS,
    }.items() if not v]
    if missing:
        print("Faltan variables: " + ", ".join(missing), file=sys.stderr)
        sys.exit(1)

    extra = cli_to_api_params(args)
    filters = {**default_env_filters(), **extra}
    if filters:
        print(f"ETL filtros API: {filters}", flush=True)

    try:
        items = fetch_requests(extra or None)
    except Exception as e:
        print(f"Fallo leyendo API: {e}", file=sys.stderr)
        sys.exit(2)

    try:
        n = upsert_requests(items)
    except Exception as e:
        print(f"Fallo guardando en SQL Server: {e}", file=sys.stderr)
        sys.exit(3)

    label = extra.get('policyTypeIds', 'todas')
    print(f"ETL OK - policyTypeIds={label} - filas procesadas: {n}")


if __name__ == "__main__":
    main()
