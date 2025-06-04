<?php

namespace App\Http\Controllers; // Ajuste o namespace se for diferente, ex: App\Http\Controllers\Admin

use App\Models\User;
use App\Models\Role; // Modelo para Papéis
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): InertiaResponse
    {
        // Verificação de permissão removida
        // if (!$request->user()->can('users.viewAny')) { 
        //     abort(403, 'Acesso não autorizado.');
        // }

        $query = User::with('roles:id,name')->orderBy('name'); 

        if ($request->filled('search')) {
            $searchTerm = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('email', 'like', $searchTerm);
            });
        }

        $users = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/Users/Index', [ 
            'users' => $users,
            'filters' => $request->only(['search']),
            // Props de permissão removidas
            // 'canCreateUsers' => true, // Ou remova completamente se o frontend não depender mais delas
            // 'canUpdateUsers' => true,
            // 'canDeleteUsers' => true,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): InertiaResponse
    {
        // Verificação de permissão removida
        // if (!$request->user()->can('users.create')) {
        //     abort(403, 'Acesso não autorizado.');
        // }

        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            // Prop de permissão removida
            // 'canAssignRoles' => true, 
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        // Verificação de permissão removida
        // if (!$request->user()->can('users.create')) {
        //     abort(403, 'Acesso não autorizado.');
        // }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:roles,id',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'email_verified_at' => now(),
            ]);

            // Verificação de permissão para atribuir papéis removida
            if (!empty($validatedData['roles'])) {
                $user->roles()->sync($validatedData['roles']);
            }

            DB::commit();

            return redirect()->route('admin.users.index')->with('success', 'Utilizador criado com sucesso.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao criar utilizador: " . $e->getMessage());
            return back()->with('error', 'Erro ao criar o utilizador. Tente novamente.')->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $user): InertiaResponse
    {
        // Verificação de permissão removida
        // if (!$request->user()->can('users.view', $user)) { 
        //     abort(403, 'Acesso não autorizado.');
        // }
        $user->load('roles:id,name');
        return Inertia::render('Admin/Users/Show', ['user' => $user]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, User $user): InertiaResponse
    {
        // Verificação de permissão removida
        //  if (!$request->user()->can('users.update', $user)) { 
        //     abort(403, 'Acesso não autorizado.');
        // }

        $user->load('roles:id'); 
        
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'userRoleIds' => $user->roles->pluck('id')->toArray(),
            // Prop de permissão removida
            // 'canAssignRoles' => true,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        // Verificação de permissão removida
        // if (!$request->user()->can('users.update', $user)) {
        //     abort(403, 'Acesso não autorizado.');
        // }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:roles,id',
        ]);

        DB::beginTransaction();
        try {
            $userDataToUpdate = [
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
            ];

            if (!empty($validatedData['password'])) {
                $userDataToUpdate['password'] = Hash::make($validatedData['password']);
            }

            $user->update($userDataToUpdate);

            // Verificação de permissão para atribuir papéis removida
            if ($request->has('roles')) { 
                $user->roles()->sync($validatedData['roles'] ?? []);
            }
            
            DB::commit();

            return redirect()->route('admin.users.index')->with('success', 'Utilizador atualizado com sucesso.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar utilizador {$user->id}: " . $e->getMessage());
            return back()->with('error', 'Erro ao atualizar o utilizador. Tente novamente.')->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Verificação de permissão removida
        // if (!$request->user()->can('users.delete', $user)) {
        //     abort(403, 'Acesso não autorizado.');
        // }

        if ($request->user()->id === $user->id) {
            return back()->with('error', 'Não pode excluir a sua própria conta.');
        }
        // Exemplo: if ($user->hasRole('Admin')) { return back()->with('error', 'Não pode excluir um Administrador.'); }


        DB::beginTransaction();
        try {
            $user->roles()->detach(); 
            $user->delete(); 
            DB::commit();

            return redirect()->route('admin.users.index')->with('success', 'Utilizador excluído com sucesso.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir utilizador {$user->id}: " . $e->getMessage());
            return back()->with('error', 'Erro ao excluir o utilizador. Tente novamente.');
        }
    }
}
