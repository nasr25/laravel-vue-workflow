<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;
use App\Models\AuditLog;
use App\Services\ExternalUserLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    protected $userLookupService;

    public function __construct(ExternalUserLookupService $userLookupService)
    {
        $this->userLookupService = $userLookupService;
    }
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

        // Log department creation
        AuditLog::log([
            'action' => 'created',
            'model_type' => 'Department',
            'model_id' => $department->id,
            'description' => "Admin created department: {$department->name}",
            'new_values' => $validated,
        ]);

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
        $oldValues = $department->toArray();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('departments')->ignore($department->id)],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_department_a' => 'boolean',
        ]);

        $department->update($validated);

        // Log department update
        AuditLog::log([
            'action' => 'updated',
            'model_type' => 'Department',
            'model_id' => $department->id,
            'description' => "Admin updated department: {$department->name}",
            'old_values' => $oldValues,
            'new_values' => $validated,
        ]);

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

        $departmentName = $department->name;
        $department->delete();

        // Log department deletion
        AuditLog::log([
            'action' => 'deleted',
            'model_type' => 'Department',
            'model_id' => $id,
            'description' => "Admin deleted department: {$departmentName}",
        ]);

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

        // Log user creation
        AuditLog::log([
            'action' => 'created',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => "Admin created user: {$user->name} ({$user->email}) with role {$user->role}",
        ]);

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
        $oldValues = $user->toArray();

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

        // Log user update
        AuditLog::log([
            'action' => 'updated',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => "Admin updated user: {$user->name} ({$user->email})",
            'old_values' => $oldValues,
        ]);

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

        $userName = $user->name;
        $userEmail = $user->email;
        $user->delete();

        // Log user deletion
        AuditLog::log([
            'action' => 'deleted',
            'model_type' => 'User',
            'model_id' => $id,
            'description' => "Admin deleted user: {$userName} ({$userEmail})",
        ]);

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
        if ($user->departments()->where('departments.id', $department->id)->exists()) {
            return response()->json([
                'message' => 'User is already assigned to this department'
            ], 400);
        }

        $user->departments()->attach($department->id, ['role' => $validated['role']]);

        // Log user assignment
        AuditLog::log([
            'action' => 'assigned',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => "Admin assigned {$user->name} to department {$department->name} as {$validated['role']}",
        ]);

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
        if (!$user->departments()->where('departments.id', $department->id)->exists()) {
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
            'weight' => 'nullable|numeric|min:0|max:100',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        // Check if total weight will exceed 100%
        if (isset($validated['weight']) && $validated['weight'] !== null) {
            $totalWeight = \App\Models\EvaluationQuestion::where('is_active', true)->sum('weight');
            $newWeight = $validated['weight'];

            if ($totalWeight + $newWeight > 100) {
                return response()->json([
                    'message' => "Total weight cannot exceed 100%. Current total: {$totalWeight}%. Adding {$newWeight}% would exceed the limit."
                ], 400);
            }
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
            'weight' => 'sometimes|nullable|numeric|min:0|max:100',
            'order' => 'sometimes|nullable|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        // Check if total weight will exceed 100% (excluding current question)
        if (isset($validated['weight']) && $validated['weight'] !== null) {
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

    // ============= PATH EVALUATION QUESTIONS MANAGEMENT =============

    /**
     * Get all path evaluation questions grouped by workflow path
     */
    public function getPathEvaluationQuestions(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $questions = \App\Models\PathEvaluationQuestion::with('workflowPath')
            ->orderBy('workflow_path_id')
            ->orderBy('order')
            ->get();

        return response()->json([
            'questions' => $questions
        ]);
    }

    /**
     * Create a new path evaluation question
     */
    public function createPathEvaluationQuestion(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validated = $request->validate([
            'workflow_path_id' => 'required|exists:workflow_paths,id',
            'question' => 'required|string|max:500',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        $question = \App\Models\PathEvaluationQuestion::create($validated);

        return response()->json([
            'message' => 'Path evaluation question created successfully',
            'question' => $question->load('workflowPath')
        ], 201);
    }

    /**
     * Update a path evaluation question
     */
    public function updatePathEvaluationQuestion($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $question = \App\Models\PathEvaluationQuestion::findOrFail($id);

        $validated = $request->validate([
            'workflow_path_id' => 'sometimes|required|exists:workflow_paths,id',
            'question' => 'sometimes|required|string|max:500',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        $question->update($validated);

        return response()->json([
            'message' => 'Path evaluation question updated successfully',
            'question' => $question->load('workflowPath')
        ]);
    }

    /**
     * Delete a path evaluation question
     */
    public function deletePathEvaluationQuestion($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $question = \App\Models\PathEvaluationQuestion::findOrFail($id);

        // Check if question has been used in evaluations
        $evaluationCount = $question->evaluations()->count();

        if ($evaluationCount > 0) {
            return response()->json([
                'message' => "Cannot delete question. It has been used in {$evaluationCount} evaluation(s). Consider deactivating it instead."
            ], 400);
        }

        $question->delete();

        return response()->json([
            'message' => 'Path evaluation question deleted successfully'
        ]);
    }

    // ============= EXTERNAL USER LOOKUP =============

    /**
     * Search for users in external API
     */
    public function lookupExternalUser(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validated = $request->validate([
            'search' => 'required|string|min:1',
        ]);

        // Check if external API is configured
        if (!$this->userLookupService->isConfigured()) {
            return response()->json([
                'message' => 'External user lookup API is not configured',
                'configured' => false,
                'users' => []
            ]);
        }

        // Perform the search
        $users = $this->userLookupService->search($validated['search']);

        return response()->json([
            'configured' => true,
            'users' => $users
        ]);
    }

    /**
     * Get external user lookup configuration status
     */
    public function getExternalUserLookupConfig(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        return response()->json([
            'configured' => $this->userLookupService->isConfigured(),
            'api_url' => config('services.user_lookup.url', env('USER_LOOKUP_API_URL'))
        ]);
    }

    // ============= WORKFLOW PATH MANAGEMENT =============

    /**
     * Get all workflow paths
     */
    public function getWorkflowPaths(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $paths = \App\Models\WorkflowPath::with(['department', 'steps.department'])
            ->orderBy('order')
            ->get();

        return response()->json([
            'paths' => $paths
        ]);
    }

    /**
     * Create a new workflow path
     */
    public function createWorkflowPath(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:workflow_paths,code',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $path = \App\Models\WorkflowPath::create($validated);

        // Log the action
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'created',
            'model_type' => 'WorkflowPath',
            'model_id' => $path->id,
            'description' => "Created workflow path: {$path->name}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Workflow path created successfully',
            'path' => $path->load(['department', 'steps.department'])
        ], 201);
    }

    /**
     * Update a workflow path
     */
    public function updateWorkflowPath($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $path = \App\Models\WorkflowPath::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('workflow_paths')->ignore($path->id)],
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $path->update($validated);

        // Log the action
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'updated',
            'model_type' => 'WorkflowPath',
            'model_id' => $path->id,
            'description' => "Updated workflow path: {$path->name}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Workflow path updated successfully',
            'path' => $path->load(['department', 'steps.department'])
        ]);
    }

    /**
     * Delete a workflow path
     */
    public function deleteWorkflowPath($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $path = \App\Models\WorkflowPath::findOrFail($id);
        $pathName = $path->name;

        $path->delete();

        // Log the action
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'deleted',
            'model_type' => 'WorkflowPath',
            'model_id' => $id,
            'description' => "Deleted workflow path: {$pathName}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Workflow path deleted successfully'
        ]);
    }
}
