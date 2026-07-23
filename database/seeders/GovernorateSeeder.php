<?php

namespace Database\Seeders;

use App\Models\Governorate;
use Illuminate\Database\Seeder;

class GovernorateSeeder extends Seeder
{
    public function run(): void
    {
        $governorates = [
            ['ar' => 'شمال غزة', 'en' => 'North Gaza'],
            ['ar' => 'غزة', 'en' => 'Gaza'],
            ['ar' => 'دير البلح', 'en' => 'Deir al-Balah'],
            ['ar' => 'خان يونس', 'en' => 'Khan Yunis'],
            ['ar' => 'رفح', 'en' => 'Rafah'],
        ];

        foreach ($governorates as $name) {
            Governorate::query()->firstOrCreate(
                ['name->en' => $name['en']],
                ['name' => $name]
            );
        }
    }
}
