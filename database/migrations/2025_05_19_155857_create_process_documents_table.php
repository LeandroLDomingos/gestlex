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
            $table->uuid('id')->primary(); // Chave primária da tabela process_documents

            // Chave estrangeira para o processo
            // Assumindo que 'processes.id' é UUID, esta definição está correta.
            $table->foreignUuid('process_id')->constrained('processes')->onDelete('cascade');
            // A linha acima é um atalho para:
            // $table->uuid('process_id');
            // $table->foreign('process_id')->references('id')->on('processes')->onDelete('cascade');

            // --- INÍCIO DA CORREÇÃO PARA uploader_user_id ---
            // Chave estrangeira para o usuário que fez o upload.
            // Assumindo que a tabela 'users' usa a chave primária padrão do Laravel (BIGINT).
            // Use foreignId para criar uma coluna BIGINT UNSIGNED compatível.
            $table->foreignId('uploader_user_id')->nullable()->constrained('users')->onDelete('set null');
            // --- FIM DA CORREÇÃO PARA uploader_user_id ---

            $table->string('name'); // Nome original do arquivo ou um nome descritivo
            $table->string('path'); // Caminho para o arquivo no storage (ex: 'process_documents/arquivo.pdf')
            $table->string('mime_type')->nullable(); // Ex: 'application/pdf', 'image/jpeg'
            $table->unsignedBigInteger('size')->nullable(); // Tamanho do arquivo em bytes
            $table->text('description')->nullable(); // Descrição adicional sobre o documento
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
