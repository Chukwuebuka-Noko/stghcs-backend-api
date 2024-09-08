<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            [
                'city' => 'Alexandria',
                'zip_code' => '71301',
                'address1' => '123 Pine St',
                'address2' => null,
                'coordinate' => json_encode(['lat' => 31.3113, 'long' => -92.4451]),
            ],
            [
                'city' => 'Shreveport',
                'zip_code' => '71101',
                'address1' => '456 Elm St',
                'address2' => 'Suite 2',
                'coordinate' => json_encode(['lat' => 32.5110, 'long' => -93.7502]),
            ]
        ];

        $reward = [
            [
                'name' => 'Setup Completion',
                'points' => 30
            ],
            [
                'name' => 'Gig Completion',
                'points' => 90
            ]
        ];

        DB::table('locations')->insert($locations);
        DB::table('reward_points')->insert($reward);
    }
}
