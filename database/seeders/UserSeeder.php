<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vendor;
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
            'project_manager',
            'hr',
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

        $vendor = Vendor::firstOrCreate(
            ['name' => 'Vendor Teknisi Dummy'],
            ['provides_technical' => true, 'contact_person' => 'PIC Vendor Dummy', 'phone' => '081200000000'],
        );

        User::updateOrCreate(
            ['email' => 'vendor@gscrm.test'],
            [
                'name' => 'Vendor User',
                'password' => Hash::make('password'),
                'role' => 'vendor',
                'vendor_id' => $vendor->id,
                'is_active' => true,
            ],
        );
    }
}
