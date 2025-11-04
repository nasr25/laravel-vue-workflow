<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentManager;
use App\Models\DepartmentEmployee;
use App\Models\User;
use App\Models\Role;
use App\Models\FormType;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepApprover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * Get all departments
     */
    public function getDepartments()
    {
        try {
            $departments = Department::with('managers')->orderBy('approval_order')->get();

            return response()->json([
                'success' => true,
                'departments' => $departments,
            ]);
        } catch (\Exception $e) {
            \Log::error('Get departments error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch departments'
            ], 500);
        }
    }

    /**
     * Create a new department
     */
    public function createDepartment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Auto-assign approval_order as the next available position
            $maxOrder = Department::max('approval_order') ?? 0;
            $newOrder = $maxOrder + 1;

            $department = Department::create([
                'name' => $request->name,
                'description' => $request->description,
                'approval_order' => $newOrder,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'success' => true,
                'department' => $department,
                'message' => 'Department created successfully'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Create department error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department'
            ], 500);
        }
    }

    /**
     * Update a department
     */
    public function updateDepartment(Request $request, $id)
    {
        try {
            $department = Department::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'approval_order' => 'sometimes|integer|min:1|unique:departments,approval_order,' . $id,
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $department->update($request->all());

            return response()->json([
                'success' => true,
                'department' => $department,
                'message' => 'Department updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Update department error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department'
            ], 500);
        }
    }

    /**
     * Delete a department
     */
    public function deleteDepartment($id)
    {
        try {
            $department = Department::findOrFail($id);
            $department->delete();

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete department error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department'
            ], 500);
        }
    }

    /**
     * Get all managers (users with manager role)
     */
    public function getManagers()
    {
        try {
            $managerRole = Role::where('name', 'manager')->first();
            $managers = User::where('role_id', $managerRole->id)
                ->with('managedDepartments')
                ->get();

            return response()->json([
                'success' => true,
                'managers' => $managers,
            ]);
        } catch (\Exception $e) {
            \Log::error('Get managers error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch managers'
            ], 500);
        }
    }

    /**
     * Assign manager to department
     */
    public function assignManager(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'department_id' => 'required|exists:departments,id',
                'user_id' => 'required|exists:users,id',
                'permission' => 'nullable|in:viewer,approver',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if user has manager role
            $user = User::find($request->user_id);
            if (!$user->isManager()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must have manager role'
                ], 422);
            }

            // Check if already assigned
            $existing = DepartmentManager::where('department_id', $request->department_id)
                ->where('user_id', $request->user_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Manager already assigned to this department'
                ], 422);
            }

            DepartmentManager::create([
                'department_id' => $request->department_id,
                'user_id' => $request->user_id,
                'permission' => $request->permission ?? 'approver', // Default to approver
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Manager assigned successfully'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Assign manager error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign manager'
            ], 500);
        }
    }

    /**
     * Remove manager from department
     */
    public function removeManager(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'department_id' => 'required|exists:departments,id',
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DepartmentManager::where('department_id', $request->department_id)
                ->where('user_id', $request->user_id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Manager removed successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Remove manager error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove manager'
            ], 500);
        }
    }

    /**
     * Create a manager user
     */
    public function createManager(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|min:3',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $managerRole = Role::where('name', 'manager')->first();

            // Sanitize inputs
            $name = strip_tags(trim($request->name));
            $email = trim($request->email);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => bcrypt($request->password),
                'role_id' => $managerRole->id,
            ]);

            return response()->json([
                'success' => true,
                'manager' => $user->load('role'),
                'message' => 'Manager created successfully'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Create manager error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create manager'
            ], 500);
        }
    }

    /**
     * Reorder departments (update approval_order)
     */
    public function reorderDepartments(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'departments' => 'required|array',
                'departments.*.id' => 'required|exists:departments,id',
                'departments.*.approval_order' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate department count and unique approval orders
            $departments = $request->departments;
            $totalDepartments = Department::count();

            if (count($departments) !== $totalDepartments) {
                return response()->json([
                    'success' => false,
                    'message' => "Must provide all $totalDepartments departments for reordering"
                ], 422);
            }

            $orders = array_column($departments, 'approval_order');
            $expectedCount = count($departments);

            // Check: all orders are unique, start from 1, and are sequential
            if (count(array_unique($orders)) !== $expectedCount ||
                min($orders) !== 1 ||
                max($orders) !== $expectedCount) {
                return response()->json([
                    'success' => false,
                    'message' => "Approval orders must be unique and sequential from 1 to $expectedCount"
                ], 422);
            }

            // Update each department's approval order in a transaction
            \DB::transaction(function () use ($departments) {
                foreach ($departments as $dept) {
                    Department::where('id', $dept['id'])
                        ->update(['approval_order' => $dept['approval_order']]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Department order updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Reorder departments error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder departments'
            ], 500);
        }
    }

    /**
     * Get count of pending ideas
     */
    public function getPendingIdeasCount()
    {
        try {
            $count = \App\Models\Idea::where('status', 'pending')->count();

            return response()->json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            \Log::error('Get pending ideas count error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get pending ideas count'
            ], 500);
        }
    }

    /**
     * Update manager permission for a department
     */
    public function updateManagerPermission(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'department_id' => 'required|exists:departments,id',
                'user_id' => 'required|exists:users,id',
                'permission' => 'required|in:viewer,approver',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $assignment = DepartmentManager::where('department_id', $request->department_id)
                ->where('user_id', $request->user_id)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Manager assignment not found'
                ], 404);
            }

            $assignment->update(['permission' => $request->permission]);

            return response()->json([
                'success' => true,
                'message' => 'Permission updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Update permission error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission'
            ], 500);
        }
    }

    /**
     * Get all users (for user management)
     */
    public function getAllUsers()
    {
        try {
            $users = User::with(['role', 'managedDepartments'])->get();

            return response()->json([
                'success' => true,
                'users' => $users,
            ]);
        } catch (\Exception $e) {
            \Log::error('Get all users error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users'
            ], 500);
        }
    }

    /**
     * Update a user
     */
    public function updateUser(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255|min:3',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
                'password' => 'sometimes|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];

            if ($request->has('name')) {
                $updateData['name'] = strip_tags(trim($request->name));
            }

            if ($request->has('email')) {
                $updateData['email'] = trim($request->email);
            }

            if ($request->has('password') && $request->password) {
                $updateData['password'] = bcrypt($request->password);
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'user' => $user->load('role'),
                'message' => 'User updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Update user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user'
            ], 500);
        }
    }

    /**
     * Delete a user
     */
    public function deleteUser($id)
    {
        try {
            $user = User::findOrFail($id);

            // Prevent deleting own account
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 422);
            }

            // Remove manager assignments if any
            DepartmentManager::where('user_id', $user->id)->delete();

            // Delete user
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user'
            ], 500);
        }
    }

    // ========================================
    // EMPLOYEE MANAGEMENT
    // ========================================

    /**
     * Get all employees
     */
    public function getEmployees()
    {
        try {
            $employeeRole = Role::where('name', 'employee')->first();
            $employees = User::where('role_id', $employeeRole->id)
                ->with(['employeeDepartments', 'workflowStepApprovers.workflowStep'])
                ->get();

            return response()->json([
                'success' => true,
                'employees' => $employees,
            ]);
        } catch (\Exception $e) {
            \Log::error('Get employees error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employees'
            ], 500);
        }
    }

    /**
     * Create a new employee
     */
    public function createEmployee(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:5',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $employeeRole = Role::where('name', 'employee')->first();
            if (!$employeeRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee role not found'
                ], 404);
            }

            $employee = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'role_id' => $employeeRole->id,
            ]);

            return response()->json([
                'success' => true,
                'employee' => $employee,
                'message' => 'Employee created successfully'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Create employee error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee'
            ], 500);
        }
    }

    /**
     * Assign employee to department
     */
    public function assignEmployeeToDepartment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'department_id' => 'required|exists:departments,id',
                'permission' => 'required|in:viewer,approver',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if already assigned
            $existing = DepartmentEmployee::where('user_id', $request->user_id)
                ->where('department_id', $request->department_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee already assigned to this department'
                ], 422);
            }

            DepartmentEmployee::create([
                'user_id' => $request->user_id,
                'department_id' => $request->department_id,
                'permission' => $request->permission,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee assigned to department successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Assign employee error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign employee'
            ], 500);
        }
    }

    /**
     * Remove employee from department
     */
    public function removeEmployeeFromDepartment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'department_id' => 'required|exists:departments,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DepartmentEmployee::where('user_id', $request->user_id)
                ->where('department_id', $request->department_id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee removed from department successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Remove employee error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove employee'
            ], 500);
        }
    }

    // ========================================
    // FORM TYPE MANAGEMENT
    // ========================================

    /**
     * Get all form types (including inactive)
     */
    public function getFormTypes()
    {
        try {
            $formTypes = FormType::with('workflowTemplates')->get();

            return response()->json([
                'success' => true,
                'formTypes' => $formTypes,
            ]);
        } catch (\Exception $e) {
            \Log::error('Get form types error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch form types'
            ], 500);
        }
    }

    /**
     * Create a new form type
     */
    public function createFormType(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'icon' => 'nullable|string|max:50',
                'has_file_upload' => 'nullable|boolean',
                'file_types_allowed' => 'nullable|array',
                'max_file_size_mb' => 'nullable|integer|min:1|max:100',
                'is_active' => 'nullable|boolean',
                'form_fields' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $formType = FormType::create([
                'name' => $request->name,
                'description' => $request->description,
                'icon' => $request->icon ?? 'file-text',
                'has_file_upload' => $request->has_file_upload ?? true,
                'file_types_allowed' => $request->file_types_allowed ?? ['pdf', 'docx'],
                'max_file_size_mb' => $request->max_file_size_mb ?? 10,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'success' => true,
                'formType' => $formType,
                'message' => 'Form type created successfully'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Create form type error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create form type'
            ], 500);
        }
    }

    /**
     * Update a form type
     */
    public function updateFormType(Request $request, $id)
    {
        try {
            $formType = FormType::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'icon' => 'nullable|string|max:50',
                'has_file_upload' => 'nullable|boolean',
                'file_types_allowed' => 'nullable|array',
                'max_file_size_mb' => 'nullable|integer|min:1|max:100',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $formType->update($request->all());

            return response()->json([
                'success' => true,
                'formType' => $formType,
                'message' => 'Form type updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Update form type error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update form type'
            ], 500);
        }
    }

    /**
     * Delete a form type
     */
    public function deleteFormType($id)
    {
        try {
            $formType = FormType::findOrFail($id);

            // Check if form type has workflow templates
            if ($formType->workflowTemplates()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete form type with existing workflows'
                ], 422);
            }

            $formType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Form type deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete form type error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete form type'
            ], 500);
        }
    }

    // ========================================
    // WORKFLOW TEMPLATE MANAGEMENT
    // ========================================

    /**
     * Get all workflow templates
     */
    public function getWorkflowTemplates()
    {
        try {
            $templates = WorkflowTemplate::with(['formType', 'steps' => function ($query) {
                $query->orderBy('step_order')->with(['department', 'approvers.user']);
            }])->get();

            return response()->json([
                'success' => true,
                'templates' => $templates,
            ]);
        } catch (\Exception $e) {
            \Log::error('Get workflow templates error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workflow templates'
            ], 500);
        }
    }

    /**
     * Create a new workflow template
     */
    public function createWorkflowTemplate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'form_type_id' => 'required|exists:form_types,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // If set as active, deactivate other templates for this form type
            if ($request->is_active) {
                WorkflowTemplate::where('form_type_id', $request->form_type_id)
                    ->update(['is_active' => false]);
            }

            $template = WorkflowTemplate::create([
                'form_type_id' => $request->form_type_id,
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'success' => true,
                'template' => $template->load('formType'),
                'message' => 'Workflow template created successfully'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Create workflow template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create workflow template'
            ], 500);
        }
    }

    /**
     * Update a workflow template
     */
    public function updateWorkflowTemplate(Request $request, $id)
    {
        try {
            $template = WorkflowTemplate::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // If set as active, deactivate other templates for this form type
            if ($request->is_active) {
                WorkflowTemplate::where('form_type_id', $template->form_type_id)
                    ->where('id', '!=', $id)
                    ->update(['is_active' => false]);
            }

            $template->update($request->all());

            return response()->json([
                'success' => true,
                'template' => $template->load('formType'),
                'message' => 'Workflow template updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Update workflow template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update workflow template'
            ], 500);
        }
    }

    /**
     * Delete a workflow template
     */
    public function deleteWorkflowTemplate($id)
    {
        try {
            $template = WorkflowTemplate::findOrFail($id);

            // Delete all workflow steps and their approvers
            foreach ($template->steps as $step) {
                $step->approvers()->delete();
                $step->delete();
            }

            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Workflow template deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete workflow template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete workflow template'
            ], 500);
        }
    }

    // ========================================
    // WORKFLOW STEP MANAGEMENT
    // ========================================

    /**
     * Create a workflow step
     */
    public function createWorkflowStep(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'workflow_template_id' => 'required|exists:workflow_templates,id',
                'step_name' => 'required|string|max:255',
                'approver_type' => 'required|in:employee,manager,either',
                'department_id' => 'required|exists:departments,id',
                'required_approvals_count' => 'required|integer|min:1',
                'approval_mode' => 'required|in:all,any_count',
                'can_skip' => 'nullable|boolean',
                'timeout_hours' => 'nullable|integer|min:1',
                'approver_ids' => 'nullable|array', // Array of user IDs to assign
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get max step_order for this template
            $maxOrder = WorkflowStep::where('workflow_template_id', $request->workflow_template_id)
                ->max('step_order') ?? 0;

            $step = WorkflowStep::create([
                'workflow_template_id' => $request->workflow_template_id,
                'step_order' => $maxOrder + 1,
                'step_name' => $request->step_name,
                'approver_type' => $request->approver_type,
                'department_id' => $request->department_id,
                'required_approvals_count' => $request->required_approvals_count,
                'approval_mode' => $request->approval_mode,
                'can_skip' => $request->can_skip ?? false,
                'timeout_hours' => $request->timeout_hours,
            ]);

            // Assign approvers if provided
            if ($request->has('approver_ids') && is_array($request->approver_ids)) {
                foreach ($request->approver_ids as $userId) {
                    WorkflowStepApprover::create([
                        'workflow_step_id' => $step->id,
                        'user_id' => $userId,
                        'role' => $request->approver_type === 'manager' ? 'manager' : 'employee',
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'step' => $step->load(['department', 'approvers.user']),
                'message' => 'Workflow step created successfully'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Create workflow step error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create workflow step'
            ], 500);
        }
    }

    /**
     * Update a workflow step
     */
    public function updateWorkflowStep(Request $request, $id)
    {
        try {
            $step = WorkflowStep::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'step_name' => 'sometimes|string|max:255',
                'approver_type' => 'sometimes|in:employee,manager,either',
                'department_id' => 'sometimes|exists:departments,id',
                'required_approvals_count' => 'sometimes|integer|min:1',
                'approval_mode' => 'sometimes|in:all,any_count',
                'can_skip' => 'nullable|boolean',
                'timeout_hours' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $step->update($request->all());

            return response()->json([
                'success' => true,
                'step' => $step->load(['department', 'approvers.user']),
                'message' => 'Workflow step updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Update workflow step error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update workflow step'
            ], 500);
        }
    }

    /**
     * Delete a workflow step
     */
    public function deleteWorkflowStep($id)
    {
        try {
            $step = WorkflowStep::findOrFail($id);

            // Delete approvers first
            $step->approvers()->delete();

            // Delete step
            $step->delete();

            // Reorder remaining steps
            $remainingSteps = WorkflowStep::where('workflow_template_id', $step->workflow_template_id)
                ->orderBy('step_order')
                ->get();

            foreach ($remainingSteps as $index => $remainingStep) {
                $remainingStep->update(['step_order' => $index + 1]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Workflow step deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete workflow step error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete workflow step'
            ], 500);
        }
    }

    /**
     * Add approver to workflow step
     */
    public function addWorkflowStepApprover(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'workflow_step_id' => 'required|exists:workflow_steps,id',
                'user_id' => 'required|exists:users,id',
                'role' => 'required|in:employee,manager',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if already assigned
            $existing = WorkflowStepApprover::where('workflow_step_id', $request->workflow_step_id)
                ->where('user_id', $request->user_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already assigned to this workflow step'
                ], 422);
            }

            WorkflowStepApprover::create([
                'workflow_step_id' => $request->workflow_step_id,
                'user_id' => $request->user_id,
                'role' => $request->role,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Approver added to workflow step successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Add workflow step approver error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add approver'
            ], 500);
        }
    }

    /**
     * Remove approver from workflow step
     */
    public function removeWorkflowStepApprover(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'workflow_step_id' => 'required|exists:workflow_steps,id',
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            WorkflowStepApprover::where('workflow_step_id', $request->workflow_step_id)
                ->where('user_id', $request->user_id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Approver removed from workflow step successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Remove workflow step approver error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove approver'
            ], 500);
        }
    }
}
