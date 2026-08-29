<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\File;
use App\Models\FileCategory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $departments = collect([
            'Registry',
            'Finance',
            'Human Resources',
            'Procurement',
            'Legal',
        ])->mapWithKeys(fn (string $name) => [
            $name => Department::updateOrCreate(['name' => $name], ['parent_id' => null]),
        ]);

        $admin = User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'System Administrator'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'role' => 'admin',
                'department_id' => null,
                'is_active' => true,
            ]
        );

        $users = collect([
            [
                'name' => 'Amina Yusuf',
                'email' => 'registry@example.com',
                'role' => 'registry_staff',
                'department' => 'Registry',
            ],
            [
                'name' => 'David Okafor',
                'email' => 'finance@example.com',
                'role' => 'department_staff',
                'department' => 'Finance',
            ],
            [
                'name' => 'Grace Mensah',
                'email' => 'hr@example.com',
                'role' => 'department_staff',
                'department' => 'Human Resources',
            ],
            [
                'name' => 'Samuel Adeyemi',
                'email' => 'procurement@example.com',
                'role' => 'department_staff',
                'department' => 'Procurement',
            ],
            [
                'name' => 'Nkechi Bello',
                'email' => 'legal@example.com',
                'role' => 'department_staff',
                'department' => 'Legal',
            ],
            [
                'name' => 'Fatima Musa',
                'email' => 'supervisor@example.com',
                'role' => 'supervisor',
                'department' => 'Registry',
            ],
        ])->mapWithKeys(function (array $user) use ($departments) {
            return [
                $user['email'] => User::updateOrCreate(
                    ['email' => $user['email']],
                    [
                        'name' => $user['name'],
                        'password' => Hash::make('password'),
                        'role' => $user['role'],
                        'department_id' => $departments[$user['department']]->id,
                        'is_active' => true,
                    ]
                ),
            ];
        });

        $categories = collect([
            ['name' => 'Finance', 'default_due_days' => 7],
            ['name' => 'HR', 'default_due_days' => 10],
            ['name' => 'Procurement', 'default_due_days' => 14],
            ['name' => 'Legal', 'default_due_days' => 14],
            ['name' => 'General Registry', 'default_due_days' => 5],
        ])->mapWithKeys(fn (array $category) => [
            $category['name'] => FileCategory::updateOrCreate(
                ['name' => $category['name']],
                ['default_due_days' => $category['default_due_days']]
            ),
        ]);

        $registryUser = $users['registry@example.com'];

        collect([
            ['REG/2026/001', 'General Administrative Correspondence', 'General Registry', 'Registry', 'registry@example.com'],
            ['FIN/2026/002', 'Q1 Budget Release Request', 'Finance', 'Finance', 'finance@example.com'],
            ['HR/2026/003', 'Staff Promotion Review File', 'HR', 'Human Resources', 'hr@example.com'],
            ['PROC/2026/004', 'Office Equipment Procurement File', 'Procurement', 'Procurement', 'procurement@example.com'],
            ['LEG/2026/005', 'Contract Review for Vendor Agreement', 'Legal', 'Legal', 'legal@example.com'],
            ['REG/2026/006', 'Board Meeting Registry File', 'General Registry', 'Registry', 'supervisor@example.com'],
            ['FIN/2026/007', 'Vendor Payment Authorization', 'Finance', 'Finance', 'finance@example.com'],
            ['HR/2026/008', 'Training Nomination Records', 'HR', 'Human Resources', 'hr@example.com'],
            ['PROC/2026/009', 'Vehicle Maintenance Procurement', 'Procurement', 'Procurement', 'procurement@example.com'],
            ['LEG/2026/010', 'Policy Compliance Legal Opinion', 'Legal', 'Legal', 'legal@example.com'],
        ])->each(function (array $file) use ($categories, $departments, $users, $registryUser) {
            [$fileNumber, $title, $category, $department, $holderEmail] = $file;

            File::updateOrCreate(
                ['file_number' => $fileNumber],
                [
                    'title' => $title,
                    'description' => "Initial registered physical file for {$title}.",
                    'category_id' => $categories[$category]->id,
                    'confirmed_department_id' => $departments[$department]->id,
                    'confirmed_holder_user_id' => $users[$holderEmail]->id,
                    'status' => File::STATUS_ACTIVE,
                    'registered_by_user_id' => $registryUser->id,
                    'registered_at' => now(),
                ]
            );
        });

        $admin->tokens()->delete();
    }
}
