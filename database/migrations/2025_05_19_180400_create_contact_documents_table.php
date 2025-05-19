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
        Schema::create('contact_documents', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Ou $table->id();

            $table->uuid('contact_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');

            $table->uuid('uploader_user_id')->nullable(); // Ou o tipo de ID do seu usuário
            $table->foreign('uploader_user_id')->references('id')->on('users')->onDelete('set null');

            $table->string('name'); // Nome original do arquivo ou um nome descritivo
            $table->string('path'); // Caminho para o arquivo no storage (ex: 'contact_documents/arquivo.pdf')
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
        Schema::dropIfExists('contact_documents');
    }
};
