<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void // Adicionado :void para consistência
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Chave primária da tabela tasks é UUID

            // Chave estrangeira para processes.
            // Assumindo que 'processes.id' é UUID, foreignUuid está correto.
            $table->foreignUuid('process_id')->constrained('processes')->onDelete('cascade');

            // --- INÍCIO DA CORREÇÃO PARA responsible_user_id ---
            // Chave estrangeira para users.
            // Assumindo que a tabela 'users' usa a chave primária padrão do Laravel (BIGINT).
            // Use foreignId para criar uma coluna BIGINT UNSIGNED compatível.
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->onDelete('set null');
            // --- FIM DA CORREÇÃO PARA responsible_user_id ---

            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('Pendente'); // Ex: Pendente, Em Andamento, Concluída
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void // Adicionado :void para consistência
    {
        Schema::dropIfExists('tasks');
    }
};
