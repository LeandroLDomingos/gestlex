<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ACLMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1) Carrega o usuário com perms diretas + via roles
        $user = Auth::user()
            ->load(['permissions', 'roles.permissions']);

        // 2) Se for admin -> passa livre
        if ($user->roles->contains('name', 'Admin')) {
            return $next($request);
        }

        // 3) Nome da rota atual
        $routeName = Route::currentRouteName();

        // 4) Agrega todas as permissões (diretas + via roles)
        $direct   = $user->permissions->pluck('name')->all();
        $viaRoles = $user->roles
            ->flatMap(fn($r) => $r->permissions->pluck('name'))
            ->all();

        $allPerms = array_unique(array_merge($direct, $viaRoles));

        // 5) Checa se a rota está nas permissões
        if (! in_array($routeName, $allPerms, true)) {
            // você pode usar Inertia::location() ou redirect()->back()
            return redirect()->back()
                ->with('flash.error', 'Você não tem permissão para acessar esta rota.');
        }

        // 6) Disponibiliza as permissões completas na request, se precisar depois
        $request->attributes->set('permissions', $allPerms);

        return $next($request);
    }
}
