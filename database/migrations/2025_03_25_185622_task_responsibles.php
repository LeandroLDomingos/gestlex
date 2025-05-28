<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void // Adicionado :void para consistência
    {
        Schema::create('task_responsibles', function (Blueprint $table) {
            $table->bigIncrements('id'); // Chave primária da tabela pivot

            // --- INÍCIO DA CORREÇÃO PARA task_id ---
            // Chave estrangeira para tasks.
            // A tabela 'tasks' usa UUID como chave primária.
            $table->foreignUuid('task_id');
            // --- FIM DA CORREÇÃO PARA task_id ---

            // --- INÍCIO DA CORREÇÃO PARA user_id ---
            // Chave estrangeira para users.
            // Assumindo que a tabela 'users' usa a chave primária padrão do Laravel (BIGINT).
            $table->foreignId('user_id');
            // --- FIM DA CORREÇÃO PARA user_id ---

            $table->timestamps();

            // Definição das chaves estrangeiras
            $table->foreign('task_id')
                  ->references('id')->on('tasks')
                  ->onDelete('cascade');

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            // Adicionar uma chave primária composta pode ser útil para tabelas pivot
            // para garantir que um usuário não seja atribuído à mesma tarefa múltiplas vezes.
            // $table->primary(['task_id', 'user_id']);
            // Descomente a linha acima se desejar essa restrição.
            // Se descomentar, remova $table->bigIncrements('id'); ou ajuste conforme necessário.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void // Adicionado :void para consistência
    {
        Schema::dropIfExists('task_responsibles');
    }
};
