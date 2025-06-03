<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

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
        // O método load() é usado para eager loading das relações,
        // o que otimiza as consultas ao banco de dados.
        $user = Auth::user()->load(['permissions', 'roles.permissions']);
        // 2) Verifica se o usuário possui alguma role com o atributo 'level' maior que 5.
        // A função contains() da Collection é usada aqui com uma closure.
        // Para cada role do usuário, a closure verifica se $role->level > 5.
        // Se qualquer uma das roles satisfizer essa condição, o usuário
        // tem acesso irrestrito e a requisição prossegue.
        if ($user->roles->contains(fn($role) => $role->level > 5)) {
            return $next($request);
        }

        // 3) Obtém o nome da rota atual que o usuário está tentando acessar.
        // Isso é crucial para verificar se o usuário tem permissão para esta rota específica.
        $routeName = Route::currentRouteName();

        // 4) Agrega todas as permissões do usuário.
        // Primeiro, pega as permissões diretas atribuídas ao usuário.
        $directPermissions = $user->permissions->pluck('name')->all();

        // Em seguida, pega as permissões atribuídas através das roles do usuário.
        // flatMap() é usado para achatar a coleção de roles e suas permissões
        // em uma única lista de nomes de permissões.
        $permissionsViaRoles = $user->roles
            ->flatMap(fn($role) => $role->permissions->pluck('name'))
            ->all();

        // Combina as permissões diretas e as permissões via roles.
        // array_unique() remove quaisquer nomes de permissões duplicados.
        $allPermissions = array_unique(array_merge($directPermissions, $permissionsViaRoles));

        // 5) Verifica se o nome da rota atual está na lista de todas as permissões do usuário.
        // Se a rota não estiver nas permissões, o usuário é redirecionado
        // para a página anterior com uma mensagem de erro.
        if (!in_array($routeName, $allPermissions, true)) {
            // Você pode personalizar o redirecionamento ou a resposta aqui.
            // Por exemplo, usar Inertia::location() se estiver usando Inertia.js,
            // ou retornar uma view de erro específica.
            return redirect()->back()
                ->with('error', 'Você não tem permissão para acessar esta rota.');
        }

        // 6) Disponibiliza a lista completa de permissões do usuário no objeto Request.
        // Isso pode ser útil se você precisar verificar permissões
        // posteriormente no ciclo de vida da requisição (por exemplo, em controllers ou views).
        $request->attributes->set('permissions', $allPermissions);

        // Se todas as verificações passarem, permite que a requisição continue para o próximo middleware ou controller.
        return $next($request);
    }
}
