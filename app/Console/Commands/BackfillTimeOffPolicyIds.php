<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillTimeOffPolicyIds extends Command
{
    protected $signature = 'timeoff:backfill-policy-ids';

    protected $description = 'Rellena policy_type_id en time_off_requests cuando Humand no lo guardó';

    public function handle(): int
    {
        $rules = [
            ['like' => '%SUPERVISOR%', 'id' => (int) config('time_off_policies.fc.supervisores.policy_type_id', 308356)],
            ['like' => '%ANTICIPO%', 'id' => (int) config('time_off_policies.dc.anticipos-vacaciones.policy_type_id', 308355)],
            ['like' => '%LEGO%', 'id' => (int) config('time_off_policies.fc.lego.policy_type_id', 172701)],
            ['like' => '%VACACIONES%DC%', 'id' => (int) config('time_off_policies.dc.vacaciones-dc.policy_type_id', 179204)],
            ['like' => '%VACACIONES%FC%', 'id' => (int) config('time_off_policies.fc.vacaciones-fc.policy_type_id', 9637)],
        ];

        $total = 0;

        foreach ($rules as $rule) {
            $updated = DB::table('time_off_requests')
                ->whereNull('policy_type_id')
                ->whereRaw('UPPER(policy_name) LIKE ?', [strtoupper($rule['like'])])
                ->update(['policy_type_id' => $rule['id']]);

            $this->line("  {$rule['like']} → {$rule['id']}: {$updated} filas");
            $total += $updated;
        }

        $this->info("Listo. {$total} filas actualizadas.");

        return self::SUCCESS;
    }
}
