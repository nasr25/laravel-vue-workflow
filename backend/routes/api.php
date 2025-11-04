<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\IdeaController;
use App\Http\Controllers\API\ApprovalController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\FormTypeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {

    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ========== USER ROUTES ==========
    // Ideas management for regular users
    Route::prefix('ideas')->group(function () {
        Route::get('/my-ideas', [IdeaController::class, 'myIdeas']);
        Route::get('/{id}', [IdeaController::class, 'show']);
        Route::post('/', [IdeaController::class, 'store']);
        Route::put('/{id}', [IdeaController::class, 'update']);
        Route::post('/{id}/submit', [IdeaController::class, 'submit']);
        Route::delete('/{id}', [IdeaController::class, 'destroy']);
    });

    // ========== FORM TYPES ROUTES ==========
    // Form types for dynamic workflows
    Route::prefix('form-types')->group(function () {
        Route::get('/', [FormTypeController::class, 'index']);
        Route::get('/{id}', [FormTypeController::class, 'show']);
    });

    // ========== EMPLOYEE ROUTES ==========
    // Approval management for employees
    Route::prefix('employee')->group(function () {
        Route::get('/pending', [EmployeeController::class, 'getPendingIdeas']);
        Route::post('/{ideaId}/approve', [EmployeeController::class, 'approve']);
        Route::post('/{ideaId}/reject', [EmployeeController::class, 'reject']);
    });

    // ========== MANAGER ROUTES ==========
    // Approval management for managers
    Route::prefix('approvals')->group(function () {
        Route::get('/pending', [ApprovalController::class, 'pendingIdeas']);
        Route::get('/all-ideas', [ApprovalController::class, 'allIdeas']);
        Route::get('/{ideaId}/return-departments', [ApprovalController::class, 'getReturnDepartments']);
        Route::post('/{ideaId}/approve', [ApprovalController::class, 'approve']);
        Route::post('/{ideaId}/reject', [ApprovalController::class, 'reject']);
        Route::post('/{ideaId}/return', [ApprovalController::class, 'returnToUser']);
    });

    // ========== ADMIN ROUTES ==========
    // Department management
    Route::prefix('admin/departments')->group(function () {
        Route::get('/', [AdminController::class, 'getDepartments']);
        Route::post('/', [AdminController::class, 'createDepartment']);
        Route::put('/{id}', [AdminController::class, 'updateDepartment']);
        Route::delete('/{id}', [AdminController::class, 'deleteDepartment']);
        Route::post('/reorder', [AdminController::class, 'reorderDepartments']);
    });

    // Manager management
    Route::prefix('admin/managers')->group(function () {
        Route::get('/', [AdminController::class, 'getManagers']);
        Route::post('/', [AdminController::class, 'createManager']);
        Route::post('/assign', [AdminController::class, 'assignManager']);
        Route::post('/remove', [AdminController::class, 'removeManager']);
        Route::post('/update-permission', [AdminController::class, 'updateManagerPermission']);
    });

    // Admin statistics
    Route::get('/admin/pending-ideas-count', [AdminController::class, 'getPendingIdeasCount']);

    // User management
    Route::prefix('admin/users')->group(function () {
        Route::get('/', [AdminController::class, 'getAllUsers']);
        Route::put('/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/{id}', [AdminController::class, 'deleteUser']);
    });

    // Employee management
    Route::prefix('admin/employees')->group(function () {
        Route::get('/', [AdminController::class, 'getEmployees']);
        Route::post('/', [AdminController::class, 'createEmployee']);
        Route::post('/assign', [AdminController::class, 'assignEmployeeToDepartment']);
        Route::post('/remove', [AdminController::class, 'removeEmployeeFromDepartment']);
    });

    // Form type management
    Route::prefix('admin/form-types')->group(function () {
        Route::get('/', [AdminController::class, 'getFormTypes']);
        Route::post('/', [AdminController::class, 'createFormType']);
        Route::put('/{id}', [AdminController::class, 'updateFormType']);
        Route::delete('/{id}', [AdminController::class, 'deleteFormType']);
    });

    // Workflow template management
    Route::prefix('admin/workflows')->group(function () {
        Route::get('/', [AdminController::class, 'getWorkflowTemplates']);
        Route::post('/', [AdminController::class, 'createWorkflowTemplate']);
        Route::put('/{id}', [AdminController::class, 'updateWorkflowTemplate']);
        Route::delete('/{id}', [AdminController::class, 'deleteWorkflowTemplate']);
    });

    // Workflow step management
    Route::prefix('admin/workflow-steps')->group(function () {
        Route::post('/', [AdminController::class, 'createWorkflowStep']);
        Route::put('/{id}', [AdminController::class, 'updateWorkflowStep']);
        Route::delete('/{id}', [AdminController::class, 'deleteWorkflowStep']);
        Route::post('/add-approver', [AdminController::class, 'addWorkflowStepApprover']);
        Route::post('/remove-approver', [AdminController::class, 'removeWorkflowStepApprover']);
    });
});
