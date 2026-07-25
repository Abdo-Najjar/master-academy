<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\City;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $governorate = Governorate::query()->where('name->en', 'Deir al-Balah')->first();
        if (! $governorate) {
            return;
        }

        $city = City::query()
            ->where('name->en', 'Deir al-Balah Camp')
            ->where('governorate_id', $governorate->id)
            ->first();
        if (! $city) {
            return;
        }

        Branch::query()->firstOrCreate(
            ['name->en' => 'Main Branch'],
            [
                'name' => ['ar' => 'الفرع الرئيسي', 'en' => 'Main Branch'],
                'governorate_id' => $governorate->id,
                'city_id' => $city->id,
            ]
        );
    }
}
