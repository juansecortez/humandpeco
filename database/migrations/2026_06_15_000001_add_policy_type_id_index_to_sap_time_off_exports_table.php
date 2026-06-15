<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sap_time_off_exports', 'policy_type_id')) {
            Schema::table('sap_time_off_exports', function (Blueprint $table) {
                $table->unsignedBigInteger('policy_type_id')->nullable()->after('policy_name');
            });
        }

        $indexes = [
            'ix_sap_exports_policy_type_id' => 'policy_type_id',
            'ix_sap_exports_policy_name'    => 'policy_name',
            'ix_sap_exports_created_at'     => 'created_at',
        ];

        foreach ($indexes as $name => $column) {
            DB::statement("
                IF NOT EXISTS (
                    SELECT 1 FROM sys.indexes
                    WHERE name = N'{$name}'
                      AND object_id = OBJECT_ID(N'dbo.sap_time_off_exports')
                )
                CREATE INDEX [{$name}] ON [dbo].[sap_time_off_exports] ([{$column}]);
            ");
        }

        if (Schema::hasColumn('sap_time_off_exports', 'policy_type_id')) {
            DB::statement('
                UPDATE e
                SET e.policy_type_id = r.policy_type_id
                FROM dbo.sap_time_off_exports e
                INNER JOIN dbo.time_off_requests r ON r.request_id = e.request_id
                WHERE e.policy_type_id IS NULL AND r.policy_type_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        foreach ([
            'ix_sap_exports_policy_type_id',
            'ix_sap_exports_policy_name',
            'ix_sap_exports_created_at',
        ] as $name) {
            DB::statement("
                IF EXISTS (
                    SELECT 1 FROM sys.indexes
                    WHERE name = N'{$name}'
                      AND object_id = OBJECT_ID(N'dbo.sap_time_off_exports')
                )
                DROP INDEX [{$name}] ON [dbo].[sap_time_off_exports];
            ");
        }

        if (Schema::hasColumn('sap_time_off_exports', 'policy_type_id')) {
            Schema::table('sap_time_off_exports', function (Blueprint $table) {
                $table->dropColumn('policy_type_id');
            });
        }
    }
};
