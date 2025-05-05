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
        Schema::create('contact_processes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('process_id');
            $table->unsignedBigInteger('contact_id');
            $table->timestamps();

            $table->foreign('process_id')
                  ->references('id')->on('processes')
                  ->onDelete('cascade');

            $table->foreign('contact_id')
                  ->references('id')->on('contacts')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_processes');
    }
};
