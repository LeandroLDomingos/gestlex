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
            // Torna value_of_installment anulável
            // O tipo 'decimal' e a precisão (ex: 8, 2) devem corresponder à sua definição original
            $table->decimal('value_of_installment', 8, 2)->nullable()->change();

            // Torna number_of_installments anulável
            // O tipo 'integer' deve corresponder à sua definição original
            $table->integer('number_of_installments')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_payments', function (Blueprint $table) {
            // Reverte para não nulo (CUIDADO: pode falhar se houver dados nulos)
            // Ajuste conforme necessário se o tipo original ou precisão eram diferentes
            $table->decimal('value_of_installment', 8, 2)->nullable(false)->change();
            $table->integer('number_of_installments')->nullable(false)->change();
        });
    }
};