<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed one dummy user per role for local testing.
     */
    public function run(): void
    {
        $roles = [
            'sales',
            'procurement',
            'operational',
            'technician',
            'finance',
            'management',
            'administrator',
        ];

        foreach ($roles as $role) {
            User::updateOrCreate(
                ['email' => "{$role}@gscrm.test"],
                [
                    'name' => ucfirst($role).' User',
                    'password' => Hash::make('password'),
                    'role' => $role,
                    'is_active' => true,
                ],
            );
        }
    }
}
