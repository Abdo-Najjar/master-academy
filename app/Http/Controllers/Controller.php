<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Authorize a HexaLite permission gate outside the Filament panel context.
     *
     * hexa()->can() falls back to Gate::allows(), whose abilities are only
     * registered while a Filament panel is booted — so it always denies on
     * plain web routes. Here we read the authenticated user's role access
     * (a group-keyed array of gates) directly and flatten it.
     */
    protected function authorizeHexaGate(\Illuminate\Http\Request $request, string $gate): void
    {
        $user = $request->user();

        // Super admin (User #1) bypasses all gates.
        if ($user instanceof \App\Models\User && (int) $user->getKey() === 1) {
            return;
        }

        $allowed = $user && collect($user->roles)
            ->flatMap(fn ($role) => is_array($role->access) ? \Illuminate\Support\Arr::flatten($role->access) : [])
            ->contains($gate);

        abort_unless($allowed, 403);
    }
}
