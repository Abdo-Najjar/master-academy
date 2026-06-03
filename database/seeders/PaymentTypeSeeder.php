<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['نقداً', 'تحويل بنكي', 'بطاقة ائتمانية', 'محفظة إلكترونية'] as $name) {
            PaymentType::query()->firstOrCreate(['name' => $name]);
        }
    }
}
