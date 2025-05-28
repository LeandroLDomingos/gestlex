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
        Schema::create('process_annotations', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Chave primária da tabela process_annotations

            // Chave estrangeira para o processo
            // Assumindo que 'processes.id' é UUID, esta definição está correta.
            $table->foreignUuid('process_id')->constrained('processes')->onDelete('cascade');
            // A linha acima é um atalho para:
            // $table->uuid('process_id');
            // $table->foreign('process_id')->references('id')->on('processes')->onDelete('cascade');

            // --- INÍCIO DA CORREÇÃO PARA user_id ---
            // Chave estrangeira para o usuário que criou a anotação.
            // Assumindo que a tabela 'users' usa a chave primária padrão do Laravel (BIGINT).
            // Use foreignId para criar uma coluna BIGINT UNSIGNED compatível.
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            // --- FIM DA CORREÇÃO PARA user_id ---

            $table->text('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_annotations');
    }
};
