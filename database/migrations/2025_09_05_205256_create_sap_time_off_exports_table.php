<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sap_time_off_exports', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->bigInteger('request_id');                         // time_off_requests.request_id
            $table->string('processed_state', 50);                    // APPROVED | CANCELLED

            $table->string('issuer_employee_internal_id', 320);       // correo
            $table->string('usuario_id', 320)->nullable();            // parte antes de '@'
            $table->string('codigo_col', 20)->nullable();             // Organigrama.CodigoCol

            $table->string('policy_name', 100);                       // 'Vacaciones' | 'LEGO'
            $table->string('clave', 10);                               // 6072 / 6073
            $table->string('infotipo', 10)->default('2001');

            $table->date('from_date');
            $table->date('to_date');
            $table->integer('dias');                                   // amount_requested

            $table->string('request_url', 512)->nullable();           // URL que se llamó (sin credenciales)
            $table->integer('response_status')->nullable();           // HTTP status
            $table->boolean('response_ok')->nullable();               // true si 2xx
            $table->text('response_text')->nullable();                // cuerpo/respuesta o error

            $table->dateTimeTz('created_at')->useCurrent();           // cuándo registramos el envío
            $table->dateTimeTz('responded_at')->nullable();           // cuándo respondió SAP

            // ÚNICO necesario para no duplicar el mismo request/estado
            $table->unique(['request_id', 'processed_state'], 'ux_sap_exports_req_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sap_time_off_exports');
    }
};
