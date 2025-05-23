<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            // Coluna de ID primário, usando UUID por padrão no Laravel 9+ se configurado,
            // ou você pode especificar $table->uuid('id')->primary();
            $table->id(); // Ou $table->uuid('id')->primary(); se preferir UUIDs como chave primária.
                          // Se 'process_id' e 'contact_id' são UUIDs, talvez a chave primária também deva ser.
                          // Para este exemplo, vou manter o $table->id() padrão (BigInt auto-increment).
                          // Se você decidir usar UUID como PK, ajuste as FKs abaixo também.

            $table->string('description', 255);
            $table->decimal('amount', 15, 2); // Precisão de 15 dígitos, 2 casas decimais. Ajuste conforme necessário.
            $table->string('type'); // 'income' ou 'expense'
            $table->date('transaction_date'); // Data em que a transação ocorreu ou foi registrada

            // Campos adicionais inspirados na interface:
            $table->string('category')->nullable(); // Categoria da transação, ex: "Receita de Serviços", "Despesa Escritório", "Conta Corrente X"
                                                    // Considere uma tabela 'transaction_categories' separada se as categorias forem complexas ou tiverem propriedades próprias.
            $table->string('payment_method')->nullable(); // Ex: "Dinheiro", "Transferência Bancária", "Cartão de Crédito", "Conta Corrente Y"
            $table->string('status')->default('Confirmado'); // Ex: "Confirmado", "Pendente", "Agendado", "Cancelado"
            $table->date('due_date')->nullable(); // Data de vencimento (para contas a pagar/receber)
            $table->timestamp('paid_at')->nullable(); // Data e hora em que uma transação pendente/agendada foi efetivamente paga/recebida

            // Chaves estrangeiras
            // Assumindo que 'processes' e 'contacts' usam UUIDs como chaves primárias,
            // e 'users' usa o padrão (BigInt). Ajuste se for diferente.

            // Se 'processes.id' é UUID:
            $table->foreignUuid('process_id')->nullable()->constrained('processes')->onDelete('set null');
            // Se 'processes.id' é BigInt:
            // $table->foreignId('process_id')->nullable()->constrained('processes')->onDelete('set null');

            // Se 'contacts.id' é UUID:
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->onDelete('set null');
            // Se 'contacts.id' é BigInt:
            // $table->foreignId('contact_id')->nullable()->constrained('contacts')->onDelete('set null');


            // Assumindo que a tabela 'users' usa ID padrão (BigInt)
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade'); // Ou onDelete('restrict') ou onDelete('set null') dependendo da sua regra de negócio

            $table->text('notes')->nullable(); // Para notas mais longas

            $table->timestamps(); // Adiciona created_at e updated_at
            $table->softDeletes(); // Adiciona deleted_at para soft deletes
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('financial_transactions');
    }
};
