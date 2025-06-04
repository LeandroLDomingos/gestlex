<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log; // Adicionado para debug, se necessário

class ACLMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1) Carrega o usuário autenticado junto com suas permissões diretas
        // e as permissões associadas às suas roles (funções/papéis).
        $user = Auth::user();

        // Se não houver usuário autenticado, prossegue para o próximo middleware
        // (provavelmente o middleware 'auth' tratará disso, redirecionando para o login).
        if (!$user) {
            return $next($request);
        }

        // Eager load das relações para otimizar.
        $user->load(['permissions', 'roles.permissions']);
        
        // 2) Verifica se o usuário possui alguma role com o atributo 'level' maior que 7 (ex: Admin).
        // Se sim, tem acesso irrestrito e a requisição prossegue.
        if ($user->roles->contains(fn($role) => $role->level > 7)) { // Ajuste o nível conforme sua lógica de Admin
            return $next($request);
        }

        // 3) Obtém o nome da rota atual.
        $routeName = Route::currentRouteName();

        // Se a rota não tiver nome, não podemos verificar a permissão baseada em nome.
        // Decida como tratar isso: permitir, negar, ou logar.
        // Por agora, se não houver nome de rota, vamos permitir (ou você pode negar por segurança).
        if (!$routeName) {
            // Log::warning('ACLMiddleware: Rota sem nome acessada: ' . $request->path());
            return $next($request); // Ou abort(403) ou redirect()->back()
        }

        // 4) Agrega todas as permissões do usuário.
        $directPermissions = $user->permissions->pluck('name')->all();
        $permissionsViaRoles = $user->roles
            ->flatMap(fn($role) => $role->permissions->pluck('name'))
            ->all();
        $allPermissions = array_unique(array_merge($directPermissions, $permissionsViaRoles));

        // 5) Verifica se o nome da rota atual (ou a permissão associada) está na lista de permissões.
        // O seeder cria permissões como 'route.nome.da.rota'.
        // Ajuste aqui se o seu padrão de nome de permissão for diferente.
        $requiredPermission = $routeName; // Assumindo que as permissões de rota são prefixadas com 'route.'

        if (!in_array($requiredPermission, $allPermissions, true)) {
            // Log para debug:
            // Log::warning("ACLMiddleware: Acesso negado para o usuário {$user->id} ({$user->email}) à rota '{$routeName}' (permissão necessária: '{$requiredPermission}'). Permissões do usuário: " . implode(', ', $allPermissions));
            // Log::info("ACLMiddleware: URL anterior: " . url()->previous());
            // Log::info("ACLMiddleware: URL atual: " . $request->fullUrl());

            // Redireciona para a página anterior.
            // Se não houver "página anterior" (ex: acesso direto à URL),
            // o Laravel pode redirecionar para a rota 'HOME' (geralmente /dashboard).
            // Certifique-se de que a mensagem 'error' é exibida no template para onde o usuário for redirecionado.
            return redirect()->back()
                ->with('error', 'Você não tem permissão para acessar esta página.');
        }

        // 6) Disponibiliza a lista completa de permissões do usuário no objeto Request (opcional).
        $request->attributes->set('permissions', $allPermissions);

        return $next($request);
    }
}
