<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrainerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('trainer')->check()) {
            return redirect()->route('trainer.login');
        }

        return $next($request);
    }
}
