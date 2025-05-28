<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Process; // Para a chave estrangeira opcional
use App\Models\User;    // Para a chave estrangeira opcional
// Se você adicionar a FK para contacts, descomente a linha abaixo
// use App\Models\Contact;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Chave estrangeira para users (assumindo users.id é BIGINT)
            $table->foreignIdFor(User::class, 'user_id')
                  ->comment('Usuário que registrou a despesa')
                  ->nullable()
                  ->constrained('users') // Laravel infere a coluna 'id' da tabela 'users'
                  ->nullOnDelete();     // Se o usuário for excluído, define user_id como NULL

            // --- INÍCIO DA CORREÇÃO PARA process_id ---
            // Chave estrangeira para processes (assumindo processes.id é UUID)
            $table->foreignUuid('process_id') // Alterado de foreignIdFor para foreignUuid
                  ->comment('Caso/Processo associado (opcional)')
                  ->nullable()
                  ->constrained('processes') // Laravel infere a coluna 'id' da tabela 'processes'
                  ->nullOnDelete();     // Se o processo for excluído, define process_id como NULL
            // --- FIM DA CORREÇÃO PARA process_id ---

            // Exemplo de FK para contacts (assumindo contacts.id é UUID)
            // Se você decidir usar, a tabela 'contacts' deve ter 'id' como UUID.
            // $table->foreignUuid('contact_id')
            //       ->comment('Fornecedor (opcional)')
            //       ->nullable()
            //       ->constrained('contacts')
            //       ->nullOnDelete();

            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('expense_date')->comment('Data em que a despesa ocorreu/foi paga');
            $table->date('due_date')->nullable()->comment('Data de vencimento (se for uma conta a pagar)');
            $table->string('category')->nullable()->comment('Categoria da despesa, ex: Aluguel, Material, Custas');
            $table->string('payment_method')->nullable();
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('paid');
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index('expense_date');
            $table->index('category');
            $table->index('status');
            // Considere adicionar um índice para user_id e process_id se forem frequentemente usados em filtros
            // $table->index('user_id');
            // $table->index('process_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
