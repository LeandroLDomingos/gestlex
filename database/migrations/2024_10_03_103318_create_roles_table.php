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
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->integer('level');
            $table->timestamps();
        });
        
        Schema::create('role_user', function (Blueprint $table) {
            $table->uuid('role_id');
            // Defina user_id como uma foreignId normal.
            // O tipo deve corresponder ao tipo da coluna 'id' na sua tabela 'users'.
            // Se 'users.id' for bigIncrements (padrão do Laravel), use foreignId.
            // Se 'users.id' for UUID, use $table->foreignUuid('user_id').
            $table->foreignId('user_id'); // Alterado de $table->id('user_id')

            // Definir a chave primária composta
            $table->primary(['role_id', 'user_id']);

            // Definir as chaves estrangeiras
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->uuid('role_id');
            $table->uuid('permission_id'); // Assumindo que permissions.id é UUID
            
            // Definir a chave primária composta
            $table->primary(['role_id', 'permission_id']);

            // Definir as chaves estrangeiras
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permission'); // Ordem correta para drop: tabelas pivot primeiro
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
