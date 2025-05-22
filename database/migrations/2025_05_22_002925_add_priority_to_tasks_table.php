    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;
    use App\Models\Task; // Importar o model Task para usar as constantes

    return new class extends Migration
    {
        /**
         * Run the migrations.
         */
        public function up(): void
        {
            Schema::table('tasks', function (Blueprint $table) {
                // Adicionar a coluna 'priority'
                // Definir um valor padrão é uma boa prática, especialmente se já existem dados na tabela.
                // Usar uma das suas constantes de prioridade como padrão.
                $table->string('priority')->default(Task::PRIORITY_MEDIUM)->after('status');
                // Você pode ajustar o ->after('status') para colocar a coluna onde preferir.
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('priority');
            });
        }
    };
    