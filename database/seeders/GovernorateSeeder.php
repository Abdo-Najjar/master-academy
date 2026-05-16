<?php

namespace Database\Seeders;

use App\Models\Governorate;
use Illuminate\Database\Seeder;

class GovernorateSeeder extends Seeder
{
    public function run(): void
    {
        $governorates = [
            ['ar' => 'القدس', 'en' => 'Jerusalem'],
            ['ar' => 'رام الله والبيرة', 'en' => 'Ramallah'],
            ['ar' => 'الخليل', 'en' => 'Hebron'],
            ['ar' => 'نابلس', 'en' => 'Nablus'],
            ['ar' => 'جنين', 'en' => 'Jenin'],
            ['ar' => 'بيت لحم', 'en' => 'Bethlehem'],
            ['ar' => 'طولكرم', 'en' => 'Tulkarm'],
            ['ar' => 'قلقيلية', 'en' => 'Qalqilya'],
            ['ar' => 'أريحا', 'en' => 'Jericho'],
            ['ar' => 'سلفيت', 'en' => 'Salfit'],
            ['ar' => 'طوباس', 'en' => 'Tubas'],
            ['ar' => 'غزة', 'en' => 'Gaza'],
        ];

        foreach ($governorates as $name) {
            Governorate::query()->firstOrCreate(
                ['name->en' => $name['en']],
                ['name' => $name]
            );
        }
    }
}
