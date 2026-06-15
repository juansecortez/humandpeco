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
        'policy_type_id',
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
        'from_date'       => 'date',
        'to_date'         => 'date',
        'dias'            => 'integer',
        'policy_type_id'  => 'integer',
        'response_ok'     => 'boolean',
        'response_status' => 'integer',
        'created_at'      => 'datetime',
        'responded_at'    => 'datetime',
    ];

    /**
     * Filtra por policy_type_id y/o nombres exactos (sin UPPER() → usa índices).
     *
     * @param  array<int>  $policyTypeIds
     * @param  array<string>  $policyNames
     */
    public function scopeForPolicies($query, array $policyTypeIds, array $policyNames = [], array $nameLike = [])
    {
        $policyTypeIds = array_values(array_filter(array_map('intval', $policyTypeIds)));
        $policyNames   = array_values(array_filter(array_map('trim', $policyNames)));
        $nameLike      = array_values(array_filter(array_map('trim', $nameLike)));

        return $query->where(function ($q) use ($policyTypeIds, $policyNames, $nameLike) {
            $applied = false;
            if ($policyTypeIds !== []) {
                $q->whereIn('policy_type_id', $policyTypeIds);
                $applied = true;
            }
            if ($policyNames !== []) {
                $applied ? $q->orWhereIn('policy_name', $policyNames)
                         : $q->whereIn('policy_name', $policyNames);
                $applied = true;
            }
            foreach ($nameLike as $pattern) {
                $applied ? $q->orWhere('policy_name', 'like', $pattern)
                         : $q->where('policy_name', 'like', $pattern);
                $applied = true;
            }
        });
    }
}
