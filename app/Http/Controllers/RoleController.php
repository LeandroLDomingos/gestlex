<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): InertiaResponse
    {
        // $this->authorize('viewAny', Role::class); // Descomente se tiver policies

        // Esta parte do carregamento de dados para os papéis e suas permissões (apenas IDs)
        // está correta para o que o frontend espera, ASSUMINDO que o relacionamento
        // 'permissions()' no modelo App\Models\Role está definido corretamente como:
        // public function permissions() {
        //     return $this->belongsToMany(Permission::class, 'role_permission', 'role_id', 'permission_id');
        // }
        $roles = Role::with([
                'permissions:id' // Carrega apenas o ID das permissões para cada papel
            ])
            ->withCount('users', 'permissions') // Mantém as contagens
            ->orderBy('level', 'asc')
            ->orderBy('name', 'asc')
            ->paginate(15)
            ->withQueryString();

        // Carregar todas as permissões para o formulário de seleção também parece correto.
        $allPermissions = Permission::orderBy('name')->get(['id', 'name', 'description']);

        // O ERRO SQL (no such column: role_permission.user_id) é MUITO PROVAVELMENTE
        // DESENCADEADO POR UMA DAS SEGUINTES VERIFICAÇÕES 'can()', se a lógica
        // por trás dessas permissões no seu AuthServiceProvider (Gates) ou Policies
        // estiver a tentar consultar as permissões do UTILIZADOR de forma incorreta
        // (ex: tentando usar a tabela 'role_permission' para ligar utilizadores a permissões).
        // A forma correta é: User -> tem Roles -> Role tem Permissions.
        $canCreateRoles = $request->user()->can('roles.create');
        $canUpdateRoles = $request->user()->can('roles.update');
        $canDeleteRoles = $request->user()->can('roles.delete');
        $canManageRolePermissions = $request->user()->can('permissions.manage'); // Verifique a definição desta permissão

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'filters' => $request->only(['search', 'role_id']),
            'allPermissions' => $allPermissions,
            'canCreateRoles' => $canCreateRoles,
            'canUpdateRoles' => $canUpdateRoles,
            'canDeleteRoles' => $canDeleteRoles,
            'canManageRolePermissions' => $canManageRolePermissions,
            // Adicione a prop canCreateUsers se o botão "Novo Usuário" estiver nesta página
            'canCreateUsers' => $request->user()->can('users.create'), // Exemplo, ajuste o nome da permissão
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): InertiaResponse
    {
        $allPermissions = Permission::orderBy('description')->get(['id', 'name', 'description']);
        return Inertia::render('Admin/Roles/Create', [
             'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {

        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:255',
            'level' => 'required|integer|min:0',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,id',
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create([
                'name' => $validatedData['name'],
                'description' => $validatedData['description'],
                'level' => $validatedData['level'],
            ]);

            if (!empty($validatedData['permissions'])) {
                // Isto usa o relacionamento permissions() do modelo Role.
                // Certifique-se que está correto.
                $role->permissions()->sync($validatedData['permissions']);
            }

            DB::commit();
            return redirect()->route('admin.roles.index', ['role_id' => $role->id])->with('success', 'Papel criado com sucesso. Permissões atribuídas.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao criar papel: " . $e->getMessage());
            return back()->with('error', 'Erro ao criar o papel. Tente novamente.')->withInput();
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Role $role): InertiaResponse
    {

        // Carrega as permissões associadas a este papel específico.
        // Novamente, depende da correção do relacionamento permissions() no modelo Role.
        $role->load('permissions:id,name');
        $allPermissions = Permission::orderBy('description')->get(['id', 'name', 'description']);

        return Inertia::render('Admin/Roles/Edit', [
            'role' => $role,
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => 'nullable|string|max:255',
            'level' => 'required|integer|min:0',
            'permissions' => 'sometimes|nullable|array',
            'permissions.*' => 'string|exists:permissions,id',
        ]);

        DB::beginTransaction();
        try {
            $role->update([
                'name' => $validatedData['name'],
                'description' => $validatedData['description'],
                'level' => $validatedData['level'],
            ]);

            if ($request->has('permissions')) {
                 // Isto usa o relacionamento permissions() do modelo Role.
                $role->permissions()->sync($validatedData['permissions'] ?? []);
            }

            DB::commit();
            return redirect()->route('admin.roles.index', ['role_id' => $role->id])->with('success', 'Papel atualizado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar papel {$role->id}: " . $e->getMessage());
            return back()->with('error', 'Erro ao atualizar o papel. Tente novamente.')->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {

        if ($role->name === 'Admin' || $role->level > 5) { // Exemplo de proteção, ajuste 'level' conforme sua lógica
            return back()->with('error', 'Este papel não pode ser excluído.');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Este papel não pode ser excluído pois está associado a usuários.');
        }

        DB::beginTransaction();
        try {
            $role->permissions()->detach(); 
            $role->delete();
            DB::commit();
            return redirect()->route('admin.roles.index')->with('success', 'Papel excluído com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir papel {$role->id}: " . $e->getMessage());
            return back()->with('error', 'Erro ao excluir o papel. Tente novamente.');
        }
    }

    /**
     * Synchronize permissions for a given role.
     */
    public function syncRolePermissions(Request $request, Role $role): RedirectResponse
    {


        $validated = $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,id', // Garante que os IDs de permissão são strings (UUIDs) e existem
        ]);

        try {
            // Isto usa o relacionamento permissions() do modelo Role.
            $role->permissions()->sync($validated['permissions'] ?? []);
            return redirect()->route('admin.roles.index', ['role_id' => $role->id])
                             ->with('success', 'Permissões para o papel "' . $role->name . '" atualizadas com sucesso.');
        } catch (\Exception $e) {
            Log::error("Erro ao sincronizar permissões para o papel {$role->id}: " . $e->getMessage());
            return back()->with('error', 'Erro ao atualizar as permissões. Tente novamente.');
        }
    }
}
