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
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->string('origin', 255)->nullable();
            $table->text('description')->nullable();

            // Chave estrangeira para o usuário responsável
            $table->uuid('responsible_id')->nullable(); // Pode ser nulo se um processo puder não ter responsável inicialmente
            $table->foreign('responsible_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null'); // Ou 'cascade' se preferir deletar processos se o usuário for deletado, ou 'restrict'

            // Chave estrangeira para o contato principal
            $table->uuid('contact_id'); // Assumindo que um processo sempre tem um contato principal
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
            ])->default('prospecting'); // Ou o workflow padrão que fizer mais sentido
            
            $table->integer('stage')->nullable(); // Armazena a chave numérica do estágio

            // Novos campos
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium')->nullable();
            $table->string('status', 50)->nullable()->default('Aberto'); // Ex: Aberto, Em Andamento, Concluído, Cancelado
            $table->date('due_date')->nullable(); // Data de vencimento do processo

            $table->timestamps();
            $table->softDeletes(); // Opcional: para exclusão lógica

            // Indexes
            $table->index('workflow');
            $table->index('stage');
            $table->index('priority');
            $table->index('status');
            $table->index('due_date');
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
