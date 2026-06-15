#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os, sys, json, re, requests
from datetime import datetime, date
from requests.auth import HTTPBasicAuth
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
from dotenv import load_dotenv
import pyodbc

# === carga .env ===
ROOT_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
load_dotenv(os.path.join(ROOT_DIR, '.env'))

# ---------- helpers env ----------
def strip_quotes(s: str | None) -> str | None:
    if s is None: return None
    s = s.strip()
    if (s.startswith('"') and s.endswith('"')) or (s.startswith("'") and s.endswith("'")):
        return s[1:-1]
    return s

def getenv_clean(key: str, default: str | None = None) -> str | None:
    v = os.getenv(key, default)
    return strip_quotes(v) if v is not None else v

def bool_env(v, default=False):
    if v is None: return default
    return str(v).strip().lower() in ('1','true','yes','y','on')

EXPORT_VERBOSE = bool_env(os.getenv('EXPORT_VERBOSE', '0'))

def dbg(msg: str):
    if EXPORT_VERBOSE:
        print(f"[DEBUG] {msg}", flush=True)

# --- DB ---
DB_HOST   = getenv_clean('DB_HOST')
DB_PORT   = getenv_clean('DB_PORT') or '1433'
DB_NAME   = getenv_clean('DB_DATABASE')
DB_USER   = getenv_clean('DB_USERNAME')
DB_PASS   = getenv_clean('DB_PASSWORD')
DB_DRIVER = getenv_clean('DB_ODBC_DRIVER')
DB_ENCRYPT= getenv_clean('DB_ENCRYPT')      # 'true'/'false'
DB_TSC    = getenv_clean('DB_TRUST_SERVER_CERTIFICATE') or 'true'

# --- SAP ---
SAP_BASE_URL   = getenv_clean('SAP_BASE_URL')
SAP_CLIENT     = getenv_clean('SAP_CLIENT') or '110'
SAP_USER       = getenv_clean('SAP_USER')
SAP_PASS       = getenv_clean('SAP_PASS')
SAP_VERIFY_SSL = getenv_clean('SAP_VERIFY_SSL') or 'false'
CONNECT_TIMEOUT = int((getenv_clean('SAP_CONNECT_TIMEOUT') or '8').strip())
READ_TIMEOUT    = int((getenv_clean('SAP_READ_TIMEOUT') or getenv_clean('SAP_READ__TIMEOUT') or '20').strip())
SAP_KEY         = getenv_clean('SAP_KEY')
SAP_PARAM_MODE  = (getenv_clean('SAP_PARAM_MODE') or 'query').strip().lower()  # 'query' | 'form'

# --- HUMAND ---
HUMAND_API_BASE    = (getenv_clean('HUMAND_API_BASE') or 'https://api-prod.humand.co/public/api/v1').rstrip('/')
HUMAND_API_AUTH    = getenv_clean('HUMAND_API_AUTH')  # DEBE venir sin comillas
HUMAND_AUTO_CANCEL = bool_env(getenv_clean('HUMAND_AUTO_CANCEL') or 'true', True)
HUMAND_DEFAULT_CANCEL_REASON = getenv_clean('HUMAND_CANCEL_REASON') or "SE CANCELA POR QUE EL RANGO DE FECHAS NO ES UN DIA LABORABLE PARA EL USUARIO"

# --- comportamiento ---
PROCESS_STATES = [s.strip().upper() for s in (getenv_clean('PROCESS_STATES') or 'APPROVED,CANCELLED').split(',') if s.strip()]
DO_DEDUP = bool_env(getenv_clean('SAP_EXPORT_DEDUP') or 'true', True)

POLICY_CLAVE_MAP = {
    'VACACIONES FC': '6072',
    'SUPERVISORES': '6072',
    'LEGO': '6073',
    'ANTICIPOS DE VACACIONES': '6072',
}

# Respaldo por policy_type_id de Humand (si el nombre en BD varía)
POLICY_TYPE_ID_CLAVE_MAP = {
    9637: '6072',     # Vacaciones FC
    308356: '6072',   # Supervisores → mismo tratamiento que vacaciones
    172701: '6073',   # LEGO
    308355: '6072',   # Anticipos DC → mismo envío que vacaciones (fecha inicio/fin)
}

