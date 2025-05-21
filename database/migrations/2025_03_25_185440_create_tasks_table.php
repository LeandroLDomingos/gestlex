<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Ou $table->id(); se preferir IDs inteiros
            $table->foreignUuid('process_id')->constrained('processes')->onDelete('cascade');
            $table->foreignUuid('responsible_user_id')->nullable()->constrained('users')->onDelete('set null');
            // Ou, se User usa ID inteiro: $table->foreignId('responsible_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('Pendente'); // Ex: Pendente, Em Andamento, Concluída
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

    }

    public function down()
    {
        Schema::dropIfExists('tasks');
    }
};
