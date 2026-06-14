<?php

use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\Trainer;
use App\Models\User;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'trainer' => [
            'driver' => 'session',
            'provider' => 'trainers',
        ],
        'student' => [
            'driver' => 'session',
            'provider' => 'students',
        ],
        'parent' => [
            'driver' => 'session',
            'provider' => 'parents',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
        'trainers' => [
            'driver' => 'eloquent',
            'model' => Trainer::class,
        ],
        'students' => [
            'driver' => 'eloquent',
            'model' => Student::class,
        ],
        'parents' => [
            'driver' => 'eloquent',
            'model' => ParentGuardian::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
        'trainers' => [
            'provider' => 'trainers',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'students' => [
            'provider' => 'students',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'parents' => [
            'provider' => 'parents',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
