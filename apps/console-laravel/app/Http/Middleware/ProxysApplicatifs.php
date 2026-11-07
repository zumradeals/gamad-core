<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Même configuration compilable que la validation, aucune confiance implicite d'hébergeur. */
final class ProxysApplicatifs extends \Illuminate\Http\Middleware\TrustProxies
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('gamad_domains.enabled')) {
            return parent::handle($request, $next);
        }
        Request::setTrustedProxies(config('gamad_domains.trusted_proxies', []),
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO);
        return $next($request);
    }
}