def export_scope() -> str:
    if len(sys.argv) > 1 and sys.argv[1].strip():
        return sys.argv[1].strip().lower()
    return (getenv_clean('SAP_EXPORT_SCOPE') or 'fc').strip().lower()

def policy_type_ids_for_sap() -> list:
    scope = export_scope()
    if scope == 'anticipos':
        raw = getenv_clean('SAP_EXPORT_ANTICIPOS_POLICY_TYPE_IDS') or '308355'
    else:
        raw = getenv_clean('SAP_EXPORT_POLICY_TYPE_IDS') or '9637,172701,308356'
    ids = [x.strip() for x in raw.split(',') if x.strip().isdigit()]
    if scope == 'anticipos':
        return ids or ['308355']
    return ids or ['9637', '172701', '308356']

SCOPE_POLICY_NAMES = {
    'fc': ['VACACIONES FC', 'LEGO', 'SUPERVISORES'],
    'anticipos': ['ANTICIPOS DE VACACIONES'],
}

def policy_names_for_sap() -> list:
    scope = export_scope()
    if scope == 'anticipos':
        raw = getenv_clean('SAP_EXPORT_ANTICIPOS_POLICY_NAMES') or 'ANTICIPOS DE VACACIONES'
    else:
        raw = getenv_clean('SAP_EXPORT_POLICY_NAMES') or 'VACACIONES FC,LEGO,SUPERVISORES'
    names = [n.strip().upper() for n in raw.split(',') if n.strip()]
    return names or SCOPE_POLICY_NAMES.get(scope, SCOPE_POLICY_NAMES['fc'])


def sql_base_export() -> str:
    state_ph = ', '.join(['?'] * len(PROCESS_STATES))
    policy_ids = policy_type_ids_for_sap()
    pid_ph = ', '.join(['?'] * len(policy_ids))
    policy_names = policy_names_for_sap()
    name_ph = ', '.join(['?'] * len(policy_names))
    scope = export_scope()
    like_parts = []
    if scope == 'anticipos':
        like_parts = [
            "UPPER(r.policy_name) LIKE '%ANTICIPO%'",
        ]
    else:
        like_parts = [
            "UPPER(r.policy_name) LIKE '%SUPERVISOR%'",
            "UPPER(r.policy_name) LIKE '%LEGO%'",
            "(UPPER(r.policy_name) LIKE '%VACACIONES%' AND UPPER(r.policy_name) NOT LIKE '%DC%' AND UPPER(r.policy_name) NOT LIKE '%ANTICIPO%')",
        ]
    like_sql = (' OR ' + ' OR '.join(like_parts)) if like_parts else ''
    return f"""
SELECT
  r.request_id,
  r.issuer_employee_internal_id,
  r.issuer_full_name,
  r.policy_name,
  r.policy_type_id,
  r.from_date,
  r.to_date,
  r.amount_requested,
  UPPER(r.state) AS state,
  CASE WHEN CHARINDEX('@', r.issuer_employee_internal_id) > 0
         THEN LEFT(r.issuer_employee_internal_id, CHARINDEX('@', r.issuer_employee_internal_id)-1)
       ELSE r.issuer_employee_internal_id
  END AS usuario_id
FROM dbo.time_off_requests r
WHERE UPPER(r.state) IN ({state_ph})
  AND (
    r.policy_type_id IN ({pid_ph})
    OR UPPER(LTRIM(RTRIM(r.policy_name))) IN ({name_ph}){like_sql}
  )
"""

def build_session():
    s = requests.Session()
    s.trust_env = False
    s.headers.update({'Accept': 'application/json'})
    retry = Retry(total=3, backoff_factor=0.6, status_forcelist=(429, 500, 502, 503, 504), allowed_methods=frozenset(['GET','POST','PUT']))
    adapter = HTTPAdapter(max_retries=retry)
    s.mount('http://', adapter)
    s.mount('https://', adapter)
    return s

SESSION = build_session()
INFOTIPO = '2001'

