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
        Schema::create('process_history_entries', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Chave primária da tabela de histórico
            
            // Chave estrangeira para o processo
            // Assumindo que 'processes.id' é UUID, esta definição está correta.
            $table->foreignUuid('process_id')
                  ->constrained('processes') // Assumes your processes table is named 'processes'
                  ->onDelete('cascade');   // If a process is deleted, its history is also deleted

            // --- INÍCIO DA CORREÇÃO PARA user_id ---
            // Chave estrangeira para o usuário que realizou a ação.
            // Assumindo que a tabela 'users' usa a chave primária padrão do Laravel (BIGINT).
            // Use foreignId para criar uma coluna BIGINT UNSIGNED compatível.
            $table->foreignId('user_id')
                  ->nullable()             // System actions might not have a specific user
                  ->constrained('users')   // Assumes your users table is named 'users'
                  ->onDelete('set null'); // If a user is deleted, keep the history entry but nullify the user
            // --- FIM DA CORREÇÃO PARA user_id ---

            $table->string('action', 100);   // Ex: "Estágio Alterado", "Prioridade Definida", "Caso Criado"
            $table->text('description');     // Ex: "Estágio alterado de 'Contato Inicial' para 'Coleta Documental' por Admin."
            $table->text('old_value')->nullable(); // Valor antigo (pode ser JSON ou string simples) - alterado para text para mais flexibilidade
            $table->text('new_value')->nullable(); // Novo valor (pode ser JSON ou string simples) - alterado para text para mais flexibilidade
            
            $table->timestamp('created_at')->useCurrent();
            // Não precisamos de updated_at para entradas de histórico, geralmente são imutáveis.
            // $table->timestamps(); 

            // Índices para otimizar consultas
            $table->index('process_id');
            $table->index('user_id');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_history_entries');
    }
};
