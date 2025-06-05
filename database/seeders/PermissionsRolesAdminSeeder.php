<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str; // For Str::uuid()

// Assuming you have these models
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;

class PermissionsRolesAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        // Optional: Truncate tables before seeding
        // Permission::truncate();
        // Role::truncate();
        // DB::table('role_user')->truncate();
        // DB::table('role_permission')->truncate();

        $this->command->info('Seeding Permissions...');
        $allGeneratedPermissions = []; 
        $routeCollection = Route::getRoutes();

        foreach ($routeCollection as $route) {
            $routeName = $route->getName();
            if ($routeName && 
                !Str::startsWith($routeName, ['ignition.', 'sanctum.', 'livewire.', 'telescope', 'horizon.']) &&
                !Str::contains($routeName, ['password.', 'verification.']) // Simplificado para cobrir todas as rotas de password e verification
            ) {
                // REMOVIDO '.access' do final do nome da permissão
                $permissionName = $routeName; 
                $permissionDescription = 'Permissão para: ' . $routeName; // Descrição mais genérica
                
                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName],
                    [
                        'description' => $permissionDescription,
                    ]
                );
                $allGeneratedPermissions[] = $permission;
                $this->command->line("  Permissão: <info>{$permissionName}</info> " . ($permission->wasRecentlyCreated ? 'criada.' : 'já existe.'));
            }
        }
        
        // $manualPermissionsData = [
        //     ['name' => 'system.settings.manage', 'description' => 'Gerenciar configurações do sistema'],
        //     ['name' => 'users.manage', 'description' => 'Gerenciar todos os usuários (criar, editar, excluir)'],
        //     ['name' => 'roles.manage', 'description' => 'Gerenciar papéis e suas permissões'],
        // ];

        // foreach ($manualPermissionsData as $pData) {
        //      $permission = Permission::firstOrCreate(
        //         ['name' => $pData['name']],
        //         [
        //             'description' => $pData['description'],
        //         ]
        //     );
        //     $allGeneratedPermissions[] = $permission;
        //     $this->command->line("  Permissão manual: <info>{$pData['name']}</info> " . ($permission->wasRecentlyCreated ? 'criada.' : 'já existe.'));
        // }

        $allPermissionIds = collect($allGeneratedPermissions)->pluck('id')->toArray();

        $this->command->info('Seeding Roles...');

        // 1. Admin Role
        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin'],
            [
                'description' => 'Administrador com todas as permissões do sistema',
                'level' => 10, 
            ]
        );
        $this->command->line("  Role: <info>Admin</info> " . ($adminRole->wasRecentlyCreated ? 'criado.' : 'já existe.'));
        $adminRole->permissions()->sync($allPermissionIds);
        $this->command->line("  Todas as permissões (" . count($allPermissionIds) . ") atribuídas ao role <info>Admin</info>.");

        // 2. Gerente Role
        $managerRole = Role::firstOrCreate(
            ['name' => 'Gerente'],
            [
                'description' => 'Gerente com acesso amplo, exceto gerenciamento de papéis e configurações do sistema.',
                'level' => 7, 
            ]
        );
        $this->command->line("  Role: <info>Gerente</info> " . ($managerRole->wasRecentlyCreated ? 'criado.' : 'já existe.'));
        
        $managerPermissionIds = collect($allGeneratedPermissions)
            ->filter(function (Permission $permission) {
                return !Str::startsWith($permission->name, ['admin.roles.', 'admin.permissions.', 'admin.users.']) && // Rotas de admin para roles e permissoes
                       !Str::startsWith($permission->name, ['roles.']) && // Rotas gerais de roles
                       !Str::startsWith($permission->name, ['permissions.']) && // Rotas gerais de permissoes
                       !Str::startsWith($permission->name, ['users.']) && // Rotas gerais de permissoes
                       $permission->name !== 'roles.manage' && 
                       $permission->name !== 'system.settings.manage';
            })
            ->pluck('id')->toArray();
        
        $managerRole->permissions()->sync($managerPermissionIds);
        $this->command->line("  " . count($managerPermissionIds) . " permissões atribuídas ao role <info>Gerente</info>.");

        // 3. Convidado Role
        $guestRole = Role::firstOrCreate(
            ['name' => 'Convidado'],
            [
                'description' => 'Usuário convidado com acesso muito limitado ou apenas visualização.',
                'level' => 1, 
            ]
        );
        $this->command->line("  Role: <info>Convidado</info> " . ($guestRole->wasRecentlyCreated ? 'criado.' : 'já existe.'));
        // Atribuir apenas permissões de visualização básicas, se houver. Ex: dashboard
        $guestPermissions = collect($allGeneratedPermissions)
            ->filter(fn(Permission $p) => $p->name === 'contats.index') // Exemplo: apenas acesso ao dashboard
            ->pluck('id')->toArray();
        $guestRole->permissions()->sync($guestPermissions);
        $this->command->line("  " . count($guestPermissions) . " permissões atribuídas ao role <info>Convidado</info>.");


        $this->command->info('Seeding Users...');
        
        // Admin User
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@fernandalorenadvocacia.com.br'],
            [
                'name' => 'Admin Fernanda Lorena',
                'password' => Hash::make('password'), // MUDE ESTA SENHA
                'email_verified_at' => now(),
            ]
        );
        $adminUser->roles()->sync([$adminRole->id]);
        $this->command->line("  Usuário Admin: <info>{$adminUser->email}</info> " . ($adminUser->wasRecentlyCreated ? 'criado e ' : 'já existe e ') . "role Admin atribuído.");

        // Gerente User
        $managerUser = User::firstOrCreate(
            ['email' => 'gerente@fernandalorenadvocacia.com.br'],
            [
                'name' => 'Gerente Equipe',
                'password' => Hash::make('password'), // MUDE ESTA SENHA
                'email_verified_at' => now(),
            ]
        );
        $managerUser->roles()->sync([$managerRole->id]);
        $this->command->line("  Usuário Gerente: <info>{$managerUser->email}</info> " . ($managerUser->wasRecentlyCreated ? 'criado e ' : 'já existe e ') . "role Gerente atribuído.");
        
        // Convidado User
        $guestUser = User::firstOrCreate(
            ['email' => 'convidado@fernandalorenadvocacia.com.br'],
            [
                'name' => 'Usuário Convidado',
                'password' => Hash::make('password'), // MUDE ESTA SENHA
                'email_verified_at' => now(),
            ]
        );
        $guestUser->roles()->sync([$guestRole->id]);
        $this->command->line("  Usuário Convidado: <info>{$guestUser->email}</info> " . ($guestUser->wasRecentlyCreated ? 'criado e ' : 'já existe e ') . "role Convidado atribuído.");


        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->command->info('Permissions, Roles, and Test Users seeded successfully!');
    }
}
