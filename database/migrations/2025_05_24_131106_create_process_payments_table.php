<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\PaymentType; // Importar o Enum

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('process_payments', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Chave primária para o pagamento

            $table->uuid('process_id');
            $table->foreign('process_id')
                ->references('id')
                ->on('processes')
                ->onDelete('cascade');

            $table->decimal('total_amount', 25, 2);
            $table->decimal('down_payment_amount', 25, 2);

            $table->string('payment_type');

            $table->string('payment_method', 100)->nullable();
            $table->date('down_payment_date')->nullable();
            $table->integer('number_of_installments')->nullable();
            $table->decimal('value_of_installment', 25, 2);
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->date('first_installment_due_date')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('process_id');
            $table->index('payment_type'); 
            $table->index('payment_method');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_payments');
    }
};

