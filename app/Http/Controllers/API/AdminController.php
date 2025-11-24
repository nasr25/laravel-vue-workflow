<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Check if user is admin
     */
    private function checkAdmin(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }
        return null;
    }

    // ============= DEPARTMENT MANAGEMENT =============

    /**
     * Get all departments
     */
    public function getDepartments(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $departments = Department::with(['users' => function($query) {
            $query->withPivot('role');
        }])->get();

        return response()->json([
            'departments' => $departments
        ]);
    }

    /**
     * Create a new department
     */
    public function createDepartment(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments,code',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_department_a' => 'boolean',
        ]);

        $department = Department::create($validated);

        return response()->json([
            'message' => 'Department created successfully',
            'department' => $department
        ], 201);
    }

    /**
     * Update a department
     */
    public function updateDepartment($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('departments')->ignore($department->id)],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_department_a' => 'boolean',
        ]);

        $department->update($validated);

        return response()->json([
            'message' => 'Department updated successfully',
            'department' => $department
        ]);
    }

    /**
     * Delete a department
     */
    public function deleteDepartment($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $department = Department::findOrFail($id);

        // Check if department has active requests
        if ($department->requests()->whereIn('status', ['pending', 'in_review'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete department with active requests'
            ], 400);
        }

        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully'
        ]);
    }

    // ============= USER MANAGEMENT =============

    /**
     * Get all users
     */
    public function getUsers(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $users = User::with(['departments' => function($query) {
            $query->withPivot('role');
        }, 'roles'])->get();

        return response()->json([
            'users' => $users
        ]);
    }

    /**
     * Create a new user
     */
    public function createUser(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(['admin', 'manager', 'employee', 'user'])],
            'is_active' => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Update a user
     */
    public function updateUser($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role' => ['required', Rule::in(['admin', 'manager', 'employee', 'user'])],
            'is_active' => 'boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->load('departments')
        ]);
    }

    /**
     * Delete a user
     */
    public function deleteUser($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $user = User::findOrFail($id);

        // Don't allow deleting yourself
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Cannot delete your own account'
            ], 400);
        }

        // Check if user has active requests assigned
        if ($user->assignedRequests()->whereIn('status', ['pending', 'in_review'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete user with active assigned requests'
            ], 400);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    // ============= DEPARTMENT-USER ASSIGNMENT =============

    /**
     * Assign user to department with role
     */
    public function assignUserToDepartment(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'department_id' => 'required|exists:departments,id',
            'role' => ['required', Rule::in(['manager', 'employee'])],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $department = Department::findOrFail($validated['department_id']);

        // Check if already assigned
        if ($user->departments()->where('department_id', $department->id)->exists()) {
            return response()->json([
                'message' => 'User is already assigned to this department'
            ], 400);
        }

        $user->departments()->attach($department->id, ['role' => $validated['role']]);

        return response()->json([
            'message' => 'User assigned to department successfully',
            'user' => $user->load('departments')
        ]);
    }

    /**
     * Update user's role in department
     */
    public function updateUserDepartmentRole(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'department_id' => 'required|exists:departments,id',
            'role' => ['required', Rule::in(['manager', 'employee'])],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $department = Department::findOrFail($validated['department_id']);

        // Check if assigned
        if (!$user->departments()->where('department_id', $department->id)->exists()) {
            return response()->json([
                'message' => 'User is not assigned to this department'
            ], 400);
        }

        $user->departments()->updateExistingPivot($department->id, ['role' => $validated['role']]);

        return response()->json([
            'message' => 'User role updated successfully',
            'user' => $user->load('departments')
        ]);
    }

    /**
     * Remove user from department
     */
    public function removeUserFromDepartment(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'department_id' => 'required|exists:departments,id',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $department = Department::findOrFail($validated['department_id']);

        // Check if user has active requests in this department
        if ($user->assignedRequests()
            ->where('current_department_id', $department->id)
            ->whereIn('status', ['pending', 'in_review'])
            ->exists()) {
            return response()->json([
                'message' => 'Cannot remove user from department with active assigned requests'
            ], 400);
        }

        $user->departments()->detach($department->id);

        return response()->json([
            'message' => 'User removed from department successfully',
            'user' => $user->load('departments')
        ]);
    }

    /**
     * Get department members (managers and employees)
     */
    public function getDepartmentMembers($departmentId, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $department = Department::with(['users' => function($query) {
            $query->withPivot('role');
        }])->findOrFail($departmentId);

        return response()->json([
            'department' => $department,
            'members' => $department->users
        ]);
    }

    // ============= REQUEST TRACKING =============

    /**
     * Get all requests with full history (Admin only)
     */
    public function getAllRequests(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $requests = \App\Models\Request::with([
            'user',
            'currentDepartment',
            'currentAssignee',
            'workflowPath.steps.department',
            'attachments',
            'transitions.actionedBy',
            'transitions.toDepartment',
            'transitions.fromDepartment'
        ])
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'requests' => $requests
        ]);
    }

    /**
     * Get single request with full history (Admin can see any request)
     */
    public function getRequestDetail($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $userRequest = \App\Models\Request::with([
            'user',
            'currentDepartment',
            'currentAssignee',
            'workflowPath.steps.department',
            'attachments',
            'transitions.actionedBy',
            'transitions.toDepartment',
            'transitions.fromDepartment'
        ])->findOrFail($id);

        return response()->json([
            'request' => $userRequest
        ]);
    }

    // ============= EVALUATION QUESTIONS MANAGEMENT =============

    /**
     * Get all evaluation questions
     */
    public function getEvaluationQuestions(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $questions = \App\Models\EvaluationQuestion::orderBy('order')->get();

        return response()->json([
            'questions' => $questions
        ]);
    }

    /**
     * Create a new evaluation question
     */
    public function createEvaluationQuestion(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validated = $request->validate([
            'question' => 'required|string',
            'weight' => 'required|numeric|min:0|max:100',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        // Check if total weight will exceed 100%
        $totalWeight = \App\Models\EvaluationQuestion::where('is_active', true)->sum('weight');
        $newWeight = $validated['weight'];

        if ($totalWeight + $newWeight > 100) {
            return response()->json([
                'message' => "Total weight cannot exceed 100%. Current total: {$totalWeight}%. Adding {$newWeight}% would exceed the limit."
            ], 400);
        }

        $question = \App\Models\EvaluationQuestion::create($validated);

        return response()->json([
            'message' => 'Evaluation question created successfully',
            'question' => $question
        ], 201);
    }

    /**
     * Update an evaluation question
     */
    public function updateEvaluationQuestion($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $question = \App\Models\EvaluationQuestion::findOrFail($id);

        $validated = $request->validate([
            'question' => 'sometimes|required|string',
            'weight' => 'sometimes|required|numeric|min:0|max:100',
            'order' => 'sometimes|nullable|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        // Check if total weight will exceed 100% (excluding current question)
        if (isset($validated['weight'])) {
            $totalWeight = \App\Models\EvaluationQuestion::where('is_active', true)
                ->where('id', '!=', $id)
                ->sum('weight');
            $newWeight = $validated['weight'];

            if ($totalWeight + $newWeight > 100) {
                return response()->json([
                    'message' => "Total weight cannot exceed 100%. Other questions total: {$totalWeight}%. Adding {$newWeight}% would exceed the limit."
                ], 400);
            }
        }

        $question->update($validated);

        return response()->json([
            'message' => 'Evaluation question updated successfully',
            'question' => $question
        ]);
    }

    /**
     * Delete an evaluation question
     */
    public function deleteEvaluationQuestion($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $question = \App\Models\EvaluationQuestion::findOrFail($id);

        // Check if question has been used in evaluations
        $evaluationCount = $question->evaluations()->count();

        if ($evaluationCount > 0) {
            return response()->json([
                'message' => "Cannot delete question. It has been used in {$evaluationCount} evaluation(s). Consider deactivating it instead."
            ], 400);
        }

        $question->delete();

        return response()->json([
            'message' => 'Evaluation question deleted successfully'
        ]);
    }

    /**
     * Get total weight of active questions
     */
    public function getEvaluationWeightTotal(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $totalWeight = \App\Models\EvaluationQuestion::where('is_active', true)->sum('weight');

        return response()->json([
            'total_weight' => $totalWeight,
            'remaining' => 100 - $totalWeight
        ]);
    }
}
