<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('process_payments', function (Blueprint $table) {
            $table->uuid('transaction_group_id')->nullable()->after('id'); // Ou onde preferir
            $table->index('transaction_group_id'); // Adicionar um índice pode ser útil
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_payments', function (Blueprint $table) {
            $table->dropIndex(['transaction_group_id']); // Se adicionou o índice
            $table->dropColumn('transaction_group_id');
        });
    }
};
