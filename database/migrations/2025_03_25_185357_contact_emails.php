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
        Schema::create('contact_emails', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contact_id');
            $table->string('email', 255);
            $table->timestamps();

            $table->foreign('contact_id')
                  ->references('id')->on('contacts')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('contact_emails');
    }
};
