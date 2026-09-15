<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'sales' => ['name' => 'Hana', 'username' => 'hana'],
            'procurement' => ['name' => 'Safira', 'username' => 'safira'],
            'operational' => ['name' => 'Aini', 'username' => 'aini'],
            'technician' => ['name' => 'Riky', 'username' => 'riky'],
            'finance' => ['name' => 'Farah', 'username' => 'farah'],
            'management' => ['name' => 'Pak Adi', 'username' => 'pakadi'],
            'administrator' => ['name' => 'Administrator', 'username' => 'administrator'],
            'project_manager' => ['name' => 'project manager', 'username' => 'projectmanager'],
            'hr' => ['name' => 'Ferdina', 'username' => 'ferdina'],
            'warehouse' => ['name' => 'warehouse', 'username' => 'warehouse'],
        ];

        foreach ($roles as $role => $identity) {
            User::updateOrCreate(
                ['email' => "{$role}@gscrm.test"],
                [
                    'name' => $identity['name'],
                    'username' => $identity['username'],
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
                'username' => 'vendor',
                'password' => Hash::make('password'),
                'role' => 'vendor',
                'vendor_id' => $vendor->id,
                'is_active' => true,
            ],
        );
    }
}
