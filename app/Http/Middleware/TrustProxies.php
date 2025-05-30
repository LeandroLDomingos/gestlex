<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    // aceitar todos os proxies (dentro do Docker)
    protected $proxies = '*';

     protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO | // Certifique-se que este está aqui
        Request::HEADER_X_FORWARDED_AWS_ELB; // Ou Request::HEADER_X_FORWARDED_PREFIX nas versões mais novas
}
