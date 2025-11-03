<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentManager;
use App\Models\User;
use App\Models\Role;
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
}
