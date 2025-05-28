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
        Schema::create('tags_process', function (Blueprint $table) {
            $table->bigIncrements('id'); // Chave primária da tabela tags_process

            // Chave estrangeira para processes.
            // A tabela 'processes' usa UUID como chave primária.
            $table->uuid('process_id'); // Correto, pois processes.id é UUID

            $table->string('name'); // Nome da tag
            $table->timestamps();

            // Definição da chave estrangeira
            $table->foreign('process_id')
                  ->references('id')->on('processes')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags_process');
    }
};
