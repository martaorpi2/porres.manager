<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        '/webhooks',
    ];

    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function tokensMatch($request)
    {
        // Si es una petición AJAX, verificar headers y parámetros
        if ($request->ajax() || $request->expectsJson()) {
            // Intentar obtener el token del header X-CSRF-TOKEN
            $token = $request->header('X-CSRF-TOKEN') ?: $request->input('_token');
            
            if ($token && $request->session()->token()) {
                return hash_equals(
                    $request->session()->token(),
                    $token
                );
            }
        }

        return parent::tokensMatch($request);
    }
}
