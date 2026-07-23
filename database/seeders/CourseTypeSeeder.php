<?php

namespace Database\Seeders;

use App\Models\CourseType;
use Illuminate\Database\Seeder;

class CourseTypeSeeder extends Seeder
{
    public function run(): void
    {
        CourseType::query()->firstOrCreate(
            ['name->en' => 'General'],
            ['name' => ['ar' => 'عام', 'en' => 'General'], 'sort_order' => 0]
        );
    }
}
