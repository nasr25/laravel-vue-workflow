<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\FormType;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepApprover;
use App\Models\DepartmentEmployee;
use App\Models\User;
use App\Models\Department;
use App\Models\Role;

class DynamicWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "\n=== Starting Dynamic Workflow Seeding ===\n\n";

        // Get roles
        $employeeRole = Role::where('name', 'employee')->first();
        $managerRole = Role::where('name', 'manager')->first();

        if (!$employeeRole) {
            echo "❌ Error: Employee role not found. Please run migrations first.\n";
            return;
        }

        // Get departments (from DummyDataSeeder)
        $deptA = Department::where('name', 'Department A')->first();
        $deptB = Department::where('name', 'Department B')->first();
        $deptC = Department::where('name', 'Department C')->first();

        if (!$deptA || !$deptB || !$deptC) {
            echo "❌ Error: Departments not found. Please run DummyDataSeeder first.\n";
            return;
        }

        echo "✓ Found existing departments (A, B, C)\n";

        // =============================
        // 1. Create Employee Users
        // =============================
        echo "\n--- Creating Employee Users ---\n";

        $employee1 = User::create([
            'name' => 'Employee 1 - Finance',
            'email' => 'employee1@test.com',
            'password' => bcrypt('12345'),
            'role_id' => $employeeRole->id,
        ]);

        $employee2 = User::create([
            'name' => 'Employee 2 - Finance',
            'email' => 'employee2@test.com',
            'password' => bcrypt('12345'),
            'role_id' => $employeeRole->id,
        ]);

        $employee3 = User::create([
            'name' => 'Employee 3 - Finance',
            'email' => 'employee3@test.com',
            'password' => bcrypt('12345'),
            'role_id' => $employeeRole->id,
        ]);

        $employee4 = User::create([
            'name' => 'Employee 4 - HR',
            'email' => 'employee4@test.com',
            'password' => bcrypt('12345'),
            'role_id' => $employeeRole->id,
        ]);

        $employee5 = User::create([
            'name' => 'Employee 5 - HR',
            'email' => 'employee5@test.com',
            'password' => bcrypt('12345'),
            'role_id' => $employeeRole->id,
        ]);

        echo "✓ Created 5 employees (employee1-5@test.com)\n";

        // =============================
        // 2. Assign Employees to Departments
        // =============================
        echo "\n--- Assigning Employees to Departments ---\n";

        // Employees 1, 2, 3 -> Department A (Finance)
        DepartmentEmployee::create([
            'department_id' => $deptA->id,
            'user_id' => $employee1->id,
            'permission' => 'approver',
        ]);

        DepartmentEmployee::create([
            'department_id' => $deptA->id,
            'user_id' => $employee2->id,
            'permission' => 'approver',
        ]);

        DepartmentEmployee::create([
            'department_id' => $deptA->id,
            'user_id' => $employee3->id,
            'permission' => 'approver',
        ]);

        echo "✓ Assigned 3 employees to Department A (Finance)\n";

        // Employees 4, 5 -> Department C (HR)
        DepartmentEmployee::create([
            'department_id' => $deptC->id,
            'user_id' => $employee4->id,
            'permission' => 'approver',
        ]);

        DepartmentEmployee::create([
            'department_id' => $deptC->id,
            'user_id' => $employee5->id,
            'permission' => 'approver',
        ]);

        echo "✓ Assigned 2 employees to Department C (HR)\n";

        // =============================
        // 3. Create Form Types
        // =============================
        echo "\n--- Creating Form Types ---\n";

        $budgetRequestForm = FormType::create([
            'name' => 'Budget Request',
            'description' => 'Request budget approval for projects and initiatives',
            'icon' => 'currency-dollar',
            'has_file_upload' => true,
            'file_types_allowed' => ['pdf', 'xlsx', 'docx'],
            'max_file_size_mb' => 10,
            'is_active' => true,
        ]);

        $leaveRequestForm = FormType::create([
            'name' => 'Leave Request',
            'description' => 'Request time off and leave approval',
            'icon' => 'calendar',
            'has_file_upload' => false,
            'file_types_allowed' => null,
            'max_file_size_mb' => 5,
            'is_active' => true,
        ]);

        echo "✓ Created 2 form types: Budget Request, Leave Request\n";

        // =============================
        // 4. Create Workflow Templates
        // =============================
        echo "\n--- Creating Workflow Templates ---\n";

        $budgetWorkflow = WorkflowTemplate::create([
            'form_type_id' => $budgetRequestForm->id,
            'name' => 'Budget Approval Workflow',
            'description' => 'Finance employees review, then Operations manager approves',
            'is_active' => true,
        ]);

        $leaveWorkflow = WorkflowTemplate::create([
            'form_type_id' => $leaveRequestForm->id,
            'name' => 'Leave Approval Workflow',
            'description' => 'HR employees review and approve',
            'is_active' => true,
        ]);

        echo "✓ Created 2 workflow templates\n";

        // =============================
        // 5. Create Workflow Steps for Budget Request
        // =============================
        echo "\n--- Creating Workflow Steps ---\n";

        // Budget Request - Step 1: 3 employees from Dept A (any 2 out of 3)
        $budgetStep1 = WorkflowStep::create([
            'workflow_template_id' => $budgetWorkflow->id,
            'step_order' => 1,
            'step_name' => 'Finance Team Review',
            'approver_type' => 'employee',
            'department_id' => $deptA->id,
            'required_approvals_count' => 2,
            'approval_mode' => 'any_count',
            'can_skip' => false,
            'timeout_hours' => 48,
        ]);

        // Budget Request - Step 2: 1 manager from Dept B
        $budgetStep2 = WorkflowStep::create([
            'workflow_template_id' => $budgetWorkflow->id,
            'step_order' => 2,
            'step_name' => 'Operations Manager Approval',
            'approver_type' => 'manager',
            'department_id' => $deptB->id,
            'required_approvals_count' => 1,
            'approval_mode' => 'any_count',
            'can_skip' => false,
            'timeout_hours' => 72,
        ]);

        echo "✓ Created 2 workflow steps for Budget Request\n";

        // Leave Request - Step 1: 2 employees from Dept C (any 1 out of 2)
        $leaveStep1 = WorkflowStep::create([
            'workflow_template_id' => $leaveWorkflow->id,
            'step_order' => 1,
            'step_name' => 'HR Employee Review',
            'approver_type' => 'employee',
            'department_id' => $deptC->id,
            'required_approvals_count' => 1,
            'approval_mode' => 'any_count',
            'can_skip' => false,
            'timeout_hours' => 24,
        ]);

        echo "✓ Created 1 workflow step for Leave Request\n";

        // =============================
        // 6. Assign Employees to Workflow Steps
        // =============================
        echo "\n--- Assigning Employees to Workflow Steps ---\n";

        // Budget Step 1: Assign 3 employees from Dept A
        WorkflowStepApprover::create([
            'workflow_step_id' => $budgetStep1->id,
            'user_id' => $employee1->id,
            'role' => 'employee',
        ]);

        WorkflowStepApprover::create([
            'workflow_step_id' => $budgetStep1->id,
            'user_id' => $employee2->id,
            'role' => 'employee',
        ]);

        WorkflowStepApprover::create([
            'workflow_step_id' => $budgetStep1->id,
            'user_id' => $employee3->id,
            'role' => 'employee',
        ]);

        echo "✓ Assigned 3 employees to Budget Step 1\n";

        // Leave Step 1: Assign 2 employees from Dept C
        WorkflowStepApprover::create([
            'workflow_step_id' => $leaveStep1->id,
            'user_id' => $employee4->id,
            'role' => 'employee',
        ]);

        WorkflowStepApprover::create([
            'workflow_step_id' => $leaveStep1->id,
            'user_id' => $employee5->id,
            'role' => 'employee',
        ]);

        echo "✓ Assigned 2 employees to Leave Step 1\n";

        // =============================
        // Summary
        // =============================
        echo "\n=== Dynamic Workflow Seeding Complete! ===\n\n";
        echo "Created:\n";
        echo "  • 2 Form Types: Budget Request, Leave Request\n";
        echo "  • 2 Workflow Templates\n";
        echo "  • 3 Workflow Steps (2 for Budget, 1 for Leave)\n";
        echo "  • 5 Employees (employee1-5@test.com, password: 12345)\n";
        echo "  • Employee assignments to departments and workflow steps\n\n";

        echo "Workflow Details:\n\n";
        echo "📋 Budget Request Workflow:\n";
        echo "   Step 1: Finance Team (Dept A) - 3 employees, need 2 approvals\n";
        echo "           • employee1@test.com\n";
        echo "           • employee2@test.com\n";
        echo "           • employee3@test.com\n";
        echo "   Step 2: Operations Manager (Dept B) - managerd@test.com\n\n";

        echo "📋 Leave Request Workflow:\n";
        echo "   Step 1: HR Team (Dept C) - 2 employees, need 1 approval\n";
        echo "           • employee4@test.com\n";
        echo "           • employee5@test.com\n\n";
    }
}