# ---------- util ----------
def pick_sql_driver():
    if DB_DRIVER and DB_DRIVER.strip(): return DB_DRIVER.strip()
    drivers = [d.strip() for d in pyodbc.drivers()]
    for name in ("ODBC Driver 18 for SQL Server","ODBC Driver 17 for SQL Server","SQL Server"):
        if name in drivers: return name
    raise RuntimeError(f"No hay driver ODBC válido. Instalados: {drivers}")

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
    if encrypt is not None: parts.append(f"Encrypt={'yes' if encrypt else 'no'}")
    login_timeout = int(getenv_clean('DB_LOGIN_TIMEOUT') or '5')
    pyodbc.pooling = True
    conn = pyodbc.connect(';'.join(parts)+';', timeout=login_timeout)
    return conn

# --- queries ---
SQL_GET_LAST = """
SELECT TOP 1 id, processed_state, response_ok, response_status, response_text,
       policy_name, clave, infotipo, from_date, to_date, dias, request_url
FROM dbo.sap_time_off_exports
WHERE request_id = ?
ORDER BY id DESC
"""

SQL_HAS_APPROVED_OK = """
SELECT TOP 1 1
FROM dbo.sap_time_off_exports
WHERE request_id = ? AND UPPER(processed_state)='APPROVED' AND response_ok=1
"""

SQL_DEDUP = """
;WITH d AS (
  SELECT id, ROW_NUMBER() OVER(PARTITION BY request_id ORDER BY id DESC) rn
  FROM dbo.sap_time_off_exports
)
DELETE FROM dbo.sap_time_off_exports WHERE id IN (SELECT id FROM d WHERE rn > 1);
"""

SQL_UPSERT = """
MERGE dbo.sap_time_off_exports AS T
USING (SELECT
    ? AS request_id,
    ? AS processed_state,
    ? AS issuer_employee_internal_id,
    ? AS issuer_full_name,
    ? AS usuario_id,
    ? AS codigo_col,
    ? AS policy_name,
    ? AS policy_type_id,
    ? AS clave,
    ? AS infotipo,
    ? AS from_date,
    ? AS to_date,
    ? AS dias,
    ? AS request_url,
    ? AS response_status,
    ? AS response_ok,
    ? AS response_text,
    SYSUTCDATETIME() AS responded_at
) AS S
ON (T.request_id = S.request_id)
WHEN MATCHED THEN
  UPDATE SET
    processed_state = S.processed_state,
    issuer_employee_internal_id = S.issuer_employee_internal_id,
    issuer_full_name            = S.issuer_full_name,
    usuario_id  = S.usuario_id,
    codigo_col  = S.codigo_col,
    policy_name = S.policy_name,
    policy_type_id = S.policy_type_id,
    clave       = S.clave,
    infotipo    = S.infotipo,
    from_date   = S.from_date,
    to_date     = S.to_date,
    dias        = S.dias,
    request_url = S.request_url,
    response_status = S.response_status,
    response_ok     = S.response_ok,
    response_text   = S.response_text,
    responded_at    = S.responded_at
WHEN NOT MATCHED THEN
  INSERT (request_id, processed_state, issuer_employee_internal_id, issuer_full_name, usuario_id, codigo_col,
          policy_name, policy_type_id, clave, infotipo, from_date, to_date, dias,
          request_url, response_status, response_ok, response_text, created_at, responded_at)
  VALUES (S.request_id, S.processed_state, S.issuer_employee_internal_id, S.issuer_full_name, S.usuario_id, S.codigo_col,
          S.policy_name, S.policy_type_id, S.clave, S.infotipo, S.from_date, S.to_date, S.dias,
          S.request_url, S.response_status, S.response_ok, S.response_text, SYSUTCDATETIME(), S.responded_at);
"""

