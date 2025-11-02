<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\IdeaController;
use App\Http\Controllers\API\ApprovalController;
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

    // ========== MANAGER ROUTES ==========
    // Approval management for managers
    Route::prefix('approvals')->group(function () {
        Route::get('/pending', [ApprovalController::class, 'pendingIdeas']);
        Route::get('/all-ideas', [ApprovalController::class, 'allIdeas']);
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
    });

    // Admin statistics
    Route::get('/admin/pending-ideas-count', [AdminController::class, 'getPendingIdeasCount']);
});
