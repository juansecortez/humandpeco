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
SAP_BASE_URL   = os.getenv('SAP_BASE_URL')   # ej: https://qas.../sap/bc/zrh_vacacion/zrh
SAP_CLIENT     = os.getenv('SAP_CLIENT', '110')
SAP_USER       = os.getenv('SAP_USER')       # opcional
SAP_PASS       = os.getenv('SAP_PASS')       # opcional
SAP_VERIFY_SSL = os.getenv('SAP_VERIFY_SSL', 'false')  # false si cert interno
CONNECT_TIMEOUT = int((os.getenv('SAP_CONNECT_TIMEOUT') or '8').strip())
READ_TIMEOUT    = int((os.getenv('SAP_READ_TIMEOUT') or os.getenv('SAP_READ__TIMEOUT') or '20').strip())
SAP_KEY         = os.getenv('SAP_KEY')  # si tu endpoint exige este param en query

# Enviar parámetros como: query (default) o form
SAP_PARAM_MODE = (os.getenv('SAP_PARAM_MODE') or 'query').strip().lower()  # 'query' | 'form'

# --- HUMAND (.env) ---
# Ejemplo:
# HUMAND_API_BASE="https://api-prod.humand.co/public/api/v1"
# HUMAND_API_AUTH="Basic abcDEF..."
HUMAND_API_BASE    = (os.getenv('HUMAND_API_BASE') or 'https://api-prod.humand.co/public/api/v1').rstrip('/')
HUMAND_API_AUTH    = os.getenv('HUMAND_API_AUTH')  # "Basic ...", obligatorio
# Activar/desactivar cancelación automática en Humand ante el mensaje específico de SAP
HUMAND_AUTO_CANCEL = (os.getenv('HUMAND_AUTO_CANCEL', 'true').strip().lower() in ('1','true','yes','y','on'))
# Mensaje por defecto para resolutionReason
HUMAND_DEFAULT_CANCEL_REASON = (
    os.getenv('HUMAND_CANCEL_REASON') or
    "SE CANCELA POR QUE EL RANGO DE FECHAS NO ES UN DIA LABORABLE PARA EL USUARIO"
)

# --- comportamiento general ---
# estados a considerar que vienen de Humand
PROCESS_STATES = [s.strip().upper() for s in os.getenv('PROCESS_STATES', 'APPROVED,CANCELLED').split(',') if s.strip()]
# hace limpieza inicial de duplicados (una fila por request_id)
DO_DEDUP = (os.getenv('SAP_EXPORT_DEDUP','true').strip().lower() in ('1','true','yes','y','on'))

# Mapeo policy->clave
POLICY_CLAVE_MAP = {'VACACIONES': '6072', 'LEGO': '6073'}

# ---- Sesión HTTP ----
def build_session():
    s = requests.Session()
    s.trust_env = False
    s.headers.update({'Accept': 'application/json'})
    # Retries conservadores para errores transitorios
    retry = Retry(total=3, backoff_factor=0.6, status_forcelist=(429, 500, 502, 503, 504), allowed_methods=frozenset(['GET','POST','PUT']))
    adapter = HTTPAdapter(max_retries=retry)
    s.mount('http://', adapter)
    s.mount('https://', adapter)
    return s

SESSION = build_session()
INFOTIPO = '2001'

# ---------- util ----------
def bool_env(v, default=False):
    if v is None: return default
    return str(v).strip().lower() in ('1','true','yes','y','on')

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
    # timeouts opcionales
    login_timeout = int(os.getenv('DB_LOGIN_TIMEOUT', '5'))
    pyodbc.pooling = True
    conn = pyodbc.connect(';'.join(parts)+';', timeout=login_timeout)
    return conn

# --- queries ---
SQL_BASE = f"""
SELECT
  r.request_id,
  r.issuer_employee_internal_id,
  r.issuer_full_name,
  r.policy_name,
  r.from_date,
  r.to_date,
  r.amount_requested,
  UPPER(r.state) AS state,
  CASE WHEN CHARINDEX('@', r.issuer_employee_internal_id) > 0
         THEN LEFT(r.issuer_employee_internal_id, CHARINDEX('@', r.issuer_employee_internal_id)-1)
       ELSE r.issuer_employee_internal_id
  END AS usuario_id,
  o.CodigoCol
FROM dbo.time_off_requests r
LEFT JOIN Organigrama.dbo.Organigrama o
  ON o.UsuarioId = CASE WHEN CHARINDEX('@', r.issuer_employee_internal_id) > 0
                          THEN LEFT(r.issuer_employee_internal_id, CHARINDEX('@', r.issuer_employee_internal_id)-1)
                         ELSE r.issuer_employee_internal_id END
WHERE UPPER(r.state) IN ({', '.join(['?' for _ in PROCESS_STATES])})
"""

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

