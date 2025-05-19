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
        Schema::create('process_documents', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Ou $table->id(); se preferir IDs auto-incrementais

            $table->uuid('process_id');
            $table->foreign('process_id')->references('id')->on('processes')->onDelete('cascade');

            // user_id para quem fez o upload do documento (opcional)
            // Se users.id for BIGINT, use $table->foreignId('uploader_user_id')
            $table->uuid('uploader_user_id')->nullable();
            $table->foreign('uploader_user_id')->references('id')->on('users')->onDelete('set null');

            $table->string('name'); // Nome original do arquivo ou um nome descritivo
            $table->string('path'); // Caminho para o arquivo no storage
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable(); // Tamanho do arquivo em bytes
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_documents');
    }
};
