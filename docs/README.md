# HumandPeco — Documentación

Integración entre **Humand** (solicitudes de vacaciones/permisos) y **SAP** (registro en nómina/RH), con panel web de administración y monitoreo.

## Índice

| Documento | Contenido |
|-----------|-----------|
| [Arquitectura y funcionamiento](arquitectura.md) | Flujo de datos, tablas, estados, políticas |
| [Instalación](instalacion.md) | Requisitos, configuración inicial, Python, BD |
| [Operación](operacion.md) | Cómo usar el panel, ETL, export SAP, tareas programadas |
| [Variables de entorno](variables-entorno.md) | Referencia de `.env` |
| [Tecnologías](tecnologias.md) | Stack técnico del proyecto |

## Resumen rápido

```
Humand API  →  ETL (Python)  →  SQL Server (time_off_requests)
                                        ↓
                                 Export SAP (Python)  →  SAP zrh_vacacion
                                        ↓
                                 sap_time_off_exports (log)
```

**Panel web:** Laravel 11 · Material Dashboard  
**URL local típica:** http://127.0.0.1:8000

## Módulos del menú

- **Solicitudes → LEGO** — sync y consulta política LEGO (`policyTypeId` 172701)
- **Solicitudes → Vacaciones FC** — sync y consulta Vacaciones FC (`9637`)
- **Solicitudes → Estatus SAP** — log de envíos a SAP y botón de exportación

## Scripts principales

| Script | Función |
|--------|---------|
| `scripts/etl_time_off_requests.py` | Descarga solicitudes desde Humand y las guarda en BD |
| `scripts/export_time_off_to_sap.py` | Envía a SAP solicitudes APPROVED/CANCELLED |
| `scripts/requirements.txt` | Dependencias Python |

## Soporte / mantenimiento

Tras clonar el repo o actualizar dependencias Python:

```powershell
cd "C:\Humand Peco\HumandPeco"
C:\Python314\python.exe -m venv scripts\.venv
scripts\.venv\Scripts\python.exe -m pip install -r scripts\requirements.txt
```

Ver [Instalación](instalacion.md) para el detalle completo.
