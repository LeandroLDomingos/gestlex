<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void // Adicionado :void para consistência com padrões mais recentes
    {
        Schema::create('contact_phones', function (Blueprint $table) {
            $table->bigIncrements('id'); // Chave primária da tabela contact_phones

            // --- INÍCIO DA CORREÇÃO ---
            // A coluna contact_id deve ser do tipo UUID para corresponder
            // à chave primária 'id' da tabela 'contacts'.
            $table->uuid('contact_id');
            // --- FIM DA CORREÇÃO ---

            $table->string('phone', 20);
            $table->timestamps();

            // Definição da chave estrangeira
            $table->foreign('contact_id')
                  ->references('id')->on('contacts')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void // Adicionado :void para consistência
    {
        Schema::dropIfExists('contact_phones');
    }
};
