<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;
use App\Models\AuditLog;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionOption;
use App\Services\ExternalUserLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

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
     * Get all users with pagination
     */
    public function getUsers(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');

        $query = User::with(['departments' => function($query) {
            $query->withPivot('role');
        }, 'roles']);

        // Apply search filter
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('id', 'asc')->paginate($perPage);

        return response()->json([
            'users' => $users->items(),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
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
            'username' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            // 'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(['admin', 'supervisor', 'manager', 'employee', 'user'])],
            'is_active' => 'boolean',
        ]);

        // $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        $role = Role::where('name', 'LIKE', $validated['role'])->first(); 
        $user->assignRole($role);

        if(isset($validated['ou']) && $validated['ou'] != null) {
            $department = Department::whereIn('title', $validated['ou'])->first();

            if($department) {
                // Check if already assigned
                if (!$user->departments()->where('departments.id', $department->id)->exists()) {

                    if($validated['role'] == 'supervisor' || $validated['role'] == 'manager') {
                        $user_department_role = 'manager';
                    } else {
                        $user_department_role = 'employee';
                    }

                    $user->departments()->attach($department->id, ['role' => $user_department_role]);
                }
            }
        }

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
            'username' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role' => ['required', Rule::in(['admin', 'supervisor', 'manager', 'employee', 'user'])],
            'is_active' => 'boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        if (isset($validated['role']) && $validated['role'] !== $user->getRoleNames()->first()) {
            $newRole = Role::where('name', 'LIKE', $validated['role'])->first();
            $user->syncRoles($newRole);
        }

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

        $user->removeRole($user->getRoleNames()->first());

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

    // ============= SURVEY MANAGEMENT =============

    /**
     * Get all surveys with question and response counts
     */
    public function getSurveys(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $surveys = Survey::with(['questions.options'])->withCount('responses')->get();

        return response()->json([
            'surveys' => $surveys
        ]);
    }

    /**
     * Create a new survey with questions and options
     */
    public function createSurvey(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'is_active' => 'boolean',
            'trigger_point' => 'nullable|in:post_submission,post_completion',
            'questions' => 'required|array|min:1',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_text_ar' => 'required|string',
            'questions.*.question_type' => 'required|in:multiple_choice,satisfaction,text',
            'questions.*.order' => 'integer',
            'questions.*.is_required' => 'boolean',
            'questions.*.is_active' => 'boolean',
            'questions.*.options' => 'array',
            'questions.*.options.*.option_text' => 'required|string',
            'questions.*.options.*.option_text_ar' => 'required|string',
            'questions.*.options.*.option_value' => 'required|integer',
            'questions.*.options.*.order' => 'integer',
        ]);

        // Prevent multiple active surveys with the same trigger_point
        $triggerPoint = $validated['trigger_point'] ?? null;
        if ($triggerPoint) {
            $existing = Survey::where('trigger_point', $triggerPoint)
                ->where('is_active', true)
                ->first();
            if ($existing) {
                return response()->json([
                    'message' => "Another active survey already uses the '{$triggerPoint}' trigger point. Deactivate it first or remove its trigger point."
                ], 400);
            }
        }

        $survey = DB::transaction(function () use ($validated, $triggerPoint) {
            $survey = Survey::create([
                'title' => $validated['title'],
                'title_ar' => $validated['title_ar'],
                'description' => $validated['description'] ?? null,
                'description_ar' => $validated['description_ar'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'trigger_point' => $triggerPoint,
            ]);

            foreach ($validated['questions'] as $qData) {
                $question = $survey->questions()->create([
                    'question_text' => $qData['question_text'],
                    'question_text_ar' => $qData['question_text_ar'],
                    'question_type' => $qData['question_type'],
                    'order' => $qData['order'] ?? 0,
                    'is_required' => $qData['is_required'] ?? true,
                    'is_active' => $qData['is_active'] ?? true,
                ]);

                if (!empty($qData['options'])) {
                    foreach ($qData['options'] as $optData) {
                        $question->options()->create([
                            'option_text' => $optData['option_text'],
                            'option_text_ar' => $optData['option_text_ar'],
                            'option_value' => $optData['option_value'],
                            'order' => $optData['order'] ?? 0,
                        ]);
                    }
                }
            }

            return $survey;
        });

        AuditLog::log([
            'action' => 'created',
            'model_type' => 'Survey',
            'model_id' => $survey->id,
            'description' => "Admin created survey: {$survey->title}",
        ]);

        return response()->json([
            'message' => 'Survey created successfully',
            'survey' => $survey->load(['questions.options'])
        ], 201);
    }

    /**
     * Update an existing survey with questions and options
     */
    public function updateSurvey($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $survey = Survey::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'is_active' => 'boolean',
            'trigger_point' => 'nullable|in:post_submission,post_completion',
            'questions' => 'required|array|min:1',
            'questions.*.id' => 'nullable|integer',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_text_ar' => 'required|string',
            'questions.*.question_type' => 'required|in:multiple_choice,satisfaction,text',
            'questions.*.order' => 'integer',
            'questions.*.is_required' => 'boolean',
            'questions.*.is_active' => 'boolean',
            'questions.*.options' => 'array',
            'questions.*.options.*.id' => 'nullable|integer',
            'questions.*.options.*.option_text' => 'required|string',
            'questions.*.options.*.option_text_ar' => 'required|string',
            'questions.*.options.*.option_value' => 'required|integer',
            'questions.*.options.*.order' => 'integer',
        ]);

        // Prevent multiple active surveys with the same trigger_point
        $triggerPoint = $validated['trigger_point'] ?? null;
        if ($triggerPoint) {
            $existing = Survey::where('trigger_point', $triggerPoint)
                ->where('is_active', true)
                ->where('id', '!=', $survey->id)
                ->first();
            if ($existing) {
                return response()->json([
                    'message' => "Another active survey already uses the '{$triggerPoint}' trigger point. Deactivate it first or remove its trigger point."
                ], 400);
            }
        }

        DB::transaction(function () use ($survey, $validated, $triggerPoint) {
            $survey->update([
                'title' => $validated['title'],
                'title_ar' => $validated['title_ar'],
                'description' => $validated['description'] ?? null,
                'description_ar' => $validated['description_ar'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'trigger_point' => $triggerPoint,
            ]);

            $incomingQuestionIds = collect($validated['questions'])
                ->pluck('id')
                ->filter()
                ->toArray();

            // Delete removed questions
            $survey->questions()->whereNotIn('id', $incomingQuestionIds)->delete();

            foreach ($validated['questions'] as $qData) {
                if (!empty($qData['id'])) {
                    // Update existing question
                    $question = SurveyQuestion::find($qData['id']);
                    if ($question && $question->survey_id === $survey->id) {
                        $question->update([
                            'question_text' => $qData['question_text'],
                            'question_text_ar' => $qData['question_text_ar'],
                            'question_type' => $qData['question_type'],
                            'order' => $qData['order'] ?? 0,
                            'is_required' => $qData['is_required'] ?? true,
                            'is_active' => $qData['is_active'] ?? true,
                        ]);

                        // Sync options
                        $incomingOptionIds = collect($qData['options'] ?? [])
                            ->pluck('id')
                            ->filter()
                            ->toArray();
                        $question->options()->whereNotIn('id', $incomingOptionIds)->delete();

                        foreach ($qData['options'] ?? [] as $optData) {
                            if (!empty($optData['id'])) {
                                SurveyQuestionOption::where('id', $optData['id'])
                                    ->where('survey_question_id', $question->id)
                                    ->update([
                                        'option_text' => $optData['option_text'],
                                        'option_text_ar' => $optData['option_text_ar'],
                                        'option_value' => $optData['option_value'],
                                        'order' => $optData['order'] ?? 0,
                                    ]);
                            } else {
                                $question->options()->create([
                                    'option_text' => $optData['option_text'],
                                    'option_text_ar' => $optData['option_text_ar'],
                                    'option_value' => $optData['option_value'],
                                    'order' => $optData['order'] ?? 0,
                                ]);
                            }
                        }
                    }
                } else {
                    // Create new question
                    $question = $survey->questions()->create([
                        'question_text' => $qData['question_text'],
                        'question_text_ar' => $qData['question_text_ar'],
                        'question_type' => $qData['question_type'],
                        'order' => $qData['order'] ?? 0,
                        'is_required' => $qData['is_required'] ?? true,
                        'is_active' => $qData['is_active'] ?? true,
                    ]);

                    foreach ($qData['options'] ?? [] as $optData) {
                        $question->options()->create([
                            'option_text' => $optData['option_text'],
                            'option_text_ar' => $optData['option_text_ar'],
                            'option_value' => $optData['option_value'],
                            'order' => $optData['order'] ?? 0,
                        ]);
                    }
                }
            }
        });

        AuditLog::log([
            'action' => 'updated',
            'model_type' => 'Survey',
            'model_id' => $survey->id,
            'description' => "Admin updated survey: {$survey->title}",
        ]);

        return response()->json([
            'message' => 'Survey updated successfully',
            'survey' => $survey->load(['questions.options'])
        ]);
    }

    /**
     * Delete a survey (only if no responses exist)
     */
    public function deleteSurvey($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $survey = Survey::withCount('responses')->findOrFail($id);

        if ($survey->responses_count > 0) {
            return response()->json([
                'message' => "Cannot delete survey. It has {$survey->responses_count} response(s). Consider deactivating it instead."
            ], 400);
        }

        $surveyTitle = $survey->title;
        $survey->delete();

        AuditLog::log([
            'action' => 'deleted',
            'model_type' => 'Survey',
            'model_id' => $id,
            'description' => "Admin deleted survey: {$surveyTitle}",
        ]);

        return response()->json([
            'message' => 'Survey deleted successfully'
        ]);
    }

    /**
     * Toggle survey active status
     */
    public function toggleSurveyStatus($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $survey = Survey::findOrFail($id);
        $survey->is_active = !$survey->is_active;
        $survey->save();

        AuditLog::log([
            'action' => 'updated',
            'model_type' => 'Survey',
            'model_id' => $survey->id,
            'description' => "Admin " . ($survey->is_active ? 'activated' : 'deactivated') . " survey: {$survey->title}",
        ]);

        return response()->json([
            'message' => $survey->is_active ? 'Survey activated' : 'Survey deactivated',
            'survey' => $survey
        ]);
    }

    /**
     * Get survey responses with user and answer details
     */
    public function getSurveyResponses($id, Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $survey = Survey::with(['questions.options'])->findOrFail($id);

        $responses = $survey->responses()
            ->with(['user', 'answers.question', 'answers.selectedOption'])
            ->orderBy('submitted_at', 'desc')
            ->get();

        return response()->json([
            'survey' => $survey,
            'responses' => $responses
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
