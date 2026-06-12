<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_sync_runs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->boolean('dry_run')->default(true);
            $table->string('status', 20)->default('running'); // running | done | failed
            $table->string('triggered_by', 120)->nullable();  // usuario que la disparó
            $table->string('scope', 120)->nullable();          // 'all' | 'codigo:8021'

            $table->integer('total_users')->default(0);
            $table->integer('total_items')->default(0);
            $table->integer('applied')->default(0);
            $table->integer('unchanged')->default(0);
            $table->integer('skipped')->default(0);
            $table->integer('errors')->default(0);

            $table->text('note')->nullable();

            $table->dateTimeTz('started_at')->useCurrent();
            $table->dateTimeTz('finished_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_sync_runs');
    }
};
