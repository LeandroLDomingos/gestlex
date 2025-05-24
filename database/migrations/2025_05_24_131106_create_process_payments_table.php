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
        Schema::create('process_payments', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Chave primária para o pagamento

            // Chave estrangeira para conectar ao processo
            $table->uuid('process_id');
            $table->foreign('process_id')
                  ->references('id')
                  ->on('processes')
                  ->onDelete('cascade'); // Se o processo for deletado, os pagamentos associados também serão.
                                         // Considere 'restrict' se você não quiser permitir a exclusão de um processo que tenha pagamentos.

            $table->decimal('amount', 15, 2); // O valor que antes era 'negotiated_value'
            
            $table->string('payment_method', 100)->nullable(); // Método de pagamento (ex: 'Cartão de Crédito', 'Boleto', 'PIX', 'Transferência')
            // Você pode optar por um enum aqui também, se os métodos forem bem definidos e limitados:
            // $table->enum('payment_method', ['credit_card', 'bank_slip', 'pix', 'transfer'])->nullable();

            $table->date('payment_date')->nullable(); // Data em que o pagamento foi efetuado ou previsto
            
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending'); // Status do pagamento

            $table->text('notes')->nullable(); // Observações adicionais sobre o pagamento

            $table->timestamps(); // created_at e updated_at
            $table->softDeletes(); // Opcional: para exclusão lógica de pagamentos

            // Indexes para otimizar consultas
            $table->index('process_id');
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
