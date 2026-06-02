<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\StudentAuth;
use App\Http\Middleware\TrainerAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'trainer.auth' => TrainerAuth::class,
            'student.auth' => StudentAuth::class,
            'active.user' => EnsureUserIsActive::class,
        ]);
        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
