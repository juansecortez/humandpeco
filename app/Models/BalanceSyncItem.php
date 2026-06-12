<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceSyncItem extends Model
{
    protected $table = 'balance_sync_items';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'run_id',
        'codigo_col',
        'employee_internal_id',
        'full_name',
        'person_type',
        'policy_type_id',
        'policy_label',
        'sap_concept',
        'sap_value',
        'target_value',
        'humand_before',
        'operation',
        'cycle_title',
        'accreditation_year',
        'status',
        'http_status',
        'message',
        'created_at',
    ];

    protected $casts = [
        'run_id'         => 'integer',
        'policy_type_id' => 'integer',
        'sap_value'      => 'float',
        'target_value'   => 'float',
        'humand_before'  => 'float',
        'http_status'    => 'integer',
        'created_at'     => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(BalanceSyncRun::class, 'run_id');
    }
}
