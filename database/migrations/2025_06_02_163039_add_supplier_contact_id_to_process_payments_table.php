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
            // No método up() da migração
            $table->foreignUuid('supplier_contact_id')->nullable()->constrained('contacts')->onDelete('set null'); // Ou o nome da sua tabela de contatos
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_payments', function (Blueprint $table) {
            $table->dropColumn('supplier_contact_id');
        });
    }
};
