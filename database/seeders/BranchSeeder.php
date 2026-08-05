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
        $branches = [
            [
                'governorate' => 'Deir al-Balah',
                'city' => 'Deir al-Balah',
                'name' => ['ar' => 'دير البلح', 'en' => 'Deir al-Balah'],
                'address' => [
                    'ar' => 'البلد — مقابل مدرسة العائشية، داخل مركز منبع التميز',
                    'en' => 'Town center — opposite Al-Aishiya School, inside Manba Al-Tamayoz Center',
                ],
                'sort_order' => 1,
            ],
            [
                'governorate' => 'Gaza',
                'city' => 'Al-Rimal',
                'name' => ['ar' => 'غزة', 'en' => 'Gaza'],
                'address' => [
                    'ar' => 'الرمال — سيزون مول، عمارة مصباح، الطابق الأول',
                    'en' => 'Al-Rimal — Season Mall, Misbah Building, first floor',
                ],
                'sort_order' => 2,
            ],
            [
                'governorate' => 'Khan Yunis',
                'city' => 'Khan Yunis',
                'name' => ['ar' => 'خانيونس', 'en' => 'Khan Yunis'],
                'address' => [
                    'ar' => 'منطقة شاطئ البحر — كافيه الخليج',
                    'en' => 'Beach area — Al-Khaleej Cafe',
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($branches as $branch) {
            $governorate = Governorate::query()->where('name->en', $branch['governorate'])->first();

            if (! $governorate) {
                continue;
            }

            $city = City::query()
                ->where('name->en', $branch['city'])
                ->where('governorate_id', $governorate->id)
                ->first();

            if (! $city) {
                continue;
            }

            Branch::query()->firstOrCreate(
                ['name->en' => $branch['name']['en']],
                [
                    'name' => $branch['name'],
                    'address' => $branch['address'],
                    'governorate_id' => $governorate->id,
                    'city_id' => $city->id,
                    'sort_order' => $branch['sort_order'],
                    'show_on_site' => true,
                ]
            );
        }
    }
}