SQL_UPSERT_LEGACY = """
MERGE dbo.sap_time_off_exports AS T
USING (SELECT
    ? AS request_id,
    ? AS processed_state,
    ? AS issuer_employee_internal_id,
    ? AS issuer_full_name,
    ? AS usuario_id,
    ? AS codigo_col,
    ? AS policy_name,
    ? AS clave,
    ? AS infotipo,
    ? AS from_date,
    ? AS to_date,
    ? AS dias,
    ? AS request_url,
    ? AS response_status,
    ? AS response_ok,
    ? AS response_text,
    SYSUTCDATETIME() AS responded_at
) AS S
ON (T.request_id = S.request_id)
WHEN MATCHED THEN
  UPDATE SET
    processed_state = S.processed_state,
    issuer_employee_internal_id = S.issuer_employee_internal_id,
    issuer_full_name            = S.issuer_full_name,
    usuario_id  = S.usuario_id,
    codigo_col  = S.codigo_col,
    policy_name = S.policy_name,
    clave       = S.clave,
    infotipo    = S.infotipo,
    from_date   = S.from_date,
    to_date     = S.to_date,
    dias        = S.dias,
    request_url = S.request_url,
    response_status = S.response_status,
    response_ok     = S.response_ok,
    response_text   = S.response_text,
    responded_at    = S.responded_at
WHEN NOT MATCHED THEN
  INSERT (request_id, processed_state, issuer_employee_internal_id, issuer_full_name, usuario_id, codigo_col,
          policy_name, clave, infotipo, from_date, to_date, dias,
          request_url, response_status, response_ok, response_text, created_at, responded_at)
  VALUES (S.request_id, S.processed_state, S.issuer_employee_internal_id, S.issuer_full_name, S.usuario_id, S.codigo_col,
          S.policy_name, S.clave, S.infotipo, S.from_date, S.to_date, S.dias,
          S.request_url, S.response_status, S.response_ok, S.response_text, SYSUTCDATETIME(), S.responded_at);
"""

_EXPORT_HAS_POLICY_TYPE_ID = False

def detect_export_columns(cur):
    global _EXPORT_HAS_POLICY_TYPE_ID
    cur.execute("""
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'sap_time_off_exports'
          AND COLUMN_NAME = 'policy_type_id'
    """)
    _EXPORT_HAS_POLICY_TYPE_ID = cur.fetchone() is not None
    if not _EXPORT_HAS_POLICY_TYPE_ID:
        print("Aviso: columna sap_time_off_exports.policy_type_id ausente; ejecute php artisan migrate", flush=True)

def fmt_date_sap(d):
    if not d: return None
    if isinstance(d, datetime): d = d.date()
    if isinstance(d, date): return f"{d.day:02d}.{d.month:02d}.{d.year:04d}"
    s = str(d)[:10]
    try:
        y,m,d2 = s.split('-'); return f"{d2}.{m}.{y}"
    except Exception:
        return s

def zero_pad_personal(codigo_col):
    if codigo_col is None: return ''
    s = str(codigo_col).strip()
    if s.isdigit(): return s.zfill(8)
    digits = ''.join(ch for ch in s if ch.isdigit())
    return digits.zfill(8) if digits else s

def normalize_policy_name(name) -> str:
    return (name or '').strip().upper()

def infer_policy_type_id(policy_name, policy_type_id=None):
    if policy_type_id is not None:
        try:
            pid = int(policy_type_id)
            if pid > 0:
                return pid
        except (TypeError, ValueError):
            pass
    norm = normalize_policy_name(policy_name)
    if not norm:
        return None
    name_to_id = {
        'VACACIONES FC': 9637,
        'SUPERVISORES': 308356,
        'LEGO': 172701,
        'ANTICIPOS DE VACACIONES': 308355,
    }
    if norm in name_to_id:
        return name_to_id[norm]
    if 'ANTICIPO' in norm:
        return 308355
    if 'SUPERVISOR' in norm:
        return 308356
    if 'LEGO' in norm:
        return 172701
    if 'VACACIONES' in norm and 'DC' not in norm:
        return 9637
    if 'VACACIONES' in norm and 'DC' in norm:
        return 179204
    return None

def policy_to_clave(policy_name, policy_type_id=None):
    pid = infer_policy_type_id(policy_name, policy_type_id)
    if pid is not None:
        clave = POLICY_TYPE_ID_CLAVE_MAP.get(pid)
        if clave:
            return clave
    norm = normalize_policy_name(policy_name)
    if norm in POLICY_CLAVE_MAP:
        return POLICY_CLAVE_MAP[norm]
    if 'LEGO' in norm:
        return '6073'
    if any(k in norm for k in ('SUPERVISOR', 'ANTICIPO', 'VACACIONES')):
        return '6072'
    return None

