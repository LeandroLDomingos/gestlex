<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * IMPORTANTE: Para usar o método ->change(), o pacote doctrine/dbal deve ser instalado.
     * Execute: composer require doctrine/dbal
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // 1. Torna a coluna 'process_id' existente nulável.
            // O tipo (uuid) e o nome ('process_id') já existem, então apenas adicionamos ->nullable()->change().
            $table->uuid('process_id')->nullable()->change();

            // 2. Adiciona a nova coluna 'contact_id' com sua chave estrangeira.
            // Esta linha está correta e usa as convenções modernas do Laravel.
            $table->foreignUuid('contact_id')
                  ->nullable()
                  ->after('process_id')
                  ->constrained('contacts')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // 1. Remove a coluna 'contact_id' e sua chave estrangeira.
            // A ordem é importante: primeiro a restrição (foreign key), depois a coluna.
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');

            // 2. Reverte a coluna 'process_id' para não-nulável.
            // AVISO: Esta operação falhará se houver tarefas com 'process_id' nulo no banco de dados
            // no momento em que você tentar reverter a migração.
            $table->uuid('process_id')->nullable(false)->change();
        });
    }
};
