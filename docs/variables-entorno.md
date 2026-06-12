# Variables de entorno (`.env`)

Referencia de las variables usadas por HumandPeco. **No incluir credenciales reales en documentación ni en git.**

## Aplicación

| Variable | Ejemplo | Descripción |
|----------|---------|-------------|
| `APP_NAME` | MaterialPro | Nombre interno |
| `APP_ENV` | `local` / `production` | Entorno |
| `APP_DEBUG` | `true` / `false` | Debug Laravel |
| `APP_URL` | `http://127.0.0.1:8000` | URL base |

## Base de datos principal (`HumandPeco`)

| Variable | Descripción |
|----------|-------------|
| `DB_CONNECTION` | `sqlsrv` |
| `DB_HOST` | IP/hostname SQL Server |
| `DB_PORT` | `1433` |
| `DB_DATABASE` | `HumandPeco` |
| `DB_USERNAME` | Usuario SQL |
| `DB_PASSWORD` | Contraseña |
| `DB_ENCRYPT` | `true` / `false` |
| `DB_TRUST_SERVER_CERTIFICATE` | `true` en dev si aplica |
| `DB_ODBC_DRIVER` | Opcional; auto-detecta en Python |

Organigrama usa conexión `organigrama` en `config/database.php` (host/credenciales propias o mismas que BD principal).

## Python / scripts

| Variable | Descripción |
|----------|-------------|
| `PYTHON_BIN` | Ruta absoluta al ejecutable Python del venv |

```env
PYTHON_BIN='C:/Humand Peco/HumandPeco/scripts/.venv/Scripts/python.exe'
```

Si no se define, Laravel usa por defecto `scripts/.venv/Scripts/python.exe` (`config/scripts.php`).

## Humand API

| Variable | Descripción |
|----------|-------------|
| `HUMAND_API_BASE` | Base URL API v1 |
| `HUMAND_API_URL` | URL ETL inicial (puede incluir `?page=1` y otros query params) |
| `HUMAND_API_AUTH` | Header `Authorization` (Basic + API key) |
| `HUMAND_AUTO_CANCEL` | `true` — cancelar en Humand si SAP rechaza por día libre |
| `HUMAND_CANCEL_REASON` | Texto al cancelar en Humand |
| `HUMAND_POLICY_LEGO_ID` | `172701` |
| `HUMAND_POLICY_VACACIONES_FC_ID` | `9637` |
| `HUMAND_ETL_STATES` | Opcional. Ej: `APPROVED,CANCELLED` (vacío = todos) |
| `HUMAND_ETL_CREATED_AT_SINCE` | Opcional. Ej: `2026-01-01` |

## SAP

| Variable | Descripción |
|----------|-------------|
| `SAP_BASE_URL` | URL servicio `zrh_vacacion` |
| `SAP_CLIENT` | Cliente SAP (ej. `300`) |
| `SAP_USER` | Usuario RFC/API |
| `SAP_PASS` | Contraseña |
| `SAP_VERIFY_SSL` | `false` si certificado interno |
| `SAP_KEY` | Key opcional en query |
| `SAP_CONNECT_TIMEOUT` | Segundos |
| `SAP_READ_TIMEOUT` | Segundos |
| `SAP_PARAM_MODE` | `query` o `form` |
| `SAP_EXPORT_DEDUP` | `true` — deduplicar log exports |
| `PROCESS_STATES` | Estados a exportar. Default: `APPROVED,CANCELLED` |

## Power BI (dashboard opcional)

| Variable | Descripción |
|----------|-------------|
| `POWERBI_EMBED_URL` | URL embed reporte |
| `POWERBI_TITLE` | Título en home |
| `POWERBI_HEIGHT_MODE` | `ratio` / `vh` / `px` |

## Archivos de configuración relacionados

| Archivo | Contenido |
|---------|-----------|
| `config/time_off_policies.php` | Slugs, labels e IDs de políticas |
| `config/scripts.php` | Ruta Python |
| `config/database.php` | Conexiones `sqlsrv` y `organigrama` |
| `scripts/requirements.txt` | Dependencias pip |
