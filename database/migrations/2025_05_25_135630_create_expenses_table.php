<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Process; // Para a chave estrangeira opcional
use App\Models\User;    // Para a chave estrangeira opcional

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(User::class, 'user_id')->comment('Usuário que registrou')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(Process::class, 'process_id')->comment('Caso/Processo associado (opcional)')->nullable()->constrained('processes')->nullOnDelete();
            // Você pode adicionar uma FK para contacts se quiser associar despesas a fornecedores
            // $table->foreignIdFor(Contact::class, 'contact_id')->comment('Fornecedor (opcional)')->nullable()->constrained('contacts')->nullOnDelete();

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};