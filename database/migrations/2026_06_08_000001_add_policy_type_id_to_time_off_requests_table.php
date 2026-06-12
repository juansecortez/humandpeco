<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('policy_type_id')->nullable()->after('issuer_full_name');
            $table->index('policy_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            $table->dropIndex(['policy_type_id']);
            $table->dropColumn('policy_type_id');
        });
    }
};
