<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        DB::table('users')->insert([

            // SUPERADMIN
            [
                'role_id' => 1,
                'full_name' => 'Super Administrator',
                'username' => 'superadmin',
                'email' => 'superadmin@example.com',
                'password' => Hash::make('password'),
                'is_default_password' => false,
                'user_status' => 'active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],

            // ADMIN
            [
                'role_id' => 2,
                'full_name' => 'Administrator',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'is_default_password' => false,
                'user_status' => 'active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],

            // PIC DESIGN
            [
                'role_id' => 3,
                'full_name' => 'PIC Design',
                'username' => 'pic_design',
                'email' => 'picdesign@example.com',
                'password' => Hash::make('password'),
                'is_default_password' => false,
                'user_status' => 'active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],

            // PIC PRODUCTION
            [
                'role_id' => 4,
                'full_name' => 'PIC Production',
                'username' => 'pic_production',
                'email' => 'picproduction@example.com',
                'password' => Hash::make('password'),
                'is_default_password' => false,
                'user_status' => 'active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
