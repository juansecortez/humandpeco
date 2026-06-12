# Tecnologías

## Backend

| Tecnología | Versión | Uso |
|------------|---------|-----|
| **PHP** | 8.1+ | Runtime Laravel |
| **Laravel** | 11.x | Framework web, rutas, auth, vistas |
| **Composer** | 2.x | Dependencias PHP |

## Frontend

| Tecnología | Uso |
|------------|-----|
| **Blade** | Plantillas del panel |
| **Material Dashboard Pro** (Creative Tim) | UI base, sidebar, tarjetas |
| **Bootstrap 4** | Grid, componentes |
| **jQuery + DataTables** | Tablas paginadas y filtros en Solicitudes / SAP |
| **Material Icons** | Iconografía |

## Integraciones

| Sistema | Protocolo | Descripción |
|---------|-----------|-------------|
| **Humand** | REST API (`/public/api/v1`) | Origen de solicitudes time-off |
| **SAP** | HTTP POST + Basic Auth | Endpoint `zrh_vacacion` (infotipo 2001) |
| **SQL Server** | ODBC / `sqlsrv` (PHP) + `pyodbc` (Python) | BD `HumandPeco` + join con `Organigrama` |
| **Hub autenticador** | HTTP POST | Validación de credenciales en producción |
| **Organigrama** | SQL Server (conexión `organigrama`) | Usuarios, roles y `CodigoCol` para SAP |

## Scripts Python

| Paquete | Uso |
|---------|-----|
| `requests` | Llamadas HTTP a Humand y SAP |
| `pyodbc` | Lectura/escritura SQL Server |
| `python-dotenv` | Carga de variables desde `.env` |

Python se ejecuta desde un **entorno virtual** en `scripts/.venv` (recomendado en Windows).

## Base de datos

- **Motor:** Microsoft SQL Server
- **BD principal:** `HumandPeco`
- **BD secundaria:** `Organigrama` (solo lectura para mapeo empleado → SAP)

## Servidor de aplicación (desarrollo)

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

En producción suele desplegarse detrás de **IIS** o **Apache** con PHP-FPM y el mismo esquema de scripts Python.
