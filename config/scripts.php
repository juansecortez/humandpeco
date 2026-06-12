<?php

return [
    /*
    | Python para scripts ETL / SAP (ruta absoluta recomendada en Windows).
    | Por defecto usa el venv del proyecto: scripts/.venv
    */
    'python_bin' => env('PYTHON_BIN', base_path('scripts/.venv/Scripts/python.exe')),

    /** Segundos máximos para ETL (PHP + subprocess). El ETL puede tardar varios minutos. */
    'etl_timeout' => (int) env('ETL_TIMEOUT', 600),

    /** Meses hacia atrás para createdAtSince si no hay HUMAND_ETL_CREATED_AT_SINCE fija. */
    'etl_lookback_months' => (int) env('HUMAND_ETL_LOOKBACK_MONTHS', 2),
];
