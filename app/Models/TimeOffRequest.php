<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeOffRequest extends Model
{
    protected $table = 'time_off_requests';
    protected $primaryKey = 'request_id';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false; // usamos los campos de la API, no los de Laravel

    protected $fillable = [
        'request_id',
        'issuer_employee_internal_id',
        'issuer_full_name',
        'policy_type_id',
        'policy_name',
        'from_date',
        'to_date',
        'amount_requested',
        'state',
        'step_state',
        'created_at',
        'resolution_date',
        'description',
        'etl_synced_at',
    ];

    protected $casts = [
        'from_date'       => 'date',
        'to_date'         => 'date',
        'created_at'      => 'datetime',
        'resolution_date' => 'datetime',
        'etl_synced_at'   => 'datetime',
        'policy_type_id'  => 'integer',
    ];

    /**
     * Solicitudes de una política (por ID Humand o nombre).
     */
    public function scopeForPolicyType($query, int $policyTypeId, string $policyName)
    {
        return $query->where(function ($q) use ($policyTypeId, $policyName) {
            $q->where('policy_type_id', $policyTypeId)
              ->orWhereRaw('UPPER(LTRIM(RTRIM(policy_name))) = ?', [strtoupper(trim($policyName))]);
        });
    }
}
