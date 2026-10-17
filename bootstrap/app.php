<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Harmlose Anzeige-Einstellung (list|tiles, wird beim Lesen geprüft) – bleibt unverschlüsselt lesbar.
        $middleware->encryptCookies(except: ['licenses_view']);

        // Hinter einem Reverse Proxy (Nginx Proxy Manager, Traefik, …): X-Forwarded-* vertrauen,
        // damit Links/Assets https:// verwenden und die echte Client-IP (Rate-Limit) ankommt.
        // TRUSTED_PROXIES: kommagetrennte IPs/CIDRs, "*" = alle. Standard: private Netze.
        $middleware->trustProxies(
            at: array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', '127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,fc00::/7')))) ?: null,
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
