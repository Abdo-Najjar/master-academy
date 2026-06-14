<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ParentAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('parent')->check()) {
            return redirect()->route('parent.login');
        }

        return $next($request);
    }
}
