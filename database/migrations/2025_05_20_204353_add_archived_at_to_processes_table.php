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
        Schema::table('processes', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('updated_at'); // Adiciona a coluna após 'updated_at'
            $table->index('archived_at'); // Adiciona um índice para performance em filtros
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->dropIndex(['archived_at']); // Remove o índice primeiro
            $table->dropColumn('archived_at');
        });
    }
};