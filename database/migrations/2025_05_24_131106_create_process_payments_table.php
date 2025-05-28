<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\PaymentType; // Importar o Enum (assumindo que existe e é usado)

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('process_payments', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Chave primária para o pagamento

            // Chave estrangeira para o processo
            // Assumindo que 'processes.id' é UUID.
            $table->foreignUuid('process_id')
                  ->constrained('processes') // Laravel infere 'processes.id'
                  ->onDelete('cascade');

            // Valores monetários
            $table->decimal('total_amount', 25, 2);
            $table->decimal('down_payment_amount', 25, 2)->default(0); // Valor de entrada, pode ser 0

            // Tipo de pagamento (usando string para armazenar o valor do Enum)
            // Ex: 'pix', 'credit_card', 'bank_slip'
            // Considere adicionar um valor padrão se aplicável:
            // ->default(PaymentType::DEFAULT_TYPE->value)
            $table->string('payment_type');

            // Detalhes do pagamento
            $table->string('payment_method', 100)->nullable(); // Ex: "Visa final 4242", "Chave Pix XPTO"
            $table->date('down_payment_date')->nullable(); // Data do pagamento da entrada
            $table->integer('number_of_installments')->nullable()->default(1); // Número de parcelas (1 para pagamento único)
            
            // Valor da parcela. Se number_of_installments for 1, este pode ser igual a total_amount.
            $table->decimal('value_of_installment', 25, 2); 
            
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'partially_paid'])->default('pending');
            $table->date('first_installment_due_date')->nullable(); // Data de vencimento da primeira (ou única) parcela
            $table->text('notes')->nullable(); // Observações adicionais

            $table->timestamps(); // created_at e updated_at
            $table->softDeletes(); // deleted_at para exclusão lógica

            // Índices para otimizar consultas
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
