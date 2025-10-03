<?php

return [
    'embed_url'       => env('POWERBI_EMBED_URL'),
    'title'           => env('POWERBI_TITLE', 'Power BI'),
    'height_mode'     => env('POWERBI_HEIGHT_MODE', 'ratio'), // ratio|vh|px
    'aspect_ratio'    => env('POWERBI_ASPECT_RATIO', 56.25),  // sólo ratio
    'vh'              => env('POWERBI_VH', 85),               // sólo vh
    'px'              => env('POWERBI_PX', 900),              // sólo px
    'hide_filters'    => filter_var(env('POWERBI_HIDE_FILTERS', false), FILTER_VALIDATE_BOOL),
];
