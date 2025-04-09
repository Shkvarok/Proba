<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            ['name' => 'Україна', 'phone_code' => '+380']
        ];

        foreach ($countries as $country) {
            Country::create($country);
        }
    }
}