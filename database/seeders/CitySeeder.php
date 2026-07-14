<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Ramallah' => [
                ['ar' => 'رام الله', 'en' => 'Ramallah'],
                ['ar' => 'البيرة', 'en' => 'Al-Bireh'],
                ['ar' => 'بيتونيا', 'en' => 'Beitunia'],
            ],
            'Hebron' => [
                ['ar' => 'الخليل', 'en' => 'Hebron'],
                ['ar' => 'دورا', 'en' => 'Dura'],
                ['ar' => 'يطا', 'en' => 'Yatta'],
            ],
            'Nablus' => [
                ['ar' => 'نابلس', 'en' => 'Nablus'],
                ['ar' => 'بيتا', 'en' => 'Beita'],
            ],
            'Jenin' => [
                ['ar' => 'جنين', 'en' => 'Jenin'],
                ['ar' => 'يعبد', 'en' => 'Yabad'],
            ],
            'Bethlehem' => [
                ['ar' => 'بيت لحم', 'en' => 'Bethlehem'],
                ['ar' => 'بيت ساحور', 'en' => 'Beit Sahour'],
            ],
            'Tulkarm' => [
                ['ar' => 'طولكرم', 'en' => 'Tulkarm'],
            ],
            'Jerusalem' => [
                ['ar' => 'القدس', 'en' => 'Jerusalem'],
                ['ar' => 'بيت حنينا', 'en' => 'Beit Hanina'],
            ],
            'North Gaza' => [
                ['ar' => 'جباليا', 'en' => 'Jabalia'],
                ['ar' => 'بيت لاهيا', 'en' => 'Beit Lahia'],
                ['ar' => 'بيت حانون', 'en' => 'Beit Hanoun'],
            ],
            'Gaza' => [
                ['ar' => 'غزة', 'en' => 'Gaza City'],
                ['ar' => 'الزيتون', 'en' => 'Al-Zaytoun'],
                ['ar' => 'الشجاعية', 'en' => 'Al-Shuja\'iyya'],
                ['ar' => 'التفاح', 'en' => 'Al-Tuffah'],
                ['ar' => 'الرمال', 'en' => 'Al-Rimal'],
            ],
            'Deir al-Balah' => [
                ['ar' => 'دير البلح', 'en' => 'Deir al-Balah'],
                ['ar' => 'النصيرات', 'en' => 'An-Nuseirat'],
                ['ar' => 'البريج', 'en' => 'Al-Bureij'],
                ['ar' => 'المغازي', 'en' => 'Al-Maghazi'],
                ['ar' => 'الزوايدة', 'en' => 'Al-Zawayda'],
            ],
            'Khan Yunis' => [
                ['ar' => 'خان يونس', 'en' => 'Khan Yunis'],
                ['ar' => 'عبسان الكبيرة', 'en' => 'Abasan al-Kabira'],
                ['ar' => 'بني سهيلا', 'en' => 'Bani Suheila'],
                ['ar' => 'القرارة', 'en' => 'Al-Qarara'],
            ],
            'Rafah' => [
                ['ar' => 'رفح', 'en' => 'Rafah'],
                ['ar' => 'الشوكة', 'en' => 'Al-Shawka'],
            ],
        ];

        foreach ($data as $govEn => $cities) {
            $governorate = Governorate::query()->where('name->en', $govEn)->first();
            if (! $governorate) {
                continue;
            }
            foreach ($cities as $name) {
                City::query()->firstOrCreate(
                    ['name->en' => $name['en'], 'governorate_id' => $governorate->id],
                    ['name' => $name]
                );
            }
        }
    }
}
