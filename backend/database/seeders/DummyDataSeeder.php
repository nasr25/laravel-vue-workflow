<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        \App\Models\DepartmentManager::truncate();
        \App\Models\IdeaApproval::truncate();
        \App\Models\Idea::truncate();
        \App\Models\Department::truncate();
        \App\Models\User::where('id', '>', 0)->delete();

        // Get roles
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $userRole = \App\Models\Role::where('name', 'user')->first();
        $managerRole = \App\Models\Role::where('name', 'manager')->first();

        // Create Admin
        $admin = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('12345'),
            'role_id' => $adminRole->id,
        ]);
        echo "✓ Created Admin: admin@test.com / 12345\n";

        // Create Regular User
        $user = \App\Models\User::create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('12345'),
            'role_id' => $userRole->id,
        ]);
        echo "✓ Created User: user@test.com / 12345\n";

        // Create 4 Departments
        $deptA = \App\Models\Department::create([
            'name' => 'Department A',
            'description' => 'First approval department',
            'approval_order' => 1,
            'is_active' => true,
        ]);

        $deptB = \App\Models\Department::create([
            'name' => 'Department B',
            'description' => 'Second approval department',
            'approval_order' => 2,
            'is_active' => true,
        ]);

        $deptC = \App\Models\Department::create([
            'name' => 'Department C',
            'description' => 'Third approval department',
            'approval_order' => 3,
            'is_active' => true,
        ]);

        $deptD = \App\Models\Department::create([
            'name' => 'Department D',
            'description' => 'Fourth approval department',
            'approval_order' => 4,
            'is_active' => true,
        ]);
        echo "✓ Created 4 Departments (A, B, C, D)\n";

        // Create 4 Managers
        $managerA = \App\Models\User::create([
            'name' => 'Manager A',
            'email' => 'managera@test.com',
            'password' => bcrypt('12345'),
            'role_id' => $managerRole->id,
        ]);

        $managerB = \App\Models\User::create([
            'name' => 'Manager B',
            'email' => 'managerb@test.com',
            'password' => bcrypt('12345'),
            'role_id' => $managerRole->id,
        ]);

        $managerC = \App\Models\User::create([
            'name' => 'Manager C',
            'email' => 'managerc@test.com',
            'password' => bcrypt('12345'),
            'role_id' => $managerRole->id,
        ]);

        $managerD = \App\Models\User::create([
            'name' => 'Manager D',
            'email' => 'managerd@test.com',
            'password' => bcrypt('12345'),
            'role_id' => $managerRole->id,
        ]);
        echo "✓ Created 4 Managers (managera, b, c, d @test.com)\n";

        // Assign Managers to Departments
        \App\Models\DepartmentManager::create([
            'department_id' => $deptA->id,
            'user_id' => $managerA->id,
        ]);

        \App\Models\DepartmentManager::create([
            'department_id' => $deptB->id,
            'user_id' => $managerB->id,
        ]);

        \App\Models\DepartmentManager::create([
            'department_id' => $deptC->id,
            'user_id' => $managerC->id,
        ]);

        \App\Models\DepartmentManager::create([
            'department_id' => $deptD->id,
            'user_id' => $managerD->id,
        ]);
        echo "✓ Assigned managers to departments\n";

        echo "\n=== Dummy Data Created Successfully! ===\n";
        echo "All passwords: 12345\n\n";
        echo "Accounts:\n";
        echo "  Admin:     admin@test.com\n";
        echo "  User:      user@test.com\n";
        echo "  Manager A: managera@test.com\n";
        echo "  Manager B: managerb@test.com\n";
        echo "  Manager C: managerc@test.com\n";
        echo "  Manager D: managerd@test.com\n";
    }
}
