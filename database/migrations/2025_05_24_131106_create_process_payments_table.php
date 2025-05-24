<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\PaymentType; // Importar o Enum

return new class extends Migration
{
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

            $table->decimal('amount', 15, 2);
            
            $table->string('payment_type')->default(PaymentType::A_VISTA->value); // Define um padrão

            $table->string('payment_method', 100)->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('process_id');
            $table->index('payment_type'); // Adicionar índice para o novo campo
            $table->index('payment_method');
            $table->index('status');
            $table->index('payment_date');
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

