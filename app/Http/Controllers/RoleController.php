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
        // Autorização: Verifica se o usuário pode ver qualquer papel
        // $this->authorize('viewAny', Role::class); // Descomente se tiver policies

        $roles = Role::withCount('users', 'permissions')
            ->orderBy('level', 'asc')
            ->orderBy('name', 'asc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'filters' => $request->only(['search']), // Adicione filtros se necessário
            'canCreateRoles' => $request->user()->can('roles.create'), // Exemplo de verificação de permissão
            'canUpdateRoles' => $request->user()->can('roles.update'),
            'canDeleteRoles' => $request->user()->can('roles.delete'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): InertiaResponse
    {
        // Autorização
        // $this->authorize('create', Role::class); // Descomente se tiver policies
        if (!$request->user()->can('roles.create')) {
            abort(403, 'Você não tem permissão para criar papéis.');
        }

        $permissions = Permission::orderBy('description')->get(['id', 'name', 'description']);

        return Inertia::render('Admin/Roles/Create', [
            'allPermissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        // Autorização
        // $this->authorize('create', Role::class);
         if (!$request->user()->can('roles.create')) {
            abort(403, 'Você não tem permissão para criar papéis.');
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:255',
            'level' => 'required|integer|min:0',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,id', // Valida que cada ID de permissão existe
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create([
                'name' => $validatedData['name'],
                'description' => $validatedData['description'],
                'level' => $validatedData['level'],
            ]);

            if (!empty($validatedData['permissions'])) {
                $role->permissions()->sync($validatedData['permissions']);
            }

            DB::commit();
            return redirect()->route('admin.roles.index')->with('success', 'Papel criado com sucesso.');
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
        // Autorização
        // $this->authorize('update', $role);
         if (!$request->user()->can('roles.update')) {
            abort(403, 'Você não tem permissão para editar papéis.');
        }

        $role->load('permissions:id,name'); // Carrega as permissões atuais do papel
        $allPermissions = Permission::orderBy('description')->get(['id', 'name', 'description']);

        return Inertia::render('Admin/Roles/Edit', [
            'role' => $role,
            'rolePermissions' => $role->permissions->pluck('id')->toArray(), // Apenas os IDs das permissões do papel
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        // Autorização
        // $this->authorize('update', $role);
         if (!$request->user()->can('roles.update')) {
            abort(403, 'Você não tem permissão para editar papéis.');
        }

        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => 'nullable|string|max:255',
            'level' => 'required|integer|min:0',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,id',
        ]);

        DB::beginTransaction();
        try {
            $role->update([
                'name' => $validatedData['name'],
                'description' => $validatedData['description'],
                'level' => $validatedData['level'],
            ]);

            $role->permissions()->sync($validatedData['permissions'] ?? []);

            DB::commit();
            return redirect()->route('admin.roles.index')->with('success', 'Papel atualizado com sucesso.');
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
        // Autorização
        // $this->authorize('delete', $role);
         if (!$request->user()->can('roles.delete')) {
            abort(403, 'Você não tem permissão para excluir papéis.');
        }

        // Adicionar lógica para impedir a exclusão de papéis essenciais, se necessário
        if ($role->name === 'Admin' || $role->level > 5) { // Exemplo: não permitir excluir Admin ou papéis de alto nível
            return back()->with('error', 'Este papel não pode ser excluído.');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Este papel não pode ser excluído pois está associado a usuários.');
        }

        DB::beginTransaction();
        try {
            $role->permissions()->detach(); // Remove associações com permissões
            $role->delete();
            DB::commit();
            return redirect()->route('admin.roles.index')->with('success', 'Papel excluído com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir papel {$role->id}: " . $e->getMessage());
            return back()->with('error', 'Erro ao excluir o papel. Tente novamente.');
        }
    }
}
