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
        Schema::create('processes', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Chave primária da tabela processes é UUID
            $table->string('title', 255);
            $table->string('origin', 255)->nullable();
            $table->text('description')->nullable();

            // --- INÍCIO DA CORREÇÃO PARA responsible_id ---
            // Chave estrangeira para o usuário responsável.
            // Assumindo que a tabela 'users' usa a chave primária padrão do Laravel (BIGINT).
            // Use foreignId para criar uma coluna BIGINT UNSIGNED compatível.
            $table->foreignId('responsible_id')->nullable()->constrained('users')->onDelete('set null');
            // A linha acima é um atalho para:
            // $table->unsignedBigInteger('responsible_id')->nullable();
            // $table->foreign('responsible_id')
            //       ->references('id')
            //       ->on('users')
            //       ->onDelete('set null');
            // --- FIM DA CORREÇÃO PARA responsible_id ---

            // Chave estrangeira para o contato principal
            // A tabela 'contacts' usa UUID como chave primária, então 'contact_id' deve ser UUID.
            $table->uuid('contact_id');
            $table->foreign('contact_id')
                  ->references('id')
                  ->on('contacts')
                  ->onDelete('cascade'); // Se o contato for deletado, os processos associados também serão

            // Workflow e Estágio
            $table->enum('workflow', [
                'prospecting',
                'consultative',
                'administrative',
                'judicial',
                // Adicione outras chaves de workflow aqui se necessário
            ])->default('prospecting');
            
            $table->integer('stage')->nullable(); // Armazena a chave numérica do estágio

            // Novos campos
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium')->nullable();
            $table->string('status', 50)->nullable()->default('Aberto'); // Ex: Aberto, Em Andamento, Concluído, Cancelado

            $table->timestamps();
            $table->softDeletes(); // Opcional: para exclusão lógica

            // Indexes
            $table->index('workflow');
            $table->index('stage');
            $table->index('priority');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
