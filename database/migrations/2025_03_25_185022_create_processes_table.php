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
        Schema::create('processes', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->string('title', 255);
            $table->string('origin', 255)->nullable();
            $table->decimal('negotiated_value', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->uuid('responsible_id');
            $table->integer('workflow');
            $table->integer('stage')->nullable();
            $table->foreign('responsible_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
