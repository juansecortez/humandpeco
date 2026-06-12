<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_sync_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('run_id')->index();

            // Identidad del empleado
            $table->string('codigo_col', 30)->nullable();
            $table->string('employee_internal_id', 320)->nullable();
            $table->string('full_name', 200)->nullable();
            $table->string('person_type', 20)->nullable();   // fc | dc | supervisor

            // Política / concepto
            $table->unsignedBigInteger('policy_type_id')->nullable();
            $table->string('policy_label', 100)->nullable();
            $table->string('sap_concept', 40)->nullable();   // vacaciones | lego | anticipo | vacaciones+convenio

            // Valores
            $table->decimal('sap_value', 9, 2)->nullable();        // valor crudo SAP (sumado si aplica)
            $table->decimal('target_value', 9, 2)->nullable();     // valor redondeado enviado a Humand
            $table->decimal('humand_before', 9, 2)->nullable();    // currentBalance antes del ajuste
            $table->string('operation', 20)->nullable();           // SET
            $table->string('cycle_title', 40)->nullable();
            $table->string('accreditation_year', 10)->nullable();

            // Resultado
            $table->string('status', 20)->index();           // applied | unchanged | skipped | error | simulated
            $table->integer('http_status')->nullable();
            $table->text('message')->nullable();

            $table->dateTimeTz('created_at')->useCurrent();

            $table->index(['codigo_col']);
            $table->index(['employee_internal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_sync_items');
    }
};