def _lookup_codigo_organigrama(cur, uid, internal):
    for sql in (
        """
        SELECT TOP 1 CodigoCol
        FROM Organigrama.dbo.OrganigramaCompleto
        WHERE UsuarioId = ? OR Correo = ? OR LOWER(Correo) = LOWER(?)
        """,
        """
        SELECT TOP 1 CodigoCol
        FROM Organigrama.dbo.Organigrama
        WHERE UsuarioId = ? OR Correo = ? OR LOWER(Correo) = LOWER(?)
        """,
    ):
        try:
            cur.execute(sql, uid, internal, internal)
            row = cur.fetchone()
            if row and row.CodigoCol:
                return str(row.CodigoCol).strip()
        except Exception as ex:
            dbg(f"lookup organigrama error: {ex}")
    return None

def resolve_codigo_col(conn, internal_id, usuario_id, request_id=None) -> str | None:
    """Resuelve CodigoCol: join por request_id, export previo, patrón DC, organigrama."""
    if request_id is not None:
        try:
            cur = conn.cursor()
            cur.execute(
                """
                SELECT TOP 1 o.CodigoCol
                FROM dbo.time_off_requests r
                LEFT JOIN Organigrama.dbo.OrganigramaCompleto o ON (
                    o.UsuarioId = CASE WHEN CHARINDEX('@', r.issuer_employee_internal_id) > 0
                        THEN LEFT(r.issuer_employee_internal_id, CHARINDEX('@', r.issuer_employee_internal_id)-1)
                        ELSE r.issuer_employee_internal_id END
                    OR LOWER(LTRIM(RTRIM(o.Correo))) = LOWER(LTRIM(RTRIM(r.issuer_employee_internal_id)))
                )
                WHERE r.request_id = ?
                """,
                request_id,
            )
            row = cur.fetchone()
            cur.close()
            if row and row.CodigoCol:
                return str(row.CodigoCol).strip()
        except Exception as ex:
            dbg(f"resolve_codigo_col via request_id {request_id}: {ex}")

    if request_id is not None:
        try:
            cur = conn.cursor()
            cur.execute(
                """
                SELECT TOP 1 codigo_col
                FROM dbo.sap_time_off_exports
                WHERE request_id = ? AND codigo_col IS NOT NULL AND LTRIM(RTRIM(codigo_col)) <> ''
                  AND response_ok = 1
                ORDER BY id DESC
                """,
                request_id,
            )
            row = cur.fetchone()
            cur.close()
            if row and row.codigo_col:
                return str(row.codigo_col).strip()
        except Exception as ex:
            dbg(f"resolve_codigo_col prior ok export: {ex}")

    internal = (internal_id or '').strip()
    if not internal:
        return None

    ln = len(internal)
    if ln >= 2 and ln % 2 == 0 and internal.isdigit():
        half = ln // 2
        left, right = internal[:half], internal[half:]
        if left == right:
            return (left.lstrip('0') or left)

    uid = (usuario_id or '').strip()
    if not uid and '@' in internal:
        uid = internal.split('@', 1)[0]
    if not uid:
        uid = internal

    cur = conn.cursor()
    codigo = _lookup_codigo_organigrama(cur, uid, internal)
    cur.close()
    if codigo:
        return codigo

    if internal.isdigit():
        return internal.lstrip('0') or internal

    return None

def build_log_url(accion, codigo_col, from_date, to_date, clave, dias):
    params = [
        f"sap-client={SAP_CLIENT}",
        f"accion={accion}",
        f"num_personal={zero_pad_personal(codigo_col)}",
        f"fecha_inicial={fmt_date_sap(from_date)}",
        f"fecha_final={fmt_date_sap(to_date)}",
        f"infotipo={INFOTIPO}",
        f"clave={clave}",
        f"dias={dias}",
    ]
    if SAP_KEY:
        params.insert(0, f"Key={SAP_KEY}")
    return f"{SAP_BASE_URL}?{'&'.join(params)}"

# --- detección flexible ---
def normalize_text(s: str) -> str:
    if not s: return ''
    repl = (('á','a'),('é','e'),('í','i'),('ó','o'),('ú','u'),
            ('Á','A'),('É','E'),('Í','I'),('Ó','O'),('Ú','U'),
            ('ñ','n'),('Ñ','N'))
    for a,b in repl: s = s.replace(a,b)
    return s.lower()

def message_indicates_free_workday(msg: str) -> bool:
    s = normalize_text(msg)
    return 'es libre' in s

