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
        Schema::table('processes', function (Blueprint $table) {
            // Adiciona a coluna contact_id.
            // Se a sua tabela 'contacts' usa UUIDs para a chave primária,
            // esta coluna também deve ser um UUID.
            // Se 'contacts.id' for um BIGINT auto-incrementável, use $table->foreignId('contact_id')
            $table->uuid('contact_id')->nullable()->after('description'); // Ou depois de outra coluna relevante

            // Define a chave estrangeira.
            // Certifique-se de que a tabela 'contacts' existe e que 'id' é a chave primária dela.
            $table->foreign('contact_id')
                  ->references('id')
                  ->on('contacts') // Nome da sua tabela de contatos
                  ->onDelete('set null'); // Ou 'cascade' se preferir deletar processos quando o contato for deletado,
                                          // ou 'restrict' para impedir a exclusão do contato se houver processos.
                                          // 'set null' permite que o processo exista sem um contato se o contato for deletado.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            // Remove a chave estrangeira primeiro se ela existir pelo nome padrão
            // O nome padrão é geralmente: processes_contact_id_foreign
            // Se você nomeou explicitamente, use esse nome.
            if (Schema::hasColumn('processes', 'contact_id')) {
                 // Verificar se a FK existe antes de tentar dropar pode variar por DB driver
                // Para uma abordagem mais segura, você pode precisar checar o nome exato da FK.
                // Exemplo genérico:
                try {
                    $table->dropForeign(['contact_id']);
                } catch (\Exception $e) {
                    // Log ou ignora se a FK não existir com o nome padrão
                    // ou se o driver do DB não suportar dropForeign por array de colunas diretamente
                    // Neste caso, você pode precisar do nome explícito da constraint.
                    // Ex: $table->dropForeign('processes_contact_id_foreign');
                    // Se o nome da constraint for diferente, ajuste aqui.
                }
                $table->dropColumn('contact_id');
            }
        });
    }
};
