<?php

namespace Database\Seeders;

use App\Models\EducationalLevel;
use Illuminate\Database\Seeder;

class EducationalLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['ar' => 'الصف السابع', 'en' => 'Grade 7', 'sort' => 7],
            ['ar' => 'الصف الثامن', 'en' => 'Grade 8', 'sort' => 8],
            ['ar' => 'الصف التاسع', 'en' => 'Grade 9', 'sort' => 9],
            ['ar' => 'الصف العاشر', 'en' => 'Grade 10', 'sort' => 10],
            ['ar' => 'الصف الحادي عشر', 'en' => 'Grade 11', 'sort' => 11],
            ['ar' => 'الثاني عشر (توجيهي)', 'en' => 'Grade 12 (Tawjihi)', 'sort' => 12],
        ];

        foreach ($levels as $level) {
            EducationalLevel::query()->firstOrCreate(
                ['name->en' => $level['en']],
                [
                    'name' => ['ar' => $level['ar'], 'en' => $level['en']],
                    'sort_order' => $level['sort'],
                ]
            );
        }
    }
}
