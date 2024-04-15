<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'create role', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'update role', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view role', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'delete role', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'assign role', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'create permission', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view permission', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'update permission', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'delete permission', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()], 
            ['name' => 'create user', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view user', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'update user', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'delete user', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'create gigs', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view gigs', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'update gigs', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'delete gigs', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'assign gigs', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'unassign gigs', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'create products', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view products', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'update products', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'delete products', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'create locations', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view locations', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'update locations', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'delete locations', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'create client', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view client', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'update client', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'delete client', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'create category', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view category', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'update category', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'delete category', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'create schedule', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view schedule', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'update schedule', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'delete schedule', 'guard_name' => 'api', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('permissions')->insert($permissions);
    }
}
