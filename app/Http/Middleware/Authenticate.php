<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo(Request $request): ?string
    {
        // Jika request mengharapkan response JSON (biasanya untuk API/AJAX),
        // maka tidak perlu redirect, biarkan exception handler mengembalikan JSON 401.
        if ($request->expectsJson()) {
            return null;
        }

        // Untuk request non-JSON (browser biasa), redirect ke halaman login.
        return route('login');
    }
}