<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_payments', function (Blueprint $table) {
            // Tente desta forma. Substitua 'uuid' pelo tipo correto se for diferente.
            // Se process_id for uma chave estrangeira, pode ser mais complexo.
            $table->uuid('process_id')->nullable()->change();
            // Se for foreignId, poderia ser:
            // $table->unsignedBigInteger('process_id')->nullable()->change(); // Se for bigInteger
            // Ou, se era $table->foreignId('process_id')->constrained(),
            // pode ser necessário remover a constraint, alterar, e recriar.
        });
    }

    public function down(): void
    {
        Schema::table('process_payments', function (Blueprint $table) {
            // Reverter para NOT NULL. Cuidado se houver dados nulos.
            $table->uuid('process_id')->nullable(false)->change();
        });
    }
};