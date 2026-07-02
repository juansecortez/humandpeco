<?php

/**
 * Tareas programadas de integración Humand ↔ SAP.
 * Requiere en el servidor: php artisan schedule:run cada minuto (Task Scheduler).
 */
return [
    'enabled' => filter_var(env('SCHEDULE_INTEGRATIONS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    // Horarios diarios (HH:MM, 24h). Por defecto: 06:00 y 18:00.
    'times' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('SCHEDULE_INTEGRATIONS_TIMES', '06:00,18:00'))
    ))),

    // Zona horaria de los horarios anteriores (vacío = app.timezone).
    'timezone' => env('SCHEDULE_INTEGRATIONS_TIMEZONE') ?: null,

    // Sincronización de saldos: true = aplicar ajustes en Humand; false = solo simular.
    'balance_sync_apply' => filter_var(env('SCHEDULE_BALANCE_SYNC_APPLY', true), FILTER_VALIDATE_BOOLEAN),

    // Scopes de export_time_off_to_sap.py (uno por política FC + anticipos DC).
    'sap_export_scopes' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('SCHEDULE_SAP_EXPORT_SCOPES', 'vacaciones-fc,lego,supervisores,anticipos'))
    ))),

    // Minutos máximos sin solapar el ciclo completo.
    'overlap_lock_minutes' => (int) env('SCHEDULE_OVERLAP_LOCK_MINUTES', 360),
];
