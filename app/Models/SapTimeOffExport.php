<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

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
     * Filtra exportaciones por política (nombre normalizado, LIKE o policy_type_id si existe la columna).
     *
     * @param  array<int>  $policyTypeIds
     * @param  array<string>  $policyNames
     * @param  array<string>  $nameLike  Patrones LIKE (%Anticipo%, etc.)
     */
    public function scopeForPolicies($query, array $policyTypeIds, array $policyNames = [], array $nameLike = [])
    {
        $policyTypeIds = array_values(array_filter(array_map('intval', $policyTypeIds)));
        $policyNames   = array_values(array_filter(array_map('trim', $policyNames)));
        $nameLike      = array_values(array_filter(array_map('trim', $nameLike)));

        static $hasPolicyTypeId = null;
        if ($hasPolicyTypeId === null) {
            $hasPolicyTypeId = Schema::hasColumn($this->getTable(), 'policy_type_id');
        }

        return $query->where(function ($q) use ($policyTypeIds, $policyNames, $nameLike, $hasPolicyTypeId) {
            $first = true;

            if ($hasPolicyTypeId && $policyTypeIds !== []) {
                $q->whereIn('policy_type_id', $policyTypeIds);
                $first = false;
            }

            foreach ($policyNames as $name) {
                $norm = strtoupper(trim($name));
                $first
                    ? $q->whereRaw('UPPER(LTRIM(RTRIM(policy_name))) = ?', [$norm])
                    : $q->orWhereRaw('UPPER(LTRIM(RTRIM(policy_name))) = ?', [$norm]);
                $first = false;
            }

            foreach ($nameLike as $pattern) {
                $pat = strtoupper($pattern);
                $first
                    ? $q->whereRaw('UPPER(policy_name) LIKE ?', [$pat])
                    : $q->orWhereRaw('UPPER(policy_name) LIKE ?', [$pat]);
                $first = false;
            }
        });
    }
}
