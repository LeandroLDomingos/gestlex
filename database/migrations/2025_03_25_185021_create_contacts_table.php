<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // physical or legal
            $table->string('name');
            $table->string('cpf_cnpj')->unique()->nullable(); // Assuming it can be unique

            // Address fields (ensure these are all defined)
            $table->string('zip_code')->nullable();
            $table->string('address')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();

            // Physical Person Specific Fields (make nullable)
            $table->string('rg')->nullable();
            $table->string('gender')->nullable();
            $table->string('nationality')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('profession')->nullable();
            $table->date('date_of_birth')->nullable();

            // Legal Person Specific Fields (THIS IS WHERE THE MISSING COLUMNS ARE)
            $table->string('business_name')->nullable(); // <<< ADD THIS
            $table->text('business_activity')->nullable(); // <<< ADD THIS (text for longer descriptions)
            $table->string('tax_state')->nullable();     // <<< ADD THIS
            $table->string('tax_city')->nullable();      // <<< ADD THIS

            $table->foreignId('administrator_id')->nullable()->constrained('contacts')->onDelete('set null'); // Example if it's a foreign key

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};