# --- helpers de formato ---
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

def policy_to_clave(policy_name):
    if not policy_name: return None
    return POLICY_CLAVE_MAP.get((policy_name or '').strip().upper())

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

# --- patrones de error SAP que disparan cancelación en Humand ---
# Normalizamos removiendo acentos y trabajando en minúsculas.
# Ejemplo entrante:
# "Día fin 11.10.2025 de presencia/absentismo es libre de tbjo. (cl.pres./abs. 6072)"
RE_SAP_LIBRE_TRABAJO = re.compile(
    r'presencia/absentismo\s+es\s+libre\s+de\s+tbjo.*\(cl\.pres\./abs\.\s*6072\)',
    re.IGNORECASE
)

def normalize_text(s: str) -> str:
    # Remueve acentos simples por robustez (sin dependencias externas)
    repl = (
        ('á','a'),('é','e'),('í','i'),('ó','o'),('ú','u'),
        ('Á','A'),('É','E'),('Í','I'),('Ó','O'),('Ú','U'),
        ('ñ','n'),('Ñ','N')
    )
    for a,b in repl: s = s.replace(a,b)
    return s

def message_indicates_free_workday(msg: str) -> bool:
    if not msg: return False
    s = normalize_text(msg)
    return bool(RE_SAP_LIBRE_TRABAJO.search(s))

# --- llamada SAP (params en query o form) ---
def call_sap(payload):
    """
    payload dict con: num_personal, fecha_inicial, fecha_final, infotipo, clave, dias, accion
    """
    auth   = HTTPBasicAuth(SAP_USER, SAP_PASS) if (SAP_USER and SAP_PASS) else None
    verify = bool_env(SAP_VERIFY_SSL, False)

    url = f"{SAP_BASE_URL}?sap-client={SAP_CLIENT}"
    if SAP_KEY:
        url += f"&Key={requests.utils.quote(SAP_KEY)}"

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
        return (status, ok, text, data)
    except Exception as ex:
        return (None, False, f"ERROR_REQUEST: {ex}", None)

# --- HUMAND: cancelar request ---
def humand_cancel_request(request_id: int, reason: str) -> tuple[int, bool, str]:
    """
    PUT https://.../time-off/requests/{request_id}/state
    Body: {"state":"CANCELLED","resolutionReason":"..."}
    """
    if not HUMAND_API_AUTH:
        return (0, False, "HUMAND_API_AUTH no configurado")

    url = f"{HUMAND_API_BASE}/time-off/requests/{request_id}/state"
    headers = {
        'accept': '*/*',
        'Authorization': HUMAND_API_AUTH,
        'Content-Type': 'application/json',
    }
    body = {
        "state": "CANCELLED",
        "resolutionReason": reason or HUMAND_DEFAULT_CANCEL_REASON
    }
    try:
        rsp = SESSION.put(url, headers=headers, json=body, timeout=(8, 20))
        ok = (200 <= rsp.status_code < 300)
        txt = rsp.text.strip()
        return (rsp.status_code, ok, txt)
    except Exception as ex:
        return (0, False, f"ERROR_HUMAND_REQUEST: {ex}")

