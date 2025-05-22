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
        Schema::table('tasks', function (Blueprint $table) {
            // Tornar process_id nullable, pois uma tarefa pode não estar ligada a um processo
            // Se já for nullable devido a alguma alteração anterior, esta linha pode não ser necessária
            // ou pode precisar de ->nullable()->change() se a coluna já existir e não for nullable.
            // Verifique o estado atual da sua coluna 'process_id'.
            // Se ela já foi criada como UUID, o tipo aqui deve ser uuid também.
            if (Schema::hasColumn('tasks', 'process_id')) {
                 // Se a coluna process_id já é uuid e nullable, não precisa mudar
                 // Se for uuid mas não nullable, precisa de ->nullable()->change()
                 // Se não for uuid, precisa ajustar o tipo antes de adicionar a FK
                 // Para este exemplo, assumo que você ajustará o tipo se necessário manualmente
                 // ou que a coluna já é compatível.
                 // $table->foreignUuid('process_id')->nullable()->change(); // Exemplo se fosse UUID e precisasse mudar para nullable
                 $table->uuid('process_id')->nullable()->change(); // Tornar nullable
            }


            // Adicionar contact_id que pode ser nulo e é uma chave estrangeira para contacts
            $table->foreignUuid('contact_id')->nullable()->after('process_id')->constrained('contacts')->onDelete('cascade');
            // onDelete('cascade') significa que se o contato for deletado, suas tarefas associadas também serão.
            // Considere 'set null' se preferir manter a tarefa e apenas desassociá-la.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Remover a chave estrangeira e a coluna contact_id
            // O nome da restrição pode variar, verifique no seu SGBD se houver erro.
            // Ex: $table->dropForeign(['contact_id']); ou $table->dropForeign('tasks_contact_id_foreign');
            if (Schema::hasColumn('tasks', 'contact_id')) {
                // Tentar remover a FK de forma genérica (pode não funcionar em todos SGBDs para SQLite)
                // Para SQLite, a remoção de FKs pode ser complexa.
                // É mais seguro remover a coluna e recriá-la se necessário.
                // $table->dropConstrainedForeignId('contact_id'); // Laravel 9+
                $table->dropForeign(['contact_id']); // Tentativa comum
                $table->dropColumn('contact_id');
            }

            // Reverter process_id para não nullable (se era assim antes)
            // Isso é complexo e depende do estado anterior.
            // Se você tinha dados com process_id nulo, esta reversão pode falhar.
            // Por simplicidade, vamos omitir a reversão exata de nullability do process_id,
            // pois depende muito do estado inicial da sua tabela.
            // if (Schema::hasColumn('tasks', 'process_id')) {
            //     $table->uuid('process_id')->nullable(false)->change();
            // }
        });
    }
};
