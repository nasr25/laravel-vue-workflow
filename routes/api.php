<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\RequestController;
use App\Http\Controllers\API\WorkflowController;
use App\Http\Controllers\API\DepartmentWorkflowController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\PermissionManagementController;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::get('/user', [AuthController::class, 'user']); // Keep for compatibility

    // Departments (Public for form)
    Route::get('/departments', [RequestController::class, 'getDepartments']);

    // Request routes
    Route::get('/requests', [RequestController::class, 'index'])->middleware('permission:request.view-own');
    Route::post('/requests', [RequestController::class, 'store'])->middleware('permission:request.create');
    Route::get('/requests/{id}', [RequestController::class, 'show'])->middleware('permission:request.view-own');
    Route::put('/requests/{id}', [RequestController::class, 'update'])->middleware('permission:request.edit-own');
    Route::post('/requests/{id}', [RequestController::class, 'update'])->middleware('permission:request.edit-own');
    Route::delete('/requests/{id}', [RequestController::class, 'destroy'])->middleware('permission:request.delete-own');
    Route::post('/requests/{id}/submit', [RequestController::class, 'submit'])->middleware('permission:request.submit');
    Route::post('/requests/{id}/attachments', [RequestController::class, 'uploadAttachment'])->middleware('permission:request.edit-own');
    Route::delete('/requests/{requestId}/attachments/{attachmentId}', [RequestController::class, 'deleteAttachment'])->middleware('permission:request.delete-own');

    // Workflow routes (Department A managers)
    Route::get('/workflow/pending-requests', [WorkflowController::class, 'getPendingRequests']);
    Route::get('/workflow/paths', [WorkflowController::class, 'getWorkflowPaths']);
    Route::post('/workflow/requests/{requestId}/assign-path', [WorkflowController::class, 'assignPath']);
    Route::post('/workflow/requests/{requestId}/reject', [WorkflowController::class, 'rejectRequest']);
    Route::post('/workflow/requests/{requestId}/request-details', [WorkflowController::class, 'requestMoreDetails']);
    Route::post('/workflow/requests/{requestId}/complete', [WorkflowController::class, 'completeRequest']);
    Route::post('/workflow/requests/{requestId}/return-to-previous', [WorkflowController::class, 'returnToPreviousDepartment']);
    Route::get('/workflow/requests/{requestId}/evaluation-questions', [WorkflowController::class, 'getEvaluationQuestions']);
    Route::post('/workflow/requests/{requestId}/evaluation', [WorkflowController::class, 'submitEvaluation']);

    // Department workflow routes (Managers and Employees)
    Route::get('/department/requests', [DepartmentWorkflowController::class, 'getDepartmentRequests']);
    Route::get('/department/employees', [DepartmentWorkflowController::class, 'getDepartmentEmployees']);
    Route::post('/department/requests/{requestId}/assign-employee', [DepartmentWorkflowController::class, 'assignToEmployee']);
    Route::post('/department/requests/{requestId}/return-to-manager', [DepartmentWorkflowController::class, 'returnToManager']);
    Route::post('/department/requests/{requestId}/return-to-dept-a', [DepartmentWorkflowController::class, 'returnToDepartmentA']);
    Route::get('/department/requests/{requestId}/path-evaluation-questions', [DepartmentWorkflowController::class, 'getPathEvaluationQuestions']);
    Route::post('/department/requests/{requestId}/path-evaluation', [DepartmentWorkflowController::class, 'submitPathEvaluation']);
    Route::post('/department/requests/{requestId}/accept-later', [DepartmentWorkflowController::class, 'acceptIdeaForLater']);
    Route::post('/department/requests/{requestId}/reject', [DepartmentWorkflowController::class, 'rejectIdea']);

    // Admin routes (Admin only)
    // Department Management
    Route::get('/admin/departments', [AdminController::class, 'getDepartments']);
    Route::post('/admin/departments', [AdminController::class, 'createDepartment']);
    Route::put('/admin/departments/{id}', [AdminController::class, 'updateDepartment']);
    Route::delete('/admin/departments/{id}', [AdminController::class, 'deleteDepartment']);
    Route::get('/admin/departments/{departmentId}/members', [AdminController::class, 'getDepartmentMembers']);

    // User Management
    Route::get('/admin/users', [AdminController::class, 'getUsers']);
    Route::post('/admin/users', [AdminController::class, 'createUser']);
    Route::put('/admin/users/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);

    // Department-User Assignment
    Route::post('/admin/assign-user-department', [AdminController::class, 'assignUserToDepartment']);
    Route::put('/admin/update-user-department-role', [AdminController::class, 'updateUserDepartmentRole']);
    Route::post('/admin/remove-user-department', [AdminController::class, 'removeUserFromDepartment']);

    // Request Tracking (Admin)
    Route::get('/admin/requests', [AdminController::class, 'getAllRequests']);
    Route::get('/admin/requests/{id}', [AdminController::class, 'getRequestDetail']);

    // Evaluation Questions Management (Admin)
    Route::get('/admin/evaluation-questions', [AdminController::class, 'getEvaluationQuestions']);
    Route::post('/admin/evaluation-questions', [AdminController::class, 'createEvaluationQuestion']);
    Route::put('/admin/evaluation-questions/{id}', [AdminController::class, 'updateEvaluationQuestion']);
    Route::delete('/admin/evaluation-questions/{id}', [AdminController::class, 'deleteEvaluationQuestion']);
    Route::get('/admin/evaluation-weight-total', [AdminController::class, 'getEvaluationWeightTotal']);

    // Path Evaluation Questions Management (Admin)
    Route::get('/admin/path-evaluation-questions', [AdminController::class, 'getPathEvaluationQuestions']);
    Route::post('/admin/path-evaluation-questions', [AdminController::class, 'createPathEvaluationQuestion']);
    Route::put('/admin/path-evaluation-questions/{id}', [AdminController::class, 'updatePathEvaluationQuestion']);
    Route::delete('/admin/path-evaluation-questions/{id}', [AdminController::class, 'deletePathEvaluationQuestion']);

    // Permission Management (Admin/Super Admin only)
    Route::prefix('permissions')->group(function () {
        // Roles Management
        Route::get('/roles', [PermissionManagementController::class, 'getRoles']);
        Route::get('/roles/{id}', [PermissionManagementController::class, 'getRoleDetails']);
        Route::post('/roles', [PermissionManagementController::class, 'createRole']);
        Route::put('/roles/{id}', [PermissionManagementController::class, 'updateRole']);
        Route::delete('/roles/{id}', [PermissionManagementController::class, 'deleteRole']);

        // Permissions List
        Route::get('/list', [PermissionManagementController::class, 'getPermissions']);

        // User Role Assignment
        Route::post('/assign-role', [PermissionManagementController::class, 'assignRole']);
        Route::post('/remove-role', [PermissionManagementController::class, 'removeRole']);

        // User Permissions
        Route::get('/users/{userId}', [PermissionManagementController::class, 'getUserPermissions']);
        Route::post('/users/give-permission', [PermissionManagementController::class, 'givePermissionToUser']);
        Route::post('/users/revoke-permission', [PermissionManagementController::class, 'revokePermissionFromUser']);
        Route::post('/users/{userId}/check', [PermissionManagementController::class, 'checkPermission']);
    });

    // Test route
    Route::get('/test', function () {
        return response()->json([
            'message' => 'API is working!',
            'user' => auth()->user()
        ]);
    });
});
