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
        // Disable foreign key checks to avoid issues with truncating/seeding
        // Adjust for your database if not MySQL/MariaDB
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        // Truncate tables (optional, use with caution, especially in production)
        // Consider if you want to clear existing data or just add/update
        // Permission::truncate(); // Requires App\Models\Permission
        // Role::truncate();       // Requires App\Models\Role
        // DB::table('role_user')->truncate();
        // DB::table('permission_user')->truncate(); // If you have this table directly
        // DB::table('role_permission')->truncate();

        $this->command->info('Seeding Permissions...');
        $permissions = [];
        $routeCollection = Route::getRoutes();

        foreach ($routeCollection as $route) {
            $routeName = $route->getName();
            if ($routeName && !Str::startsWith($routeName, ['ignition.', 'sanctum.', 'livewire.'])) { // Ignore debug/package routes
                // Create a permission name, e.g., "route.users.index.access"
                // You can customize the permission naming convention
                $permissionName = 'route.' . $routeName . '.access';
                $permissionDescription = 'Acesso à rota: ' . $routeName;
                
                // Check if permission already exists to avoid duplicates if not truncating
                $existingPermission = Permission::where('name', $permissionName)->first();
                if (!$existingPermission) {
                    $permission = Permission::create([
                        // 'id' => Str::uuid(), // Assuming your Permission model uses UUIDs and HasUuids trait
                        'name' => $permissionName,
                        'description' => $permissionDescription,
                    ]);
                    $permissions[] = $permission;
                    $this->command->line("  Permissão criada: <info>{$permissionName}</info>");
                } else {
                    $permissions[] = $existingPermission; // Add existing permission to the list for role assignment
                    $this->command->line("  Permissão já existe: <comment>{$permissionName}</comment>");
                }
            }
        }
        
        // You can add more generic permissions manually if needed
        $manualPermissions = [
            // Example:
            // ['name' => 'users.manage', 'description' => 'Gerenciar todos os usuários'],
            // ['name' => 'settings.edit', 'description' => 'Editar configurações do sistema'],
        ];

        foreach ($manualPermissions as $pData) {
             $existingPermission = Permission::where('name', $pData['name'])->first();
             if (!$existingPermission) {
                $permission = Permission::create([
                    // 'id' => Str::uuid(),
                    'name' => $pData['name'],
                    'description' => $pData['description'],
                ]);
                $permissions[] = $permission;
                $this->command->line("  Permissão manual criada: <info>{$pData['name']}</info>");
            } else {
                $permissions[] = $existingPermission;
                $this->command->line("  Permissão manual já existe: <comment>{$pData['name']}</comment>");
            }
        }


        $this->command->info('Seeding Roles...');

        // Create Admin Role
        $adminRoleData = [
            // 'id' => Str::uuid(), // Assuming your Role model uses UUIDs and HasUuids trait
            'name' => 'Admin',
            'description' => 'Administrador com todas as permissões',
            'level' => 1, // Or your highest level
        ];
        $adminRole = Role::firstOrCreate(['name' => $adminRoleData['name']], $adminRoleData);
        $this->command->line("  Role: <info>Admin</info> " . ($adminRole->wasRecentlyCreated ? 'criado.' : 'já existe.'));

        // Assign all permissions to Admin role
        if (count($permissions) > 0) {
            $permissionIds = collect($permissions)->pluck('id');
            $adminRole->permissions()->sync($permissionIds); // Syncs permissions, removing old ones not in the list
            $this->command->line("  Todas as " . count($permissions) . " permissões atribuídas ao role <info>Admin</info>.");
        } else {
            $this->command->line("  Nenhuma permissão para atribuir ao role <info>Admin</info>.");
        }

        // You can create other roles here
        // Example:
        // $editorRoleData = ['name' => 'Editor', 'description' => 'Pode editar conteúdo', 'level' => 5];
        // $editorRole = Role::firstOrCreate(['name' => $editorRoleData['name']], $editorRoleData);
        // $this->command->line("  Role: <info>Editor</info> " . ($editorRole->wasRecentlyCreated ? 'criado.' : 'já existe.'));
        // $editorPermissions = Permission::whereIn('name', ['route.posts.edit.access', 'route.posts.update.access'])->pluck('id');
        // $editorRole->permissions()->sync($editorPermissions);


        $this->command->info('Seeding Admin User...');
        $adminUserData = [
            // 'id' => Str::uuid(), // If your User model uses UUIDs
            'name' => 'Admin',
            'email' => 'admin@fernandalorenadvocacia.com.br', // Change this
            'password' => Hash::make('password'), // Change this to a secure password
            'email_verified_at' => now(),
            // Add any other required fields for your User model
        ];

        // Check if admin user already exists
        $adminUser = User::where('email', $adminUserData['email'])->first();
        if (!$adminUser) {
            $adminUser = User::create($adminUserData);
            $this->command->line("  Usuário Admin: <info>{$adminUserData['email']}</info> criado.");
        } else {
            $this->command->line("  Usuário Admin: <info>{$adminUserData['email']}</info> já existe.");
            // Optionally update admin user's details if needed, e.g., password
            // $adminUser->password = Hash::make('new_password');
            // $adminUser->save();
        }
        
        // Assign Admin role to the admin user
        $adminUser->roles()->sync([$adminRole->id]); // Syncs roles, removing others
        $this->command->line("  Role <info>Admin</info> atribuído ao usuário <info>{$adminUser->email}</info>.");

        // Re-enable foreign key checks
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->command->info('Permissions, Roles, and Admin User seeded successfully!');
    }
}
