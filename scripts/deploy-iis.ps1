# HumandPeco - Despliegue Laravel en IIS
# Ejecutar desde PowerShell en: C:\inetpub\wwwroot\HumandPeco
# Uso: .\scripts\deploy-iis.ps1 [-SkipComposer] [-SkipMigrate]

param(
    [switch]$SkipComposer,
    [switch]$SkipMigrate
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

Write-Host "==> HumandPeco deploy en $Root" -ForegroundColor Cyan

if (-not (Test-Path ".env")) {
    Write-Error "Falta .env. Copia las variables de produccion antes de continuar."
}

if (-not $SkipComposer) {
    Write-Host "==> composer install --no-dev --optimize-autoloader"
    composer install --no-dev --optimize-autoloader
}

Write-Host "==> storage:link"
php artisan storage:link 2>$null

Write-Host "==> Limpiar y regenerar caches"
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan config:cache
php artisan view:cache

if (-not $SkipMigrate) {
    Write-Host "==> migrate --force"
    php artisan migrate --force
}

Write-Host "==> Permisos IIS (requiere admin para aplicar)"
$paths = @("storage", "bootstrap\cache")
foreach ($p in $paths) {
    Write-Host "    icacls `"$p`" /grant `"IIS_IUSRS:(OI)(CI)M`" /T"
    icacls $p /grant "IIS_IUSRS:(OI)(CI)M" /T 2>$null
}

Write-Host ""
Write-Host "Deploy de aplicacion completado." -ForegroundColor Green
Write-Host "Verifica en IIS:" -ForegroundColor Yellow
Write-Host "  - Physical path: $Root\public"
Write-Host "  - PHP FastCGI:   C:\php-8.3.7\php-cgi.exe"
Write-Host "  - URL Rewrite Module instalado"
Write-Host "  - APP_ENV=production y APP_URL en .env"
