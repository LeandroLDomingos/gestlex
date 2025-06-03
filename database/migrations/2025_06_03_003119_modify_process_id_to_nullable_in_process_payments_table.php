<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('process_payments', function (Blueprint $table) {
            // Altera a coluna process_id para permitir nulos
            // Certifique-se de que o tipo da coluna (uuid, foreignId, etc.) corresponde ao que você tem
            $table->uuid('process_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_payments', function (Blueprint $table) {
            // Reverte para não nulo (CUIDADO: isso pode falhar se houver dados nulos)
            // É importante definir o comportamento de reversão de acordo com a sua necessidade.
            $table->uuid('process_id')->nullable(false)->change();
        });
    }
}; // <-- Add the semicolon here