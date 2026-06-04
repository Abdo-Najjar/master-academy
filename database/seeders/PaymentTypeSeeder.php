<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['ar' => 'نقداً', 'en' => 'Cash'],
            ['ar' => 'تحويل بنكي', 'en' => 'Bank Transfer'],
            ['ar' => 'بطاقة ائتمانية', 'en' => 'Credit Card'],
            ['ar' => 'محفظة إلكترونية', 'en' => 'Mobile Wallet'],
        ];

        foreach ($types as $name) {
            // Match on the Arabic value so re-seeding stays idempotent.
            if (! PaymentType::query()->where('name->ar', $name['ar'])->exists()) {
                PaymentType::create(['name' => $name]);
            }
        }
    }
}
