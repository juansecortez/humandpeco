<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_off_requests', function (Blueprint $table) {
            $table->bigInteger('request_id')->primary();       // items.id único

            // Empleado
            $table->string('issuer_employee_internal_id');     // items.issuer.employeeInternalId (correo interno)

            // Política
            $table->string('policy_name');                     // items.policyType.name

            // Rango y cantidad
            $table->date('from_date');                         // items.from.date
            $table->date('to_date');                           // items.to.date
            $table->integer('amount_requested');               // items.amountRequested

            // Estado
            $table->string('state');                           // APPROVED | IN_PROGRESS | CANCELLED
            $table->string('step_state')->nullable();          // PENDING | APPROVED

            // Metadatos
            $table->dateTimeTz('created_at');                  // items.createdAt
            $table->dateTimeTz('resolution_date')->nullable(); // items.resolutionDate
            $table->text('description')->nullable();           // items.description

            // Control ETL
            $table->dateTimeTz('etl_synced_at')->useCurrent(); // marca de sincronización
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_off_requests');
    }
};
