#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os, sys, json, requests
from datetime import datetime
from requests.auth import HTTPBasicAuth
from dotenv import load_dotenv
import pyodbc

# === carga .env ===
ROOT_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
load_dotenv(os.path.join(ROOT_DIR, '.env'))

# --- DB (.env) ---
DB_HOST   = os.getenv('DB_HOST')
DB_PORT   = os.getenv('DB_PORT', '1433')
DB_NAME   = os.getenv('DB_DATABASE')
DB_USER   = os.getenv('DB_USERNAME')
DB_PASS   = os.getenv('DB_PASSWORD')
DB_DRIVER = os.getenv('DB_ODBC_DRIVER')  # opcional
DB_ENCRYPT= os.getenv('DB_ENCRYPT')      # 'true'/'false'
DB_TSC    = os.getenv('DB_TRUST_SERVER_CERTIFICATE', 'true')

# --- SAP (.env) ---
SAP_BASE_URL  = os.getenv('SAP_BASE_URL')   # ej: https://devci01:1443/sap/bc/zrh_vacacion/zrh
SAP_CLIENT    = os.getenv('SAP_CLIENT', '110')
SAP_USER      = os.getenv('SAP_USER')       # opcional
SAP_PASS      = os.getenv('SAP_PASS')       # opcional
SAP_VERIFY_SSL= os.getenv('SAP_VERIFY_SSL', 'false')  # si usas cert interno, deja false
SAP_TIMEOUT   = int(os.getenv('SAP_TIMEOUT', '20'))

# --- comportamiento ---
# Estados a procesar. Por defecto: Approved y (si cambia) Cancelled.
PROCESS_STATES = [s.strip().upper() for s in os.getenv('PROCESS_STATES', 'APPROVED,CANCELLED').split(',') if s.strip()]

# Mapeo policy->clave
POLICY_CLAVE_MAP = {
    'VACACIONES': '6072',
    'LEGO':       '6073',
}

def bool_env(v, default=False):
    if v is None:
        return default
    return str(v).strip().lower() in ('1','true','yes','y','on')

def pick_sql_driver():
    """Elige driver ODBC si no está forzado por .env."""
    if DB_DRIVER and DB_DRIVER.strip():
        return DB_DRIVER.strip()
    drivers = [d.strip() for d in pyodbc.drivers()]
    for name in ("ODBC Driver 18 for SQL Server", "ODBC Driver 17 for SQL Server", "SQL Server"):
        if name in drivers:
            return name
    raise RuntimeError(f"No hay driver ODBC de SQL Server válido. Instalados: {drivers}")

def connect_sqlserver():
    driver = pick_sql_driver()
    encrypt = None if DB_ENCRYPT is None else bool_env(DB_ENCRYPT, None)
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

# ---- SELECT: filas a procesar (no repetidas por estado) ----
SQL_TO_PROCESS = f"""
WITH base AS (
  SELECT
    r.request_id,
    r.issuer_employee_internal_id,
    r.policy_name,
    r.from_date,
    r.to_date,
    r.amount_requested,
    r.state
  FROM dbo.time_off_requests r
  WHERE UPPER(r.state) IN ({', '.join(["?" for _ in PROCESS_STATES])})
),
norm AS (
  SELECT
    b.*,
    CASE
      WHEN CHARINDEX('@', b.issuer_employee_internal_id) > 0
        THEN LEFT(b.issuer_employee_internal_id, CHARINDEX('@', b.issuer_employee_internal_id) - 1)
      ELSE b.issuer_employee_internal_id
    END AS usuario_id
  FROM base b
),
joined AS (
  SELECT
    n.*,
    o.CodigoCol
  FROM norm n
  LEFT JOIN Organigrama.dbo.Organigrama o
    ON o.UsuarioId = n.usuario_id
)
SELECT j.*
FROM joined j
LEFT JOIN dbo.sap_time_off_exports e
  ON e.request_id = j.request_id
 AND UPPER(e.processed_state) = UPPER(j.state)
WHERE e.request_id IS NULL  -- aún no se ha enviado ese request/estado
"""

# ---- INSERT: log de exportación ----
SQL_INSERT_EXPORT = """
INSERT INTO dbo.sap_time_off_exports
  (request_id, processed_state, issuer_employee_internal_id, usuario_id, codigo_col,
   policy_name, clave, infotipo, from_date, to_date, dias,
   request_url, response_status, response_ok, response_text, created_at, responded_at)
VALUES
  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, SYSUTCDATETIME(), ?)
"""

