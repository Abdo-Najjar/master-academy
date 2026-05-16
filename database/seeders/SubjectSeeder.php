<?php

namespace Database\Seeders;

use App\Models\EducationalLevel;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['ar' => 'رياضيات', 'en' => 'Math', 'color' => '#0EA5E9'],
            ['ar' => 'فيزياء', 'en' => 'Physics', 'color' => '#F97316'],
            ['ar' => 'كيمياء', 'en' => 'Chemistry', 'color' => '#22C55E'],
            ['ar' => 'علوم', 'en' => 'Science', 'color' => '#A855F7'],
            ['ar' => 'لغة عربية', 'en' => 'Arabic', 'color' => '#EAB308'],
            ['ar' => 'لغة إنجليزية', 'en' => 'English', 'color' => '#EF4444'],
            ['ar' => 'تكنولوجيا', 'en' => 'Technology', 'color' => '#6366F1'],
        ];

        $level = EducationalLevel::query()->where('name->en', 'Grade 12 (Tawjihi)')->first();

        foreach ($subjects as $i => $name) {
            Subject::query()->firstOrCreate(
                ['name->en' => $name['en']],
                [
                    'name' => ['ar' => $name['ar'], 'en' => $name['en']],
                    'educational_level_id' => $level?->id,
                    'color' => $name['color'],
                    'sort_order' => $i + 1,
                ]
            );
        }
    }
}
