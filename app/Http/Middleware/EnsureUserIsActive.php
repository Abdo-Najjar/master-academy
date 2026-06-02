<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * If the currently-authenticated user on any guard (web / student / trainer)
 * has been deactivated (`is_active = false`), force a logout and redirect to
 * the appropriate login screen with a flash message.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['web', 'student', 'trainer'] as $guard) {
            $user = Auth::guard($guard)->user();
            if (! $user) {
                continue;
            }

            // Only relevant for models that carry the column; getAttribute returns null otherwise.
            $isActive = $user->getAttribute('is_active');
            if ($isActive === null || $isActive === true || $isActive === 1) {
                continue;
            }

            Auth::guard($guard)->logout();
            $request->session()?->invalidate();
            $request->session()?->regenerateToken();

            $route = match ($guard) {
                'student' => 'student.login',
                'trainer' => 'trainer.login',
                default => null,
            };

            $redirect = $route && Route::has($route) ? route($route) : url('/');

            return redirect($redirect)->withErrors([
                'username' => __('Your account has been disabled. Please contact administration.'),
            ]);
        }

        return $next($request);
    }
}
