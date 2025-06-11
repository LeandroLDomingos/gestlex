<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request; // Importante
use Illuminate\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * The proxies that should be trusted.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*'; // Para confiar em todos os proxies (comum em Docker)

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO | // Essencial
        Request::HEADER_X_FORWARDED_AWS_ELB; // Ou Request::HEADER_X_FORWARDED_PREFIX em versões mais novas do Laravel
}