# --- llamada SAP ---
def call_sap(payload):
    auth   = HTTPBasicAuth(SAP_USER, SAP_PASS) if (SAP_USER and SAP_PASS) else None
    verify = bool_env(SAP_VERIFY_SSL, False)

    url = f"{SAP_BASE_URL}?sap-client={SAP_CLIENT}"
    if SAP_KEY:
        url += f"&Key={requests.utils.quote(SAP_KEY)}"

    if EXPORT_VERBOSE:
        dbg(f"POST SAP url={url} mode={SAP_PARAM_MODE} payload={payload}")

    try:
        if SAP_PARAM_MODE == 'form':
            headers = dict(SESSION.headers)
            headers['Content-Type'] = 'application/x-www-form-urlencoded'
            r = SESSION.post(url, data=payload, headers=headers,
                             auth=auth, verify=verify,
                             timeout=(CONNECT_TIMEOUT, READ_TIMEOUT),
                             allow_redirects=False)
        else:
            r = SESSION.post(url, params=payload, headers=SESSION.headers,
                             auth=auth, verify=verify,
                             timeout=(CONNECT_TIMEOUT, READ_TIMEOUT),
                             allow_redirects=False)

        status = r.status_code
        text   = r.text.strip()
        ok = False
        data = None
        try:
            data = r.json()
            ok = (str(data.get('ESTATUS','')).upper() == 'OK')
            text = json.dumps(data, ensure_ascii=False)
        except Exception:
            ok = (200 <= status < 300)
        dbg(f"SAP resp status={status} ok={ok} text={text[:500]}")
        return (status, ok, text, data)
    except Exception as ex:
        dbg(f"SAP error: {ex}")
        return (None, False, f"ERROR_REQUEST: {ex}", None)

# --- HUMAND: cancelar request ---
def humand_cancel_request(request_id: int, reason: str) -> tuple[int, bool, str]:
    if not HUMAND_API_AUTH:
        return (0, False, "HUMAND_API_AUTH no configurado")
    url = f"{HUMAND_API_BASE}/time-off/requests/{request_id}/state"
    headers = {'accept': '*/*','Authorization': HUMAND_API_AUTH,'Content-Type': 'application/json'}
    body = {"state": "CANCELLED", "resolutionReason": reason or HUMAND_DEFAULT_CANCEL_REASON}
    dbg(f"PUT Humand url={url} headers.Authorization={'<set>' if HUMAND_API_AUTH else '<empty>'} body={body}")
    try:
        rsp = SESSION.put(url, headers=headers, json=body, timeout=(8, 20))
        ok = (200 <= rsp.status_code < 300)
        txt = rsp.text.strip()
        dbg(f"Humand resp status={rsp.status_code} ok={ok} text={txt[:500]}")
        return (rsp.status_code, ok, txt)
    except Exception as ex:
        dbg(f"Humand error: {ex}")
        return (0, False, f"ERROR_HUMAND_REQUEST: {ex}")

def upsert(cur, *, request_id, processed_state, email, issuer_full_name, usuario_id, codigo_col,
           policy_name, policy_type_id, clave, infotipo, from_date, to_date, dias,
           request_url, response_status, response_ok, response_text):
    if _EXPORT_HAS_POLICY_TYPE_ID:
        row = (
            request_id, processed_state, email, issuer_full_name, usuario_id, codigo_col,
            policy_name, policy_type_id, clave, infotipo, from_date, to_date, dias,
            request_url, response_status, 1 if response_ok else 0, response_text
        )
        cur.execute(SQL_UPSERT, row)
    else:
        row = (
            request_id, processed_state, email, issuer_full_name, usuario_id, codigo_col,
            policy_name, clave, infotipo, from_date, to_date, dias,
            request_url, response_status, 1 if response_ok else 0, response_text
        )
        cur.execute(SQL_UPSERT_LEGACY, row)

