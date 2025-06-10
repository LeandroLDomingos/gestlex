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
        // Adicione as suas rotas de geração de documentos aqui
        'processos/*/documentos/gerar/aposentadoria',
        'processos/*/documentos/gerar/procuracao',
    ];
}
