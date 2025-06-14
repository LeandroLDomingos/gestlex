<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission; // Certifique-se de que o caminho do seu model está correto

class DocumentPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding Document-related Permissions...');

        // Lista de todas as permissões relacionadas à geração de documentos.
        // Os nomes são exatamente os mesmos definidos no seu arquivo de rotas.
        $documentPermissions = [
            'processes.documents.show.aposentadoria.form',
            'processes.documents.generate.aposentadoria',
            'processes.documents.show.procuracao.form',
            'processes.documents.generate.procuracao',
            'processes.documents.show.declaracao.form',
            'processes.documents.generate.declaracao',
            'processes.documents.show.pedido-medico.form',
            'processes.documents.generate.pedido-medico',
            'processes.payments.receipt',
        ];

        foreach ($documentPermissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['description' => 'Permissão para: ' . str_replace('.', ' ', $permissionName)]
            );

            $this->command->line("  Permissão de Documento: <info>{$permissionName}</info> " . ($permission->wasRecentlyCreated ? 'criada.' : 'já existe.'));
        }

        $this->command->info('Document permissions seeded successfully!');
    }
}
