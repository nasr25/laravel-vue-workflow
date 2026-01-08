<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions
        $permissions = [
            // Request Permissions
            'request.view-own',           // View own requests
            'request.view-all',           // View all requests (admin)
            'request.create',             // Create new request
            'request.edit-own',           // Edit own request
            'request.edit-all',           // Edit any request (admin)
            'request.delete-own',         // Delete own request
            'request.delete-all',         // Delete any request (admin)
            'request.submit',             // Submit request for review
            'request.resubmit',           // Resubmit request after need-more-details

            // Workflow Permissions (Department A)
            'workflow.view-pending',      // View pending requests
            'workflow.assign-path',       // Assign workflow path
            'workflow.complete-request',  // Complete request
            'workflow.reject-request',    // Reject request
            'workflow.request-details',   // Request more details
            'workflow.return-request',    // Return to previous department
            'workflow.evaluate',          // Create/edit evaluation

            // Department Workflow Permissions
            'department.view-requests',   // View department requests
            'department.assign-employee', // Assign request to employee (manager)
            'department.process-request', // Process request (employee)
            'department.return-to-manager', // Return to manager
            'department.move-to-next',    // Move to next department (manager)
            'department.return-to-dept-a', // Return to Department A

            // User Management Permissions
            'user.view',                  // View users
            'user.create',                // Create users
            'user.edit',                  // Edit users
            'user.delete',                // Delete users
            'user.assign-role',           // Assign roles to users
            'user.assign-department',     // Assign users to departments

            // Department Management Permissions
            'department.view',            // View departments
            'department.create',          // Create departments
            'department.edit',            // Edit departments
            'department.delete',          // Delete departments

            // Role & Permission Management
            'role.view',                  // View roles
            'role.create',                // Create roles
            'role.edit',                  // Edit roles
            'role.delete',                // Delete roles
            'permission.view',            // View permissions
            'permission.assign',          // Assign permissions to roles

            // Evaluation Question Management
            'evaluation.view-questions',  // View evaluation questions
            'evaluation.create-question', // Create evaluation questions
            'evaluation.edit-question',   // Edit evaluation questions
            'evaluation.delete-question', // Delete evaluation questions
            'evaluation.view-results',    // View evaluation results

            // Workflow Path Management
            'workflow-path.view',         // View workflow paths
            'workflow-path.create',       // Create workflow paths
            'workflow-path.edit',         // Edit workflow paths
            'workflow-path.delete',       // Delete workflow paths

            // Reports & Analytics
            'report.view',                // View reports
            'report.export',              // Export reports

            // System Settings
            'settings.view',              // View settings
            'settings.edit',              // Edit settings
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        // Create roles and assign permissions

        // 1. Super Admin Role - Has all permissions
        $superAdmin = Role::create(['name' => 'Super Admin', 'guard_name' => 'sanctum']);
        $superAdmin->givePermissionTo(Permission::all());

        // 2. Admin Role - Full system access except system settings
        $admin = Role::create(['name' => 'Admin', 'guard_name' => 'sanctum']);
        $admin->givePermissionTo([
            'request.view-own',
            'request.view-all',
            'request.create',
            'request.edit-own',
            'request.edit-all',
            'request.delete-own',
            'request.delete-all',
            'request.submit',
            'request.resubmit',
            'user.view',
            'user.create',
            'user.edit',
            'user.delete',
            'user.assign-role',
            'user.assign-department',
            'department.view',
            'department.create',
            'department.edit',
            'department.delete',
            'evaluation.view-questions',
            'evaluation.create-question',
            'evaluation.edit-question',
            'evaluation.delete-question',
            'evaluation.view-results',
            'workflow-path.view',
            'workflow-path.create',
            'workflow-path.edit',
            'workflow-path.delete',
            'report.view',
            'report.export',
        ]);

        // 3. Department A Manager Role
        $deptAManager = Role::create(['name' => 'Supervisor', 'guard_name' => 'sanctum']);
        $deptAManager->givePermissionTo([
            'request.view-own',
            'request.view-all',
            'request.create',
            'request.edit-own',
            'request.delete-own',
            'request.submit',
            'request.resubmit',
            'workflow.view-pending',
            'workflow.assign-path',
            'workflow.complete-request',
            'workflow.reject-request',
            'workflow.request-details',
            'workflow.return-request',
            'workflow.evaluate',
            'evaluation.view-questions',
            'evaluation.view-results',
            'report.view',
        ]);

        // 4. Department Manager Role
        $deptManager = Role::create(['name' => 'Manager', 'guard_name' => 'sanctum']);
        $deptManager->givePermissionTo([
            'request.view-own',
            'request.create',
            'request.edit-own',
            'request.delete-own',
            'request.submit',
            'request.resubmit',
            'department.view-requests',
            'department.assign-employee',
            'department.move-to-next',
            'department.return-to-dept-a',
            'report.view',
        ]);

        // 5. Department Employee Role
        $deptEmployee = Role::create(['name' => 'Employee', 'guard_name' => 'sanctum']);
        $deptEmployee->givePermissionTo([
            'request.view-own',
            'request.create',
            'request.edit-own',
            'request.delete-own',
            'request.submit',
            'request.resubmit',
            'department.view-requests',
            'department.process-request',
            'department.return-to-manager',
        ]);

        // 6. Regular User Role
        $user = Role::create(['name' => 'User', 'guard_name' => 'sanctum']);
        $user->givePermissionTo([
            'request.view-own',
            'request.create',
            'request.edit-own',
            'request.delete-own',
            'request.submit',
            'request.resubmit',
        ]);

        // Assign roles to existing users
        $this->assignRolesToUsers();

        $this->command->info('Roles and permissions created successfully!');
    }

    /**
     * Assign roles to existing users based on their current role field
     */
    private function assignRolesToUsers()
    {
        $users = User::all();

        foreach ($users as $user) {
            // Remove any existing roles
            $user->roles()->detach();

            // Assign role based on current role field
            switch ($user->role) {
                case 'admin':
                    $user->assignRole('Admin');
                    break;

                case 'manager':
                    // Check if user is in Department A
                    $isDeptAManager = $user->departments()
                        ->where('is_department_a', true)
                        ->wherePivot('role', 'manager')
                        ->exists();

                    if ($isDeptAManager) {
                        $user->assignRole('Supervisor');
                    } else {
                        $user->assignRole('Manager');
                    }
                    break;

                case 'employee':
                    $user->assignRole('Employee');
                    break;

                case 'user':
                default:
                    $user->assignRole('User');
                    break;
            }
        }

        $this->command->info('Roles assigned to existing users!');
    }
}
