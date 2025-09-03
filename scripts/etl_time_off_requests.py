#!/usr/bin/env python3
import os, sys, requests
from dotenv import load_dotenv
import pyodbc
from datetime import datetime

ROOT_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
load_dotenv(os.path.join(ROOT_DIR, '.env'))

API_URL   = os.getenv('HUMAND_API_URL')
API_AUTH  = os.getenv('HUMAND_API_AUTH')

DB_HOST   = os.getenv('DB_HOST')
DB_PORT   = os.getenv('DB_PORT', '1433')
DB_NAME   = os.getenv('DB_DATABASE')
DB_USER   = os.getenv('DB_USERNAME')
DB_PASS   = os.getenv('DB_PASSWORD')

# Permite forzar por .env; si no, se autodetecta
DB_DRIVER = os.getenv('DB_ODBC_DRIVER')

# TLS (opcional, para ambientes prod)
DB_ENCRYPT = os.getenv('DB_ENCRYPT')   # 'true'/'false'/'yes'/'no' o vacío
DB_TSC     = os.getenv('DB_TRUST_SERVER_CERTIFICATE', 'true')  # por defecto true en LAN

def bool_env(v, default=False):
    if v is None:
        return default
    return str(v).strip().lower() in ('1','true','yes','y','on')

def pick_sql_driver():
    """Elige el mejor driver disponible si no está definido en .env."""
    if DB_DRIVER and DB_DRIVER.strip():
        return DB_DRIVER.strip()
    drivers = [d.strip() for d in pyodbc.drivers()]
    # Preferencias
    for name in ("ODBC Driver 18 for SQL Server",
                 "ODBC Driver 17 for SQL Server",
                 "SQL Server"):
        if name in drivers:
            return name
    raise RuntimeError(f"No se encontró un driver ODBC de SQL Server válido. Instalados: {drivers}")

def fetch_requests():
    r = requests.get(API_URL, headers={'accept':'application/json','Authorization':API_AUTH}, timeout=30)
    r.raise_for_status()
    return r.json().get('items', [])

def connect_sqlserver():
    driver = pick_sql_driver()

    # ODBC 18 tiene Encrypt=Yes por defecto. Controlamos explícitamente si quieres:
    encrypt = bool_env(DB_ENCRYPT, None)  # None => no incluir atributo
    tsc = bool_env(DB_TSC, True)

    parts = [
        f"DRIVER={{{{ {driver} }}}}".replace("{{ ", "{").replace(" }}", "}"),  # produce {DriverName}
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

MERGE_SQL = """
MERGE dbo.time_off_requests AS T
USING (SELECT
    ? AS request_id,
    ? AS issuer_employee_internal_id,
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
    INSERT (request_id, issuer_employee_internal_id, policy_name, from_date, to_date, amount_requested, state, step_state, created_at, resolution_date, description)
    VALUES (S.request_id, S.issuer_employee_internal_id, S.policy_name, S.from_date, S.to_date, S.amount_requested, S.state, S.step_state, S.created_at, S.resolution_date, S.description);
"""

def to_dt(value):
    if not value:
        return None
    try:
        return datetime.fromisoformat(value.replace('Z','+00:00'))
    except Exception:
        return value

def upsert_requests(items):
    conn = connect_sqlserver()
    cur = conn.cursor()
    try:
        for it in items:
            row = (
                it.get('id'),
                (it.get('issuer') or {}).get('employeeInternalId'),
                (it.get('policyType') or {}).get('name'),
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
    items = fetch_requests()
    n = upsert_requests(items)
    print(f"ETL OK - filas procesadas: {n}")

if __name__ == "__main__":
    main()
