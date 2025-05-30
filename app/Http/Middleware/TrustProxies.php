<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    // aceitar todos os proxies (dentro do Docker)
    protected $proxies = '*';

    // pegar todos os headers X-Forwarded-*
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
