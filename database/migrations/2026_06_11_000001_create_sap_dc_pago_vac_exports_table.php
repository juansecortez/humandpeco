<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sap_dc_pago_vac_exports', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->bigInteger('request_id');
            $table->string('processed_state', 50)->default('APPROVED');

            $table->string('issuer_employee_internal_id', 320)->nullable();
            $table->string('issuer_full_name', 200)->nullable();
            $table->string('codigo_col', 20)->nullable();

            $table->unsignedBigInteger('policy_type_id')->nullable();
            $table->string('policy_name', 100)->nullable();

            $table->string('opcion', 1);
            $table->string('source', 20)->default('auto'); // auto | manual
            $table->date('fecha_inicio');

            $table->string('request_url', 512)->nullable();
            $table->integer('response_status')->nullable();
            $table->boolean('response_ok')->nullable();
            $table->text('response_text')->nullable();

            $table->dateTimeTz('created_at')->useCurrent();
            $table->dateTimeTz('responded_at')->nullable();

            $table->unique(['request_id', 'processed_state'], 'ux_sap_dc_exports_req_state');
            $table->index('codigo_col');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sap_dc_pago_vac_exports');
    }
};
