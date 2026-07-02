@echo off
REM Ejecutar cada minuto desde el Programador de tareas de Windows.
cd /d "%~dp0.."
php artisan schedule:run >> storage\logs\schedule-run.log 2>&1