def upsert(cur, *, request_id, processed_state, email, issuer_full_name, usuario_id, codigo_col,
           policy_name, clave, infotipo, from_date, to_date, dias,
           request_url, response_status, response_ok, response_text):
    row = (
        request_id, processed_state, email, issuer_full_name, usuario_id, codigo_col,
        policy_name, clave, infotipo, from_date, to_date, dias,
        request_url, response_status, 1 if response_ok else 0, response_text
    )
    cur.execute(SQL_UPSERT, row)

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
        if DO_DEDUP:
            cur.execute(SQL_DEDUP)

        cur.execute(SQL_BASE, *PROCESS_STATES)
        rows = cur.fetchall()
        print(f"Export SAP: {len(rows)} filas candidatas", flush=True)

        total = 0
        for row in rows:
            request_id = row.request_id
            email      = row.issuer_employee_internal_id
            full_name  = (row.issuer_full_name or '').strip() if hasattr(row, 'issuer_full_name') else ''
            usuario_id = row.usuario_id
            policy     = (row.policy_name or '').strip()
            from_date  = row.from_date
            to_date    = row.to_date
            dias       = row.amount_requested
            state      = (row.state or '').upper()
            codigo_col = row.CodigoCol

            # fallback de nombre si viniera vacío
            if not full_name:
                if email and '@' in email:
                    full_name = email.split('@', 1)[0]
                else:
                    full_name = email or None

            clave = policy_to_clave(policy)

            # último estado registrado en export (si existiera)
            cur2 = conn.cursor(); cur2.execute(SQL_GET_LAST, request_id); last = cur2.fetchone(); cur2.close()

            # ¿hubo un APPROVED OK alguna vez? (para habilitar DEL)
            cur3 = conn.cursor(); cur3.execute(SQL_HAS_APPROVED_OK, request_id); had_approved_ok = bool(cur3.fetchone()); cur3.close()

            accion = None
            must_call_sap = False

            # --- lógica de decisión ---
            if state == 'APPROVED':
                if last and (str(last.processed_state or '').upper() == 'APPROVED') and int(last.response_ok or 0) == 1:
                    must_call_sap = False
                else:
                    if not clave or not codigo_col:
                        req_url = build_log_url('INS?', codigo_col, from_date, to_date, (clave or '??'), dias)
                        upsert(cur,
                            request_id=request_id, processed_state=state, email=email, issuer_full_name=full_name, usuario_id=usuario_id,
                            codigo_col=codigo_col, policy_name=policy, clave=(clave or '??'), infotipo=INFOTIPO,
                            from_date=from_date, to_date=to_date, dias=dias,
                            request_url=req_url, response_status=(last.response_status if last else None),
                            response_ok=False, response_text="ACCION=? | ERROR: datos insuficientes (clave/codigo_col)")
                        total += 1
                        continue
                    accion = 'INS'; must_call_sap = True

            elif state == 'CANCELLED':
                if not had_approved_ok:
                    print(f" - req {request_id}: CANCELLED sin APPROVED OK previo -> skip total", flush=True)
                    continue
                if last and (str(last.processed_state or '').upper() == 'CANCELLED') and int(last.response_ok or 0) == 1:
                    must_call_sap = False
                    accion = 'DEL'
                else:
                    if not clave or not codigo_col:
                        req_url = build_log_url('DEL?', codigo_col, from_date, to_date, (clave or '??'), dias)
                        upsert(cur,
                            request_id=request_id, processed_state=state, email=email, issuer_full_name=full_name, usuario_id=usuario_id,
                            codigo_col=codigo_col, policy_name=policy, clave=(clave or '??'), infotipo=INFOTIPO,
                            from_date=from_date, to_date=to_date, dias=dias,
                            request_url=req_url, response_status=(last.response_status if last else None),
                            response_ok=False, response_text="ACCION=? | ERROR: datos insuficientes (clave/codigo_col)")
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

                # --- Analizar respuesta SAP para detectar el caso "presencia/absentismo es libre de tbjo..."
                humand_note = ""
                if not ok and accion == 'INS' and HUMAND_AUTO_CANCEL:
                    # Intentar extraer MENSAJES desde JSON si es que venía JSON
                    mensajes = []
                    try:
                        if isinstance(data, dict) and isinstance(data.get('MENSAJES'), list):
                            mensajes = [str(m) for m in data['MENSAJES']]
                    except Exception:
                        pass
                    # si no hay data JSON, intentar con texto plano
                    if not mensajes and text:
                        try:
                            j = json.loads(text)
                            if isinstance(j, dict) and isinstance(j.get('MENSAJES'), list):
                                mensajes = [str(m) for m in j['MENSAJES']]
                        except Exception:
                            # texto plano; lo tratamos como un único mensaje
                            mensajes = [text]

                    # ¿Algún mensaje coincide?
                    found_trigger = any(message_indicates_free_workday(m) for m in mensajes)

                    if found_trigger:
                        # Mensaje a registrar y razón para Humand
                        # Extrae y conserva, p.ej.: "presencia/absentismo es libre de tbjo. (cl.pres./abs. 6072)"
                        detalle = None
                        for m in mensajes:
                            if message_indicates_free_workday(m):
                                detalle = m
                                break
                        # Llamar a Humand para CANCELAR
                        reason = HUMAND_DEFAULT_CANCEL_REASON
                        print(f"   * Detalle SAP detectado: {detalle}", flush=True)
                        print(f"   * Llamando Humand CANCEL para request_id={request_id}", flush=True)
                        h_status, h_ok, h_txt = humand_cancel_request(request_id, reason)
                        humand_note = f" | HUMAND_CANCEL status={h_status} ok={h_ok} resp={h_txt[:500]}"

                        # Si Humand canceló OK, conviene reflejar estado CANCELLED en nuestra tabla,
                        # pero sin romper tu lógica original registramos la respuesta como parte del texto.
                        # (No cambiamos processed_state aquí; seguimos registrando el resultado de la acción SAP.)
                msg = f"ACCION={accion} | {text}{humand_note}"

                upsert(cur,
                    request_id=request_id, processed_state=state, email=email, issuer_full_name=full_name, usuario_id=usuario_id,
                    codigo_col=codigo_col, policy_name=policy, clave=str(clave), infotipo=INFOTIPO,
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
                        codigo_col=codigo_col, policy_name=policy, clave=str(clave or '??'), infotipo=INFOTIPO,
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
