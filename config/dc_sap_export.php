<?php

/**
 * Exportación SAP de Vacaciones DC (pago vacacional).
 * Endpoint: POST zws_pago_vac
 * Anticipos DC (308355) van por export_time_off_to_sap.py (fecha inicio/fin).
 */
return [
    'url'    => env('SAP_DC_PAGO_VAC_URL', 'http://proci01.pc.cmbjpc.com.mx:8000/sap/bc/zws_pago_vac'),
    'client' => env('SAP_DC_CLIENT', env('SAP_BALANCE_CLIENT', '300')),
    'user'   => env('SAP_DC_USER', env('SAP_BALANCE_USER', env('SAP_USER'))),
    'pass'   => env('SAP_DC_PASS', env('SAP_BALANCE_PASS', env('SAP_PASS'))),

    'process_states' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('DC_SAP_PROCESS_STATES', 'APPROVED'))
    ))),

    'policy_type_ids' => [
        (int) env('HUMAND_POLICY_VACACIONES_DC_ID', 179204),
    ],

    'valid_opciones' => ['1', '2', '3'],

    'opcion_labels' => [
        '1' => 'Prima vacacional, días de vacaciones y días trabajados anticipados',
        '2' => 'Prima vacacional y días de vacaciones',
        '3' => 'Prima vacacional',
    ],
];
