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
                'city' => 'Anytown',
                'zip_code' => '12345',
                'dob' => Carbon::parse('1980-01-01'),
                'employee_id' => 'EMP001',
                'role_id' => 1,  // Assuming a role with ID 1 exists
                'password' => Hash::make('123456789'),
                'status' => 'active',
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@example.com',
                'phone_number' => '555-0101',
                'location_id' => 2,  // Assuming a location with ID 2 exists
                'gender' => 'female',
                'address1' => '456 Side St',
                'city' => 'Othertown',
                'zip_code' => '54321',
                'dob' => Carbon::parse('1985-05-05'),
                'employee_id' => 'EMP002',
                'role_id' => 2,  // Assuming a role with ID 2 exists
                'password' => Hash::make('123456789'),
                'status' => 'active',
            ]
        ];

        DB::table('users')->insert($users);
    }
}
