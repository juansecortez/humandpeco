# Operación diaria

## Panel web

Tras iniciar sesión, menú **Solicitudes**:

### LEGO / Vacaciones FC

1. Revisar tarjetas de resumen (total, aprobadas, en trámite).
2. Filtrar por empleado o estado en la barra superior.
3. Pulsar **Sincronizar {política}** para traer datos recientes de Humand.
4. La tabla muestra solicitudes ya guardadas en BD (no requiere sync para ver histórico).

### Estatus SAP

1. Ver log de envíos (OK, ERROR, PENDIENTE).
2. Pulsar **Enviar a SAP** para procesar pendientes (`export_time_off_to_sap.py`).
3. Clic en icono de recibo para ver respuesta detallada de SAP.

---

## Ejecutar ETL desde consola

Útil para tareas programadas o pruebas.

```powershell
cd "C:\Humand Peco\HumandPeco"

# LEGO
scripts\.venv\Scripts\python.exe scripts\etl_time_off_requests.py --policy-type-ids 172701

# Vacaciones FC
scripts\.venv\Scripts\python.exe scripts\etl_time_off_requests.py --policy-type-ids 9637

# Con filtros adicionales
scripts\.venv\Scripts\python.exe scripts\etl_time_off_requests.py `
  --policy-type-ids 9637 `
  --states APPROVED,CANCELLED `
  --created-at-since 2026-01-01
```

Argumentos CLI del ETL:

| Argumento | API Humand |
|-----------|------------|
| `--policy-type-ids` | `policyTypeIds` |
| `--states` | `states` |
| `--from-date` | `fromDate` |
| `--to-date` | `toDate` |
| `--created-at-since` | `createdAtSince` |
| `--limit` | `limit` |

---

## Ejecutar export SAP desde consola

```powershell
scripts\.venv\Scripts\python.exe scripts\export_time_off_to_sap.py
```

Procesa solicitudes en estado `APPROVED` y `CANCELLED` (ver `PROCESS_STATES` en `.env`).

---

## Tareas programadas (recomendado en producción)

Laravel **no** incluye cron para ETL; configurar en **Programador de tareas de Windows** o SQL Agent.

Ejemplo Task Scheduler — ETL LEGO cada hora:

- **Programa:** `C:\Humand Peco\HumandPeco\scripts\.venv\Scripts\python.exe`
- **Argumentos:** `scripts\etl_time_off_requests.py --policy-type-ids 172701`
- **Iniciar en:** `C:\Humand Peco\HumandPeco`

Repetir job separado para Vacaciones FC (`9637`).

Export SAP (ej. cada 15 min):

- **Argumentos:** `scripts\export_time_off_to_sap.py`

---

## Flujo operativo sugerido

```text
1. ETL LEGO          (cada X minutos)
2. ETL Vacaciones FC (cada X minutos)
3. Export SAP        (cada Y minutos, o manual desde panel)
4. Revisar Estatus SAP ante errores
```

---

## Mantenimiento Python

Actualizar dependencias:

```powershell
scripts\.venv\Scripts\python.exe -m pip install -r scripts\requirements.txt --upgrade
```

Recrear venv desde cero:

```powershell
Remove-Item -Recurse -Force scripts\.venv
C:\Python314\python.exe -m venv scripts\.venv
scripts\.venv\Scripts\python.exe -m pip install -r scripts\requirements.txt
```

---

## Logs

- **Laravel:** `storage/logs/laravel.log`
- **ETL / SAP:** salida en pantalla al ejecutar scripts; en panel, mensajes flash tras sync/export
- **Detalle SAP:** columna `response_text` en `sap_time_off_exports` o modal en Estatus SAP
