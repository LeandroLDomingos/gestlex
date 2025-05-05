<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 255);
            $table->dateTime('due_datetime')->nullable();
            $table->enum('status', ['todo', 'in_progress', 'reviewing', 'completed']);
            $table->text('tags')->nullable();
            $table->text('description')->nullable();
            $table->uuid('process_id'); // Alterado para UUID
            $table->timestamps();
        
            $table->foreign('process_id')
                  ->references('id')->on('processes')
                  ->onDelete('cascade');
        });
        
    }

    public function down()
    {
        Schema::dropIfExists('tasks');
    }
};
