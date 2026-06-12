<?php

/**
 * Políticas Humand agrupadas por convención.
 * - fc: ETL Humand actual (HumandTimeOffEtlService) + export SAP
 * - dc: ETL Humand + export SAP vía zws_pago_vac (opción 1/2/3 en description)
 */
return [
    'fc' => [
        'lego' => [
            'label'          => 'LEGO',
            'policy_type_id' => (int) env('HUMAND_POLICY_LEGO_ID', 172701),
            'policy_name'    => 'LEGO',
            'active_page'    => 'solicitudes-fc-lego',
        ],
        'vacaciones-fc' => [
            'label'          => 'Vacaciones FC',
            'policy_type_id' => (int) env('HUMAND_POLICY_VACACIONES_FC_ID', 9637),
            'policy_name'    => 'VACACIONES FC',
            'active_page'    => 'solicitudes-fc-vacaciones',
        ],
        'supervisores' => [
            'label'          => 'Supervisores',
            'policy_type_id' => (int) env('HUMAND_POLICY_SUPERVISORES_ID', 308356),
            'policy_name'    => 'SUPERVISORES',
            'active_page'    => 'solicitudes-fc-supervisores',
        ],
    ],
    'dc' => [
        'anticipos-vacaciones' => [
            'label'          => 'Anticipos de vacaciones',
            'policy_type_id' => (int) env('HUMAND_POLICY_ANTICIPOS_ID', 308355),
            'policy_name'    => 'ANTICIPOS DE VACACIONES',
            'active_page'    => 'solicitudes-dc-anticipos',
            // Mismo envío SAP que vacaciones FC: fecha inicio/fin + días (zws estándar)
            'sap_export'     => 'date_range',
        ],
        'vacaciones-dc' => [
            'label'          => 'Vacaciones DC',
            'policy_type_id' => (int) env('HUMAND_POLICY_VACACIONES_DC_ID', 179204),
            'policy_name'    => 'VACACIONES DC',
            'active_page'    => 'solicitudes-dc-vacaciones',
            // Pago vacacional DC: opción 1/2/3 en description → zws_pago_vac
            'sap_export'     => 'pago_vac',
        ],
    ],
];
