# Instalación

## Requisitos

### Software

- **PHP** 8.1+ con extensiones: `sqlsrv`, `pdo_sqlsrv`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`
- **Composer** 2.x
- **Python** 3.10+ (3.14 probado en desarrollo)
- **ODBC Driver for SQL Server** (17 o 18) — para Python y PHP
- **Node.js** (opcional) — solo si se recompilan assets front (`npm run prod`)
- Acceso de red a:
  - SQL Server (`HumandPeco`, `Organigrama`)
  - API Humand (`api-prod.humand.co`)
  - SAP (`zrh_vacacion`)
  - Hub de autenticación (producción)

### Permisos

- Lectura/escritura en `storage/`, `bootstrap/cache/`
- Ejecución de `scripts/.venv/Scripts/python.exe` desde el usuario del servicio web

---

## 1. Clonar e instalar PHP

```powershell
cd "C:\Humand Peco\HumandPeco"
composer install
copy .env.example .env   # si no existe .env, copiar y ajustar
php artisan key:generate
```

Configurar `.env` (ver [variables-entorno.md](variables-entorno.md)).

---

## 2. Base de datos

```powershell
php artisan migrate
```

Opcional (usuarios demo del theme):

```powershell
php artisan migrate --seed
```

Verificar conexión SQL Server desde PHP:

```powershell
php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';"
```

---

## 3. Entorno Python (ETL / SAP)

Crear venv e instalar dependencias:

```powershell
cd "C:\Humand Peco\HumandPeco"
C:\Python314\python.exe -m venv scripts\.venv
scripts\.venv\Scripts\python.exe -m pip install -r scripts\requirements.txt
```

En `.env`, indicar el Python del venv (ruta con espacios entre comillas simples):

```env
PYTHON_BIN='C:/Humand Peco/HumandPeco/scripts/.venv/Scripts/python.exe'
```

Probar ETL manualmente:

```powershell
scripts\.venv\Scripts\python.exe scripts\etl_time_off_requests.py --policy-type-ids 172701
```

Salida esperada: `ETL OK - policyTypeIds=172701 - filas procesadas: N`

---

## 4. Storage y caché

```powershell
php artisan storage:link
php artisan config:clear
php artisan view:clear
```

---

## 5. Levantar la aplicación (desarrollo)

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Abrir: http://127.0.0.1:8000/login

---

## 6. Producción (notas)

- Usar IIS/Apache con `public/` como document root.
- El usuario del pool de aplicaciones debe poder ejecutar `scripts\.venv\Scripts\python.exe`.
- Programar ETL y export SAP con **Task Scheduler** o job equivalente (ver [operacion.md](operacion.md)).
- No commitear `.env` ni credenciales.
- El folder `scripts/.venv` está en `.gitignore`; recrearlo en cada servidor.

---

## Solución de problemas frecuentes

| Error | Causa | Solución |
|-------|--------|----------|
| `"python" no se reconoce…` | PATH sin Python | Usar `PYTHON_BIN` con ruta absoluta al venv |
| `ModuleNotFoundError: requests` | Paquetes no instalados en el Python que usa Laravel | `pip install -r scripts/requirements.txt` en el venv |
| `.env` invalid / whitespace | Ruta con espacios sin comillas | `PYTHON_BIN='C:/ruta/con espacios/.../python.exe'` |
| ETL timeout | Muchas páginas en Humand | Filtrar con `--policy-type-ids`, `HUMAND_ETL_STATES`, `HUMAND_ETL_CREATED_AT_SINCE` |
| SAP sin `CodigoCol` | Usuario no existe en Organigrama | Revisar mapeo `UsuarioId` ↔ correo Humand |
