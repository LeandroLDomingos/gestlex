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
            // Torna down_payment_amount anulável
            // O tipo 'decimal' e a precisão (ex: 8, 2) devem corresponder à sua definição original
            // Se o seu campo for, por exemplo, decimal(10, 2), use isso.
            $table->decimal('down_payment_amount', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_payments', function (Blueprint $table) {
            // Reverte para não nulo (CUIDADO: pode falhar se houver dados nulos)
            // Ajuste o tipo e precisão conforme a definição original
            $table->decimal('down_payment_amount', 10, 2)->nullable(false)->change();
        });
    }
};