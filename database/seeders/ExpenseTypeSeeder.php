<?php

namespace Database\Seeders;

use App\Models\ExpenseType;
use Illuminate\Database\Seeder;

class ExpenseTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['ar' => 'إيجار مكان', 'en' => 'Venue Rent'],
            ['ar' => 'فواتير كهرباء ومياه', 'en' => 'Utilities'],
            ['ar' => 'رواتب', 'en' => 'Salaries'],
            ['ar' => 'قرطاسية ومستلزمات', 'en' => 'Stationery & Supplies'],
            ['ar' => 'صيانة', 'en' => 'Maintenance'],
            ['ar' => 'دعاية وإعلان', 'en' => 'Marketing'],
            ['ar' => 'مصروفات أخرى', 'en' => 'Other Expenses'],
        ];

        foreach ($types as $name) {
            // Match on the Arabic value so re-seeding stays idempotent.
            if (! ExpenseType::query()->where('name->ar', $name['ar'])->exists()) {
                ExpenseType::create(['name' => $name]);
            }
        }
    }
}
