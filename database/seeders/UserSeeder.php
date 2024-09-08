<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone_number' => '555-0100',
                'location_id' => 1,  // Assuming a location with ID 1 exists
                'gender' => 'male',
                'address1' => '123 Main St',
                'email_verified_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'city' => 'Anytown',
                'zip_code' => '12345',
                'dob' => Carbon::parse('1980-01-01'),
                'employee_id' => 'EMP001',
                'is_temporary_password' => false,
                'password' => Hash::make('Valexjnr5055@@@'),
                'status' => 'active',
            ]
        ];

        DB::table('users')->insert($users);
    }
}
