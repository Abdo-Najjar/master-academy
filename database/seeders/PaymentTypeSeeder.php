<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Cash', 'Bank Transfer', 'Credit Card', 'Mobile Wallet'] as $name) {
            PaymentType::query()->firstOrCreate(['name' => $name]);
        }
    }
}
