<?php

return [

    'models' => [
        'role' => \Hexters\HexaLite\Models\HexaRole::class,
        'user' => \App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation Configuration
    |--------------------------------------------------------------------------
    |
    | Label + group for the "Roles & Permissions" menu item. The group string is
    | passed through __(), so 'Administration' resolves to the same "الإدارة"
    | group defined in the admin panel's navigationGroups().
    |
    */

    'navigation' => [
        'label' => 'Role & Permissions',
        'group' => 'Administration',
    ],

];
