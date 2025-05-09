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
        Schema::create('contacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('type', ['physical', 'legal']);
            $table->string('name', 255);
            $table->string('cpf_cnpj', 20)->nullable();
            $table->string('rg', 50)->nullable(); // Somente para contatos físicos
            $table->enum('gender', ['female', 'male', 'other'])->nullable(); // Somente para físicos
            $table->string('nationality', 100)->nullable(); // Somente para físicos
            $table->enum('marital_status', ['single', 'married', 'common_law', 'divorced', 'widowed', 'separated'])->nullable();
            $table->string('profession', 100)->nullable(); // Somente para físicos
            $table->string('business_activity', 100)->nullable(); // Somente para jurídicos
            $table->string('tax_state', 100)->nullable(); // Somente para jurídicos
            $table->string('tax_city', 100)->nullable(); // Somente para jurídicos
            $table->string('trade_name', 255)->nullable(); // Nome fantasia para contatos jurídicos
            $table->unsignedBigInteger('administrator_id')->nullable(); // Referência para um contato físico, se aplicável
            $table->bigInteger('zip_code')->nullable();
            $table->string('address')->nullable();
            $table->string('neighborhood')->nullable();
            $table->bigInteger('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();

            $table->foreign('administrator_id')
                  ->references('id')->on('contacts')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('contacts');
    }
};
