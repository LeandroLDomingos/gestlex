<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

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
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        $routeName = Route::currentRouteName();

        // --- INÍCIO DA ALTERAÇÃO ---
        // Lista de rotas que serão ignoradas pela verificação de permissão.
        $routesToIgnore = [
            'processes.documents.show.aposentadoria.form',
            'processes.documents.generate.aposentadoria',
            'processes.documents.show.procuracao.form',
            'processes.documents.generate.procuracao',
            'processes.documents.show.declaracao.form',
            'processes.documents.generate.declaracao',
            'processes.documents.show.pedido-medico.form',
            'processes.documents.generate.pedido-medico',
            'processes.payments.receipt',
            'process-documents.download',
        ];

        // Se a rota atual estiver na lista para ignorar, permite o acesso imediatamente.
        if (in_array($routeName, $routesToIgnore, true)) {
            return $next($request);
        }
        // --- FIM DA ALTERAÇÃO ---

        $user->load(['permissions', 'roles.permissions']);
        
        if ($user->roles->contains(fn($role) => $role->level > 7)) {
            return $next($request);
        }

        if (!$routeName) {
            return $next($request);
        }

        $directPermissions = $user->permissions->pluck('name')->all();
        $permissionsViaRoles = $user->roles
            ->flatMap(fn($role) => $role->permissions->pluck('name'))
            ->all();
        $allPermissions = array_unique(array_merge($directPermissions, $permissionsViaRoles));

        $requiredPermission = $routeName;

        if (!in_array($requiredPermission, $allPermissions, true)) {
            return redirect()->back()
                ->with('error', 'Você não tem permissão para acessar esta página.');
        }

        $request->attributes->set('permissions', $allPermissions);

        return $next($request);
    }
}
