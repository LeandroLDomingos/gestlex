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
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });
        
        Schema::create('permission_user', function (Blueprint $table) {
            $table->uuid('permission_id');
            // Defina user_id como uma foreignId normal, sem ser a chave primária por si só.
            // O tipo deve corresponder ao tipo da coluna 'id' na sua tabela 'users'.
            // Se 'users.id' for bigIncrements (padrão do Laravel), use foreignId.
            // Se 'users.id' for UUID, use $table->foreignUuid('user_id').
            $table->foreignId('user_id'); // Alterado de $table->id('user_id')

            // Definir a chave primária composta
            $table->primary(['permission_id', 'user_id']);

            // Definir as chaves estrangeiras
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permissions');
    }
};
