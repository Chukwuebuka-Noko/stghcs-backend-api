<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Manager', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Supervisor', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'HR', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'DSW', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'CSP', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]
        ];

        DB::table('roles')->insert($roles);

    }
}
