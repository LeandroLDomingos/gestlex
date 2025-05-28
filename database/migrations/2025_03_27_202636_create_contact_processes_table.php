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
        Schema::create('contact_processes', function (Blueprint $table) {
            $table->bigIncrements('id'); // Chave primária da tabela pivot

            // Chave estrangeira para processes.
            // A tabela 'processes' usa UUID como chave primária.
            $table->foreignUuid('process_id'); // Correto, pois processes.id é UUID

            // --- INÍCIO DA CORREÇÃO PARA contact_id ---
            // Chave estrangeira para contacts.
            // A tabela 'contacts' usa UUID como chave primária.
            $table->foreignUuid('contact_id');
            // --- FIM DA CORREÇÃO PARA contact_id ---

            $table->timestamps();

            // Definição das chaves estrangeiras
            $table->foreign('process_id')
                  ->references('id')->on('processes')
                  ->onDelete('cascade');

            $table->foreign('contact_id')
                  ->references('id')->on('contacts')
                  ->onDelete('cascade');

            // Adicionar uma chave primária composta pode ser útil para tabelas pivot
            // para garantir que um contato não seja associado ao mesmo processo múltiplas vezes.
            // $table->primary(['process_id', 'contact_id']);
            // Descomente a linha acima se desejar essa restrição.
            // Se descomentar, remova $table->bigIncrements('id'); ou ajuste conforme necessário.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_processes');
    }
};
