<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SapDcPagoVacExport extends Model
{
    protected $table = 'sap_dc_pago_vac_exports';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'processed_state',
        'issuer_employee_internal_id',
        'issuer_full_name',
        'codigo_col',
        'policy_type_id',
        'policy_name',
        'opcion',
        'source',
        'fecha_inicio',
        'request_url',
        'response_status',
        'response_ok',
        'response_text',
        'created_at',
        'responded_at',
    ];

    protected $casts = [
        'request_id'      => 'integer',
        'policy_type_id'  => 'integer',
        'fecha_inicio'    => 'date',
        'response_status' => 'integer',
        'response_ok'     => 'boolean',
        'created_at'      => 'datetime',
        'responded_at'    => 'datetime',
    ];
}
