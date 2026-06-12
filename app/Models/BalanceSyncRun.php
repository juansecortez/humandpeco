<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceSyncRun extends Model
{
    protected $table = 'balance_sync_runs';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'dry_run',
        'status',
        'triggered_by',
        'scope',
        'total_users',
        'total_items',
        'applied',
        'unchanged',
        'skipped',
        'errors',
        'note',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'dry_run'     => 'boolean',
        'total_users' => 'integer',
        'total_items' => 'integer',
        'applied'     => 'integer',
        'unchanged'   => 'integer',
        'skipped'     => 'integer',
        'errors'      => 'integer',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(BalanceSyncItem::class, 'run_id');
    }
}
