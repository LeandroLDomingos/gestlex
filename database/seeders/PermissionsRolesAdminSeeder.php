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
        // Consider only if you want to reset these tables completely during seeding
        // Permission::truncate();
        // Role::truncate();
        // DB::table('role_user')->truncate();
        // DB::table('role_permission')->truncate();


        $this->command->info('Seeding Permissions...');
        $allGeneratedPermissions = []; // Store all Permission models created/found
        $routeCollection = Route::getRoutes();

        foreach ($routeCollection as $route) {
            $routeName = $route->getName();
            // Exclude common framework/package routes and any others you don't want permissions for
            if ($routeName && 
                !Str::startsWith($routeName, ['ignition.', 'sanctum.', 'livewire.', 'telescope', 'horizon.']) &&
                !Str::contains($routeName, ['password.reset', 'password.email', 'verification.']) // Exclude password reset and email verification routes
            ) {
                $permissionName = 'route.' . $routeName . '.access';
                $permissionDescription = 'Acesso à rota: ' . $routeName;
                
                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName],
                    [
                        // 'id' => Str::uuid(), // If your Permission model uses UUIDs and HasUuids trait
                        'description' => $permissionDescription,
                    ]
                );
                $allGeneratedPermissions[] = $permission;
                $this->command->line("  Permissão: <info>{$permissionName}</info> " . ($permission->wasRecentlyCreated ? 'criada.' : 'já existe.'));
            }
        }
        
        $manualPermissionsData = [
            ['name' => 'system.settings.manage', 'description' => 'Gerenciar configurações do sistema'],
            ['name' => 'users.manage', 'description' => 'Gerenciar todos os usuários (criar, editar, excluir)'],
            ['name' => 'roles.manage', 'description' => 'Gerenciar papéis e suas permissões'], // Este será para Admin
            // Adicione outras permissões granulares manuais se necessário
            // Ex: ['name' => 'reports.view.financial', 'description' => 'Visualizar relatórios financeiros']
        ];

        foreach ($manualPermissionsData as $pData) {
             $permission = Permission::firstOrCreate(
                ['name' => $pData['name']],
                [
                    // 'id' => Str::uuid(),
                    'description' => $pData['description'],
                ]
            );
            $allGeneratedPermissions[] = $permission; // Add to the collection
            $this->command->line("  Permissão manual: <info>{$pData['name']}</info> " . ($permission->wasRecentlyCreated ? 'criada.' : 'já existe.'));
        }

        // --- Collect all permission IDs for easier assignment ---
        $allPermissionIds = collect($allGeneratedPermissions)->pluck('id')->toArray();


        $this->command->info('Seeding Roles...');

        // 1. Admin Role (Highest privileges)
        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin'],
            [
                // 'id' => Str::uuid(), 
                'description' => 'Administrador com todas as permissões do sistema',
                'level' => 10, // Assuming 10 is the highest level
            ]
        );
        $this->command->line("  Role: <info>Admin</info> " . ($adminRole->wasRecentlyCreated ? 'criado.' : 'já existe.'));
        $adminRole->permissions()->sync($allPermissionIds); // Admin gets all permissions
        $this->command->line("  Todas as permissões (" . count($allPermissionIds) . ") atribuídas ao role <info>Admin</info>.");

        // 2. Gerente Role (Manager)
        $managerRole = Role::firstOrCreate(
            ['name' => 'Gerente'],
            [
                // 'id' => Str::uuid(),
                'description' => 'Gerente com acesso amplo, exceto gerenciamento de papéis e permissões do sistema.',
                'level' => 7, // Mid-level
            ]
        );
        $this->command->line("  Role: <info>Gerente</info> " . ($managerRole->wasRecentlyCreated ? 'criado.' : 'já existe.'));
        
        // Filter permissions for Gerente: all except role/permission management
        $managerPermissionIds = collect($allGeneratedPermissions)
            ->filter(function (Permission $permission) {
                // Exclui permissões de gerenciamento de papéis e permissões do sistema
                return !Str::startsWith($permission->name, ['route.admin.roles.', 'route.admin.permissions.']) &&
                       $permission->name !== 'roles.manage' && 
                       $permission->name !== 'system.settings.manage'; // Exemplo, se settings for só para admin
            })
            ->pluck('id')->toArray();
        
        $managerRole->permissions()->sync($managerPermissionIds);
        $this->command->line("  " . count($managerPermissionIds) . " permissões atribuídas ao role <info>Gerente</info>.");

        // 3. Convidado Role (Guest)
        $guestRole = Role::firstOrCreate(
            ['name' => 'Convidado'],
            [
                // 'id' => Str::uuid(),
                'description' => 'Usuário convidado com acesso muito limitado ou apenas visualização.',
                'level' => 1, // Lowest level
            ]
        );
        $this->command->line("  Role: <info>Convidado</info> " . ($guestRole->wasRecentlyCreated ? 'criado.' : 'já existe.'));
        $guestRole->permissions()->sync([]); // Guest gets no permissions by default
        $this->command->line("  Nenhuma permissão atribuída ao role <info>Convidado</info>.");


        $this->command->info('Seeding Admin User...');
        $adminUserData = [
            // 'id' => Str::uuid(),
            'name' => 'Administrador do Sistema', // Nome mais descritivo
            'email' => 'admin@fernandalorenadvocacia.com.br', // MUDE ISTO
            'password' => Hash::make('password'), // MUDE ISTO para uma senha forte
            'email_verified_at' => now(),
        ];

        $adminUser = User::firstOrCreate(['email' => $adminUserData['email']], $adminUserData);
        $this->command->line("  Usuário Admin: <info>{$adminUserData['email']}</info> " . ($adminUser->wasRecentlyCreated ? 'criado.' : 'já existe.'));
        
        $adminUser->roles()->sync([$adminRole->id]);
        $this->command->line("  Role <info>Admin</info> atribuído ao usuário <info>{$adminUser->email}</info>.");

        // Você pode querer criar um usuário Gerente e Convidado de exemplo também:
        // Exemplo Gerente
        // $managerUserData = ['name' => 'Usuário Gerente', 'email' => 'gerente@example.com', 'password' => Hash::make('password')];
        // $managerUser = User::firstOrCreate(['email' => $managerUserData['email']], $managerUserData);
        // $managerUser->roles()->sync([$managerRole->id]);
        // $this->command->line("  Usuário Gerente: <info>{$managerUser->email}</info> criado e atribuído ao role <info>Gerente</info>.");

        // Exemplo Convidado
        // $guestUserData = ['name' => 'Usuário Convidado', 'email' => 'convidado@example.com', 'password' => Hash::make('password')];
        // $guestUser = User::firstOrCreate(['email' => $guestUserData['email']], $guestUserData);
        // $guestUser->roles()->sync([$guestRole->id]);
        // $this->command->line("  Usuário Convidado: <info>{$guestUser->email}</info> criado e atribuído ao role <info>Convidado</info>.");


        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->command->info('Permissions, Roles, and Admin User seeded successfully!');
    }
}