# ---------- main ----------
def process():
    missing = [k for k,v in dict(
        DB_HOST=DB_HOST, DB_NAME=DB_NAME, DB_USER=DB_USER, DB_PASS=DB_PASS, SAP_BASE_URL=SAP_BASE_URL
    ).items() if not v]
    if missing:
        print(f"Faltan variables .env: {', '.join(missing)}", file=sys.stderr, flush=True)
        sys.exit(1)

    conn = connect_sqlserver()
    cur = conn.cursor()
    try:
        detect_export_columns(cur)
        if DO_DEDUP:
            cur.execute(SQL_DEDUP)

        scope = export_scope()
        params = list(PROCESS_STATES) + policy_type_ids_for_sap() + policy_names_for_sap()
        cur.execute(sql_base_export(), *params)
        rows = cur.fetchall()
        print(
            f"Export SAP ({scope}): {len(rows)} filas candidatas "
            f"(ids={','.join(policy_type_ids_for_sap())}, names={','.join(policy_names_for_sap())})",
            flush=True,
        )

        total = 0
        for row in rows:
            request_id = row.request_id
            email      = row.issuer_employee_internal_id
            full_name  = (row.issuer_full_name or '').strip() if hasattr(row, 'issuer_full_name') else ''
            usuario_id = row.usuario_id
            policy     = (row.policy_name or '').strip()
            policy_type_id = getattr(row, 'policy_type_id', None)
            from_date  = row.from_date
            to_date    = row.to_date
            dias       = row.amount_requested
            state      = (row.state or '').upper()
            codigo_col = resolve_codigo_col(conn, email, usuario_id, request_id)
            effective_policy_id = infer_policy_type_id(policy, policy_type_id)

            clave = policy_to_clave(policy, effective_policy_id)

            if not full_name:
                if email and '@' in email: full_name = email.split('@', 1)[0]
                else: full_name = email or None

            dbg(f"req {request_id}: policy={policy!r} id={policy_type_id}->{effective_policy_id} clave={clave} codigo={codigo_col}")
            cur2 = conn.cursor(); cur2.execute(SQL_GET_LAST, request_id); last = cur2.fetchone(); cur2.close()
            cur3 = conn.cursor(); cur3.execute(SQL_HAS_APPROVED_OK, request_id); had_approved_ok = bool(cur3.fetchone()); cur3.close()

            accion = None
            must_call_sap = False

            if state == 'APPROVED':
                if last and (str(last.processed_state or '').upper() == 'APPROVED') and int(last.response_ok or 0) == 1:
                    must_call_sap = False
                else:
                    if not clave or not codigo_col:
                        req_url = build_log_url('INS?', codigo_col, from_date, to_date, (clave or '??'), dias)
                        err_detail = []
                        if not clave:
                            err_detail.append(f"clave desconocida policy={policy!r} id={policy_type_id}")
                        if not codigo_col:
                            err_detail.append(f"CodigoCol no resuelto internal={email!r} usuario={usuario_id!r}")
                        upsert(cur,
                            request_id=request_id, processed_state=state, email=email, issuer_full_name=full_name, usuario_id=usuario_id,
                            codigo_col=codigo_col, policy_name=policy, policy_type_id=effective_policy_id, clave=(clave or '??'), infotipo=INFOTIPO,
                            from_date=from_date, to_date=to_date, dias=dias,
                            request_url=req_url, response_status=(last.response_status if last else None),
                            response_ok=False, response_text="ACCION=? | ERROR: " + '; '.join(err_detail))
                        print(f" ! req {request_id} ({policy}): omitido INS -> {'; '.join(err_detail)}", flush=True)
                        total += 1
                        continue
                    accion = 'INS'; must_call_sap = True

            elif state == 'CANCELLED':
                if not had_approved_ok:
                    dbg(f"req {request_id}: CANCELLED sin APPROVED OK previo -> skip total")
                    continue
                if last and (str(last.processed_state or '').upper() == 'CANCELLED') and int(last.response_ok or 0) == 1:
                    must_call_sap = False
                    accion = 'DEL'
                else:
                    if not clave or not codigo_col:
                        req_url = build_log_url('DEL?', codigo_col, from_date, to_date, (clave or '??'), dias)
                        err_detail = []
                        if not clave:
                            err_detail.append(f"clave desconocida policy={policy!r} id={effective_policy_id}")
                        if not codigo_col:
                            err_detail.append(f"CodigoCol no resuelto internal={email!r} usuario={usuario_id!r}")
                        upsert(cur,
                            request_id=request_id, processed_state=state, email=email, issuer_full_name=full_name, usuario_id=usuario_id,
                            codigo_col=codigo_col, policy_name=policy, policy_type_id=effective_policy_id, clave=(clave or '??'), infotipo=INFOTIPO,
                            from_date=from_date, to_date=to_date, dias=dias,
                            request_url=req_url, response_status=(last.response_status if last else None),
                            response_ok=False, response_text="ACCION=? | ERROR: " + '; '.join(err_detail))
                        print(f" ! req {request_id} ({policy}): omitido DEL -> {'; '.join(err_detail)}", flush=True)
                        total += 1
                        continue
                    accion = 'DEL'; must_call_sap = True
            else:
                continue

            req_url = build_log_url(accion or '?', codigo_col, from_date, to_date, (clave or '??'), dias)

            if must_call_sap and accion in ('INS','DEL'):
                payload = {
                    'num_personal' : zero_pad_personal(codigo_col),
                    'fecha_inicial': fmt_date_sap(from_date),
                    'fecha_final'  : fmt_date_sap(to_date),
                    'infotipo'     : INFOTIPO,
                    'clave'        : str(clave),
                    'dias'         : str(dias),
                    'accion'       : accion,
                }
                print(f" - req {request_id}: {accion} -> POST ({SAP_PARAM_MODE})", flush=True)
                status, ok, text, data = call_sap(payload)

                humand_note = ""
                # idempotencia: si ya cancelamos antes, no repetir
                already_cancelled = bool(last and last.response_text and 'HUMAND_CANCEL ok=True' in (last.response_text or ''))
                if not ok and accion == 'INS' and HUMAND_AUTO_CANCEL and not already_cancelled:
                    mensajes = []
                    try:
                        if isinstance(data, dict) and isinstance(data.get('MENSAJES'), list):
                            mensajes = [str(m) for m in data['MENSAJES']]
                    except Exception:
                        pass
                    if not mensajes and text:
                        try:
                            j = json.loads(text)
                            if isinstance(j, dict) and isinstance(j.get('MENSAJES'), list):
                                mensajes = [str(m) for m in j['MENSAJES']]
                        except Exception:
                            mensajes = [text]

                    dbg(f"SAP MENSAJES detectados: {mensajes}")
                    found_trigger = any(message_indicates_free_workday(m) for m in mensajes)
                    dbg(f"trigger 'es libre' => {found_trigger}")

                    if found_trigger:
                        detalle = next((m for m in mensajes if message_indicates_free_workday(m)), None)
                        reason = HUMAND_DEFAULT_CANCEL_REASON
                        print(f"   * Detalle SAP detectado: {detalle}", flush=True)
                        print(f"   * Llamando Humand CANCEL para request_id={request_id}", flush=True)
                        h_status, h_ok, h_txt = humand_cancel_request(request_id, reason)
                        humand_note = f" | HUMAND_CANCEL status={h_status} ok={h_ok} resp={h_txt[:500]}"
                    elif EXPORT_VERBOSE:
                        print(f"   * No coincide 'es libre', no se cancela en Humand", flush=True)

                msg = f"ACCION={accion} | {text}{humand_note}"
                upsert(cur,
                    request_id=request_id, processed_state=state, email=email, issuer_full_name=full_name, usuario_id=usuario_id,
                    codigo_col=codigo_col, policy_name=policy, policy_type_id=effective_policy_id, clave=str(clave), infotipo=INFOTIPO,
                    from_date=from_date, to_date=to_date, dias=dias,
                    request_url=req_url, response_status=status, response_ok=ok, response_text=msg)

            else:
                status = last.response_status if last else None
                okflag = (int(last.response_ok or 0) == 1) if last else False
                msg = (last.response_text if last else "PENDIENTE")
                if state == 'CANCELLED' and not had_approved_ok:
                    pass
                else:
                    upsert(cur,
                        request_id=request_id, processed_state=state, email=email, issuer_full_name=full_name, usuario_id=usuario_id,
                        codigo_col=codigo_col, policy_name=policy, policy_type_id=effective_policy_id, clave=str(clave or '??'), infotipo=INFOTIPO,
                        from_date=from_date, to_date=to_date, dias=dias,
                        request_url=req_url, response_status=status, response_ok=okflag, response_text=msg)

            total += 1

        conn.commit()
        print(f"Export SAP OK - registros procesados/actualizados: {total}", flush=True)
    except Exception as ex:
        conn.rollback()
        print(f"ERROR EXPORT: {ex}", file=sys.stderr, flush=True)
        raise
    finally:
        cur.close(); conn.close()

if __name__ == '__main__':
    process()
