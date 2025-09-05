<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SapTimeOffExport extends Model
{
    protected $table = 'sap_time_off_exports';

    // PK autoincremental por defecto: id
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // manejamos created_at/responded_at manualmente

    protected $fillable = [
        'request_id',
        'processed_state',
        'issuer_employee_internal_id',
        'usuario_id',
        'codigo_col',
        'policy_name',
        'clave',
        'infotipo',
        'from_date',
        'to_date',
        'dias',
        'request_url',
        'response_status',
        'response_ok',
        'response_text',
        'created_at',
        'responded_at',
    ];

    protected $casts = [
        'from_date'     => 'date',
        'to_date'       => 'date',
        'dias'          => 'integer',
        'response_ok'   => 'boolean',
        'response_status' => 'integer',
        'created_at'    => 'datetime',
        'responded_at'  => 'datetime',
    ];
}
