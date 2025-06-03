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
            // Adicione a coluna transaction_nature.
            // Pode ser depois de uma coluna existente, por exemplo, 'status'
            // Ajuste o tipo e se é nullable conforme sua necessidade.
            // Se você estiver usando os valores do Enum TransactionNature ('income', 'expense'),
            // uma string é apropriada.
            $table->string('transaction_nature')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_payments', function (Blueprint $table) {
            $table->dropColumn('transaction_nature');
        });
    }
};