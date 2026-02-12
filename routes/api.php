<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\RequestController;
use App\Http\Controllers\API\WorkflowController;
use App\Http\Controllers\API\DepartmentWorkflowController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\PermissionManagementController;
use App\Http\Controllers\API\SettingsController;
use App\Http\Controllers\API\EmailTemplateController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\AuditLogController;
use App\Http\Controllers\API\SurveyController;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/demo-accounts', [AuthController::class, 'getDemoAccounts']);

// Public settings (no authentication required)
Route::get('/settings/public', [SettingsController::class, 'getPublicSettings']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::get('/user', [AuthController::class, 'user']); // Keep for compatibility

    // Departments (Public for form)
    Route::get('/departments', [RequestController::class, 'getDepartments']);

    // Idea Types (Public for form - active types only)
    Route::get('/idea-types', [App\Http\Controllers\API\IdeaTypeController::class, 'index']);

    // Employee search
    Route::get('/employees/search', [EmployeeController::class, 'search']);

    // Dashboard statistics
    Route::get('/dashboard/statistics', [RequestController::class, 'getStatistics']);

    // Ideas Bank - All approved ideas visible to all users
    Route::get('/ideas-bank', [RequestController::class, 'getApprovedIdeas']);

    // User Settings
    Route::get('/user/settings', [App\Http\Controllers\API\UserSettingsController::class, 'getSettings']);
    Route::post('/user/settings', [App\Http\Controllers\API\UserSettingsController::class, 'saveSettings']);

    // Notifications
    Route::get('/notifications', [App\Http\Controllers\API\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [App\Http\Controllers\API\NotificationController::class, 'getUnreadCount']);
    Route::post('/notifications/{id}/read', [App\Http\Controllers\API\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [App\Http\Controllers\API\NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [App\Http\Controllers\API\NotificationController::class, 'destroy']);

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
    Route::get('/workflow/all-requests', [WorkflowController::class, 'getAllRequests']);
    Route::get('/workflow/requests/{requestId}/detail', [WorkflowController::class, 'getRequestDetail']);
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
    Route::post('/department/requests/{requestId}/activate', [DepartmentWorkflowController::class, 'activateAcceptedIdea']);
    Route::post('/department/requests/{requestId}/reject', [DepartmentWorkflowController::class, 'rejectIdea']);
    // Employee actions
    Route::post('/department/requests/{requestId}/employee-reject', [DepartmentWorkflowController::class, 'employeeRejectRequest']);
    Route::post('/department/requests/{requestId}/employee-accept', [DepartmentWorkflowController::class, 'employeeAcceptRequest']);
    Route::post('/department/requests/{requestId}/employee-update-progress', [DepartmentWorkflowController::class, 'employeeUpdateProgress']);
    Route::post('/department/requests/{requestId}/employee-complete', [DepartmentWorkflowController::class, 'employeeCompleteRequest']);

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

    // External User Lookup (Admin)
    Route::get('/admin/lookup-external-user', [AdminController::class, 'lookupExternalUser']);
    Route::get('/admin/external-user-lookup-config', [AdminController::class, 'getExternalUserLookupConfig']);

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

    // Workflow Path Management (Admin)
    Route::get('/admin/workflow-paths', [AdminController::class, 'getWorkflowPaths']);
    Route::post('/admin/workflow-paths', [AdminController::class, 'createWorkflowPath']);
    Route::put('/admin/workflow-paths/{id}', [AdminController::class, 'updateWorkflowPath']);
    Route::delete('/admin/workflow-paths/{id}', [AdminController::class, 'deleteWorkflowPath']);

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

    // Settings Management (Admin only)
    Route::prefix('settings')->middleware('admin')->group(function () {
        Route::get('/', [SettingsController::class, 'index']);
        Route::get('/{key}', [SettingsController::class, 'show']);
        Route::post('/', [SettingsController::class, 'store']);
        Route::put('/bulk', [SettingsController::class, 'updateBulk']);
        Route::post('/upload-image', [SettingsController::class, 'uploadImage']);
        Route::delete('/{key}', [SettingsController::class, 'destroy']);
    });

    // Email Template Management (Admin only)
    Route::prefix('email-templates')->middleware('admin')->group(function () {
        Route::get('/', [EmailTemplateController::class, 'index']);
        Route::get('/config', [EmailTemplateController::class, 'getEmailConfig']);
        Route::get('/{id}', [EmailTemplateController::class, 'show']);
        Route::put('/{id}', [EmailTemplateController::class, 'update']);
        Route::post('/{id}/toggle-status', [EmailTemplateController::class, 'toggleStatus']);
        Route::post('/{id}/send-test', [EmailTemplateController::class, 'sendTest']);
    });

    // Survey Management (Admin)
    Route::get('/admin/surveys', [AdminController::class, 'getSurveys']);
    Route::post('/admin/surveys', [AdminController::class, 'createSurvey']);
    Route::put('/admin/surveys/{id}', [AdminController::class, 'updateSurvey']);
    Route::delete('/admin/surveys/{id}', [AdminController::class, 'deleteSurvey']);
    Route::post('/admin/surveys/{id}/toggle-status', [AdminController::class, 'toggleSurveyStatus']);
    Route::get('/admin/surveys/{id}/responses', [AdminController::class, 'getSurveyResponses']);

    // User Survey Routes
    Route::get('/surveys', [SurveyController::class, 'getActiveSurveys']);
    Route::get('/surveys/my-responses', [SurveyController::class, 'getMyResponses']);
    Route::get('/surveys/trigger/{triggerPoint}', [SurveyController::class, 'getTriggerSurvey']);
    Route::get('/surveys/{id}', [SurveyController::class, 'getSurvey']);
    Route::post('/surveys/{id}/submit', [SurveyController::class, 'submitSurvey']);

    // Idea Types Management (Admin only)
    Route::prefix('admin/idea-types')->middleware('admin')->group(function () {
        Route::get('/', [App\Http\Controllers\API\IdeaTypeController::class, 'index']);
        Route::post('/', [App\Http\Controllers\API\IdeaTypeController::class, 'store']);
        Route::get('/{ideaType}', [App\Http\Controllers\API\IdeaTypeController::class, 'show']);
        Route::put('/{ideaType}', [App\Http\Controllers\API\IdeaTypeController::class, 'update']);
        Route::delete('/{ideaType}', [App\Http\Controllers\API\IdeaTypeController::class, 'destroy']);
        Route::post('/{ideaType}/toggle-status', [App\Http\Controllers\API\IdeaTypeController::class, 'toggleStatus']);
    });

    // Audit Logs (Admin only)
    Route::prefix('audit-logs')->middleware('admin')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/stats', [AuditLogController::class, 'stats']);
        Route::get('/filters', [AuditLogController::class, 'filters']);
        Route::get('/{id}', [AuditLogController::class, 'show']);
        Route::post('/cleanup', [AuditLogController::class, 'cleanup']);
    });

    // Test route
    Route::get('/test', function () {
        return response()->json([
            'message' => 'API is working!',
            'user' => auth()->user()
        ]);
    });
});
