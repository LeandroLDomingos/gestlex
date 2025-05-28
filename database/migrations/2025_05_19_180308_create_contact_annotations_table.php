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
        Schema::create('contact_annotations', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Chave primária da tabela contact_annotations

            // Chave estrangeira para o contato
            // Assumindo que 'contacts.id' é UUID, esta definição está correta.
            $table->foreignUuid('contact_id')->constrained('contacts')->onDelete('cascade');
            // A linha acima é um atalho para:
            // $table->uuid('contact_id');
            // $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            
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
        Schema::dropIfExists('contact_annotations');
    }
};
