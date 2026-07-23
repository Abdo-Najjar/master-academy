<?php

namespace Database\Seeders;

use App\Models\CourseType;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $courseTypeId = CourseType::query()->where('name->en', 'General')->value('id');

        $subjects = [
            ['ar' => 'رياضيات', 'en' => 'Math', 'color' => '#0EA5E9'],
            ['ar' => 'فيزياء', 'en' => 'Physics', 'color' => '#F97316'],
            ['ar' => 'كيمياء', 'en' => 'Chemistry', 'color' => '#22C55E'],
            ['ar' => 'علوم', 'en' => 'Science', 'color' => '#A855F7'],
            ['ar' => 'لغة عربية', 'en' => 'Arabic', 'color' => '#EAB308'],
            ['ar' => 'لغة إنجليزية', 'en' => 'English', 'color' => '#EF4444'],
            ['ar' => 'تكنولوجيا', 'en' => 'Technology', 'color' => '#6366F1'],
        ];

        foreach ($subjects as $i => $name) {
            Subject::query()->firstOrCreate(
                ['name->en' => $name['en']],
                [
                    'name' => ['ar' => $name['ar'], 'en' => $name['en']],
                    'color' => $name['color'],
                    'sort_order' => $i + 1,
                    'course_type_id' => $courseTypeId,
                ]
            );
        }
    }
}