def fmt_date_dot(d):
    """YYYY-MM-DD -> YYYY.MM.DD"""
    if not d:
        return None
    if isinstance(d, datetime):
        d = d.date()
    s = str(d)
    # admite 'YYYY-MM-DD' o 'YYYY-MM-DD 00:00:00'
    return s[:10].replace('-', '.')

def build_sap_url(codigo_col, from_date, to_date, clave, dias):
    params = {
        'sap-client': SAP_CLIENT,
        'num_personal': codigo_col,
        'fecha_inicial': fmt_date_dot(from_date),
        'fecha_final': fmt_date_dot(to_date),
        'infotipo': '2001',
        'clave': clave,
        'dias': str(dias),
    }
    # construye querystring a mano para log claro (sin credenciales)
    qs = '&'.join([f"{k}={v}" for k, v in params.items()])
    return f"{SAP_BASE_URL}?{qs}"

def policy_to_clave(policy_name):
    if not policy_name:
        return None
    return POLICY_CLAVE_MAP.get(policy_name.strip().upper())

def call_sap(url):
    auth = HTTPBasicAuth(SAP_USER, SAP_PASS) if (SAP_USER and SAP_PASS) else None
    verify = bool_env(SAP_VERIFY_SSL, False)
    try:
        r = requests.get(url, auth=auth, verify=verify, timeout=SAP_TIMEOUT)
        ok = 200 <= r.status_code < 300
        text = r.text.strip()
        return (r.status_code, ok, text)
    except Exception as ex:
        return (None, False, f"ERROR_REQUEST: {ex}")

def process():
    # Validaciones mínimas
    missing = [k for k,v in dict(DB_HOST=DB_HOST, DB_NAME=DB_NAME, DB_USER=DB_USER, DB_PASS=DB_PASS, SAP_BASE_URL=SAP_BASE_URL).items() if not v]
    if missing:
        print(f"Faltan variables .env: {', '.join(missing)}", file=sys.stderr)
        sys.exit(1)

    conn = connect_sqlserver()
    cur = conn.cursor()
    try:
        # 1) Traer pendientes a procesar (por estado)
        cur.execute(SQL_TO_PROCESS, *PROCESS_STATES)
        rows = cur.fetchall()
        total = 0

        for row in rows:
            request_id      = row.request_id
            email           = row.issuer_employee_internal_id
            usuario_id      = (email.split('@', 1)[0] if email else None)
            policy_name     = row.policy_name
            from_date       = row.from_date
            to_date         = row.to_date
            dias            = row.amount_requested
            state           = (row.state or '').upper()
            codigo_col      = row.CodigoCol

            # 2) Validaciones antes de llamar SAP
            clave = policy_to_clave(policy_name)
            if not clave:
                # No se conoce esa policy -> loguear como error sin llamar SAP
                req_url = build_sap_url(codigo_col or '', from_date, to_date, '??', dias)
                cur.execute(SQL_INSERT_EXPORT,
                    request_id, state, email, usuario_id, codigo_col,
                    policy_name, '??', '2001', from_date, to_date, dias,
                    req_url, None, 0, f"ERROR: policy_name no mapeada: {policy_name}", None)
                total += 1
                continue

            if not codigo_col:
                req_url = build_sap_url('', from_date, to_date, clave, dias)
                cur.execute(SQL_INSERT_EXPORT,
                    request_id, state, email, usuario_id, codigo_col,
                    policy_name, clave, '2001', from_date, to_date, dias,
                    req_url, None, 0, "ERROR: CodigoCol no encontrado en Organigrama para usuario_id", None)
                total += 1
                continue

            # 3) Llamada a SAP
            req_url = build_sap_url(codigo_col, from_date, to_date, clave, dias)
            status, ok, text = call_sap(req_url)

            # 4) Insert log (respuesta)
            responded_at = datetime.utcnow() if status is not None else None
            cur.execute(SQL_INSERT_EXPORT,
                request_id, state, email, usuario_id, codigo_col,
                policy_name, clave, '2001', from_date, to_date, dias,
                req_url, status, 1 if ok else 0, text, responded_at)

            total += 1

        conn.commit()
        print(f"Export SAP OK - registros procesados: {total}")
    except Exception as ex:
        conn.rollback()
        print(f"ERROR EXPORT: {ex}", file=sys.stderr)
        raise
    finally:
        cur.close()
        conn.close()

if __name__ == '__main__':
    process()
