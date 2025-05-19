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
            $table->uuid('id')->primary(); // Ou $table->id(); se preferir IDs auto-incrementais

            // Chave estrangeira para o contato
            // Assumindo que sua tabela 'contacts' usa UUIDs para 'id'.
            // Se 'contacts.id' for BIGINT, use $table->foreignId('contact_id')
            $table->uuid('contact_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            
            // Chave estrangeira para o usuário que criou a anotação (opcional)
            // Assumindo que sua tabela 'users' usa UUIDs para 'id'. Se for BIGINT, use foreignId.
            $table->uuid('user_id')->nullable(); // Ou o tipo de ID do seu usuário
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
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
