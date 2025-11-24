<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Department;
use App\Models\WorkflowPath;
use App\Models\WorkflowPathStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Admin User
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create Regular User
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'user@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_active' => true,
        ]);

        // Create Department A (Initial Review Department)
        $deptA = Department::create([
            'name' => 'Department A - Initial Review',
            'code' => 'DEPT_A',
            'description' => 'Handles initial request review and routing',
            'is_department_a' => true,
            'is_active' => true,
        ]);

        // Create Manager for Department A
        $managerA = User::create([
            'name' => 'Alice Manager',
            'email' => 'manager.a@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'is_active' => true,
        ]);
        $deptA->users()->attach($managerA->id, ['role' => 'manager']);

        // Create other departments
        $deptB = Department::create([
            'name' => 'Technical Department',
            'code' => 'TECH',
            'description' => 'Technical review and approval',
            'is_active' => true,
        ]);

        $deptC = Department::create([
            'name' => 'Finance Department',
            'code' => 'FIN',
            'description' => 'Financial review and budget approval',
            'is_active' => true,
        ]);

        $deptD = Department::create([
            'name' => 'Legal Department',
            'code' => 'LEGAL',
            'description' => 'Legal compliance review',
            'is_active' => true,
        ]);

        $deptE = Department::create([
            'name' => 'Operations Department',
            'code' => 'OPS',
            'description' => 'Operations and logistics',
            'is_active' => true,
        ]);

        $deptF = Department::create([
            'name' => 'HR Department',
            'code' => 'HR',
            'description' => 'Human resources review',
            'is_active' => true,
        ]);

        // Create managers for each department
        $managerB = User::create([
            'name' => 'Bob Tech Manager',
            'email' => 'manager.tech@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'is_active' => true,
        ]);
        $deptB->users()->attach($managerB->id, ['role' => 'manager']);

        $managerC = User::create([
            'name' => 'Carol Finance Manager',
            'email' => 'manager.finance@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'is_active' => true,
        ]);
        $deptC->users()->attach($managerC->id, ['role' => 'manager']);

        $managerD = User::create([
            'name' => 'David Legal Manager',
            'email' => 'manager.legal@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'is_active' => true,
        ]);
        $deptD->users()->attach($managerD->id, ['role' => 'manager']);

        $managerE = User::create([
            'name' => 'Emma Strategy Manager',
            'email' => 'manager.strategy@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'is_active' => true,
        ]);
        $deptE->users()->attach($managerE->id, ['role' => 'manager']);

        $managerF = User::create([
            'name' => 'Frank HR Manager',
            'email' => 'manager.hr@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'is_active' => true,
        ]);
        $deptF->users()->attach($managerF->id, ['role' => 'manager']);

        // Create employees for each department
        $empTech1 = User::create([
            'name' => 'Tech Employee 1',
            'email' => 'emp.tech1@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $deptB->users()->attach($empTech1->id, ['role' => 'employee']);

        $empTech2 = User::create([
            'name' => 'Tech Employee 2',
            'email' => 'emp.tech2@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $deptB->users()->attach($empTech2->id, ['role' => 'employee']);

        $empFinance = User::create([
            'name' => 'Finance Employee',
            'email' => 'emp.finance@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $deptC->users()->attach($empFinance->id, ['role' => 'employee']);

        $empLegal = User::create([
            'name' => 'Legal Employee',
            'email' => 'emp.legal@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $deptD->users()->attach($empLegal->id, ['role' => 'employee']);

        $empStrategy1 = User::create([
            'name' => 'Strategy Employee 1',
            'email' => 'emp.strategy1@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $deptE->users()->attach($empStrategy1->id, ['role' => 'employee']);

        $empStrategy2 = User::create([
            'name' => 'Strategy Employee 2',
            'email' => 'emp.strategy2@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $deptE->users()->attach($empStrategy2->id, ['role' => 'employee']);

        $empHR1 = User::create([
            'name' => 'HR Employee 1',
            'email' => 'emp.hr1@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $deptF->users()->attach($empHR1->id, ['role' => 'employee']);

        $empHR2 = User::create([
            'name' => 'HR Employee 2',
            'email' => 'emp.hr2@workflow.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $deptF->users()->attach($empHR2->id, ['role' => 'employee']);

        // Create Workflow Path 1: Simple Technical Review
        $path1 = WorkflowPath::create([
            'name' => 'Path 1: Simple Technical Review',
            'code' => 'PATH_1',
            'description' => 'Simple technical review process',
            'department_id' => $deptA->id,
            'order' => 1,
            'is_active' => true,
        ]);

        WorkflowPathStep::create([
            'workflow_path_id' => $path1->id,
            'department_id' => $deptB->id,
            'step_order' => 1,
            'requires_approval' => true,
        ]);

        // Create Workflow Path 2: Financial Approval
        $path2 = WorkflowPath::create([
            'name' => 'Path 2: Financial Approval',
            'code' => 'PATH_2',
            'description' => 'Financial review and budget approval',
            'department_id' => $deptA->id,
            'order' => 2,
            'is_active' => true,
        ]);

        WorkflowPathStep::create([
            'workflow_path_id' => $path2->id,
            'department_id' => $deptC->id,
            'step_order' => 1,
            'requires_approval' => true,
        ]);

        // Create Workflow Path 3: Legal & Technical
        $path3 = WorkflowPath::create([
            'name' => 'Path 3: Legal & Technical Review',
            'code' => 'PATH_3',
            'description' => 'Combined legal and technical review',
            'department_id' => $deptA->id,
            'order' => 3,
            'is_active' => true,
        ]);

        WorkflowPathStep::create([
            'workflow_path_id' => $path3->id,
            'department_id' => $deptB->id,
            'step_order' => 1,
            'requires_approval' => true,
        ]);

        WorkflowPathStep::create([
            'workflow_path_id' => $path3->id,
            'department_id' => $deptD->id,
            'step_order' => 2,
            'requires_approval' => true,
        ]);

        // Create Workflow Path 4: Full Review (All Departments)
        $path4 = WorkflowPath::create([
            'name' => 'Path 4: Complete Multi-Department Review',
            'code' => 'PATH_4',
            'description' => 'Comprehensive review through all departments',
            'department_id' => $deptA->id,
            'order' => 4,
            'is_active' => true,
        ]);

        WorkflowPathStep::create([
            'workflow_path_id' => $path4->id,
            'department_id' => $deptB->id,
            'step_order' => 1,
            'requires_approval' => true,
        ]);

        WorkflowPathStep::create([
            'workflow_path_id' => $path4->id,
            'department_id' => $deptD->id,
            'step_order' => 2,
            'requires_approval' => true,
        ]);

        WorkflowPathStep::create([
            'workflow_path_id' => $path4->id,
            'department_id' => $deptC->id,
            'step_order' => 3,
            'requires_approval' => true,
        ]);

        WorkflowPathStep::create([
            'workflow_path_id' => $path4->id,
            'department_id' => $deptE->id,
            'step_order' => 4,
            'requires_approval' => true,
        ]);

        // Create Workflow Path 5: HR Process
        $path5 = WorkflowPath::create([
            'name' => 'Path 5: HR Process',
            'code' => 'PATH_5',
            'description' => 'Human resources related requests',
            'department_id' => $deptA->id,
            'order' => 5,
            'is_active' => true,
        ]);

        WorkflowPathStep::create([
            'workflow_path_id' => $path5->id,
            'department_id' => $deptF->id,
            'step_order' => 1,
            'requires_approval' => true,
        ]);

        // Create Workflow Path 6: Operations
        $path6 = WorkflowPath::create([
            'name' => 'Path 6: Operations',
            'code' => 'PATH_6',
            'description' => 'Operations and logistics requests',
            'department_id' => $deptA->id,
            'order' => 6,
            'is_active' => true,
        ]);

        WorkflowPathStep::create([
            'workflow_path_id' => $path6->id,
            'department_id' => $deptE->id,
            'step_order' => 1,
            'requires_approval' => true,
        ]);

        // Call the Roles and Permissions Seeder
        $this->call(RolesAndPermissionsSeeder::class);

        $this->command->info('Database seeded successfully!');
        $this->command->info('');
        $this->command->info('Login Credentials:');
        $this->command->info('Admin: admin@workflow.com / password');
        $this->command->info('User: user@workflow.com / password');
        $this->command->info('Manager (Dept A): manager.a@workflow.com / password');
        $this->command->info('Manager (Tech): manager.tech@workflow.com / password');
        $this->command->info('Manager (Finance): manager.finance@workflow.com / password');
        $this->command->info('Manager (Legal): manager.legal@workflow.com / password');
        $this->command->info('Manager (Strategy): manager.strategy@workflow.com / password');
        $this->command->info('Manager (HR): manager.hr@workflow.com / password');
        $this->command->info('Employee (Tech 1): emp.tech1@workflow.com / password');
        $this->command->info('Employee (Tech 2): emp.tech2@workflow.com / password');
        $this->command->info('Employee (Finance): emp.finance@workflow.com / password');
        $this->command->info('Employee (Legal): emp.legal@workflow.com / password');
        $this->command->info('Employee (Strategy 1): emp.strategy1@workflow.com / password');
        $this->command->info('Employee (Strategy 2): emp.strategy2@workflow.com / password');
        $this->command->info('Employee (HR 1): emp.hr1@workflow.com / password');
        $this->command->info('Employee (HR 2): emp.hr2@workflow.com / password');
    }
}
