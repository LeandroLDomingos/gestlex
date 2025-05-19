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
            $table->uuid('id')->primary(); // Ou $table->id(); se preferir IDs auto-incrementais
            
            // Chave estrangeira para o processo
            $table->uuid('process_id');
            $table->foreign('process_id')->references('id')->on('processes')->onDelete('cascade');
            
            // Chave estrangeira para o usuário que criou a anotação (opcional, mas recomendado)
            // Assumindo que sua tabela 'users' usa UUIDs para 'id'. Se for BIGINT, use foreignId.
            $table->uuid('user_id')->nullable();
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
        Schema::dropIfExists('process_annotations');
    }
};
