<?php

/**
 * Sincronización de saldos de vacaciones SAP -> Humand.
 *
 * Fuente de verdad de QUIÉN: Organigrama.dbo.OrganigramaCompleto
 * Fuente de verdad de CUÁNTO: SAP (zws_dias_vac)
 * Destino del ajuste: Humand (POST /time-off/policy-types/{id}/balances/correction)
 */
return [
    // Endpoint SAP que devuelve los días por concepto (vacaciones, anticipo, convenio, lego)
    'sap_balance_url' => env('SAP_BALANCE_URL', 'https://qasci01.pc.cmbjpc.com.mx:1443/sap/bc/zws_dias_vac'),

    // sap-client (reutiliza el del export si no se define uno propio)
    'sap_client' => env('SAP_BALANCE_CLIENT', env('SAP_CLIENT', '300')),

    // Credenciales del endpoint de saldos (QAS); caen al usuario del export si no se definen
    'sap_user' => env('SAP_BALANCE_USER', env('SAP_USER')),
    'sap_pass' => env('SAP_BALANCE_PASS', env('SAP_PASS')),

    // Fecha enviada a SAP (YYYY-MM-DD). Vacío = hoy.
    'sap_fecha' => env('BALANCE_SYNC_SAP_DATE') ?: null,
    'tolerance' => (float) env('BALANCE_SYNC_TOLERANCE', 0.01),

    // Por seguridad, por defecto solo simula (no escribe en Humand)
    'default_dry_run' => filter_var(env('BALANCE_SYNC_DRY_RUN', true), FILTER_VALIDATE_BOOLEAN),

    // Cuántos usuarios procesar por lote (logs/commits)
    'batch_size' => (int) env('BALANCE_SYNC_BATCH', 50),

    // Empleados FC de subtipo "Supervisor" se detectan por AreaPersonal
    'supervisor_area_personal' => [2, 5],

    // IDs de policyType en Humand
    'policies' => [
        'vacaciones_fc' => (int) env('HUMAND_POLICY_VACACIONES_FC_ID', 9637),
        'vacaciones_dc' => (int) env('HUMAND_POLICY_VACACIONES_DC_ID', 179204),
        'lego'          => (int) env('HUMAND_POLICY_LEGO_ID', 172701),
        'anticipos'     => (int) env('HUMAND_POLICY_ANTICIPOS_ID', 308355),
        'supervisores'  => (int) env('HUMAND_POLICY_SUPERVISORES_ID', 308356),
    ],

    // Etiquetas legibles por policyType id (para el log)
    'policy_labels' => [
        9637   => 'Vacaciones FC',
        179204 => 'Vacaciones DC',
        172701 => 'LEGO',
        308355 => 'Anticipos de vacaciones',
        308356 => 'Supervisores',
    ],
];
