<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Adiciona as rotas de geração de documentos aqui para evitar o erro 419.
        // O asterisco (*) funciona como um wildcard para qualquer ID de processo.
        'processos/*/documentos/aposentadoria/gerar',
        'processos/*/documentos/procuracao/gerar',
        'processos/*/documentos/declaracao/gerar',
    ];
}
