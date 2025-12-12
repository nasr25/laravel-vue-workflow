<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Request;
use App\Models\RequestAttachment;
use App\Models\AuditLog;
use App\Services\NotificationService;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Storage;

class RequestController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function getDepartments()
    {
        $departments = \App\Models\Department::select('id', 'name', 'is_department_a')->get();

        return response()->json([
            'departments' => $departments
        ]);
    }

    public function index(HttpRequest $request)
    {
        $user = $request->user();

        $requests = Request::where('user_id', $user->id)
            ->with(['ideaType', 'department', 'currentDepartment', 'workflowPath', 'attachments', 'employees'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'requests' => $requests
        ]);
    }

    public function store(HttpRequest $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'required|string|min:25',
            'idea_type' => 'required|string', // Can be idea type ID or old format
            'department' => 'required|string', // Can be department ID or "unknown"
            'benefits' => 'nullable|string',
            'status' => 'nullable|string|in:draft,pending',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240', // Max 10MB per file
            'idea_ownership_type' => 'nullable|string|in:individual,shared', // individual or shared
            'employees' => 'nullable|array',
            'employees.*.employee_name' => 'required|string',
            'employees.*.employee_email' => 'nullable|email',
            'employees.*.employee_department' => 'nullable|string',
            'employees.*.employee_title' => 'nullable|string',
        ], [
            'idea_ownership_type.in' => __('validation.idea_ownership_type_invalid'),
        ]);

        // Determine initial department and status
        $status = $validated['status'] ?? 'draft';
        $currentDepartmentId = null;

        // Handle department selection - can be department ID or "unknown"
        $selectedDepartmentId = null;
        if ($validated['department'] !== 'unknown') {
            $selectedDepartmentId = (int) $validated['department'];
        }

        // If submitting directly (not draft), assign to Department A
        if ($status === 'pending') {
            $departmentA = \App\Models\Department::where('is_department_a', true)->first();
            if ($departmentA) {
                $currentDepartmentId = $departmentA->id;
            }
        }

        $userRequest = Request::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'idea_type_id' => (int) $validated['idea_type'], // Store idea type ID
            'department_id' => $selectedDepartmentId, // Store selected department
            'benefits' => $validated['benefits'] ?? null,
            'user_id' => $request->user()->id,
            'status' => $status,
            'idea_type' => $validated['idea_ownership_type'] ?? 'individual',
            'current_department_id' => $currentDepartmentId,
            'submitted_at' => $status === 'pending' ? now() : null,
        ]);

        // Handle employees if shared idea
        if (isset($validated['idea_ownership_type']) && $validated['idea_ownership_type'] === 'shared' && isset($validated['employees']) && is_array($validated['employees'])) {
            foreach ($validated['employees'] as $employee) {
                \App\Models\RequestEmployee::create([
                    'request_id' => $userRequest->id,
                    'employee_name' => $employee['employee_name'],
                    'employee_email' => $employee['employee_email'] ?? null,
                    'employee_department' => $employee['employee_department'] ?? null,
                    'employee_title' => $employee['employee_title'] ?? null,
                ]);
            }
        }

        // Handle file attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments', 'public');

                RequestAttachment::create([
                    'request_id' => $userRequest->id,
                    'uploaded_by' => $request->user()->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        // Create transition if submitted
        if ($status === 'pending' && $currentDepartmentId) {
            \App\Models\RequestTransition::create([
                'request_id' => $userRequest->id,
                'to_department_id' => $currentDepartmentId,
                'actioned_by' => $request->user()->id,
                'action' => 'submit',
                'from_status' => 'draft',
                'to_status' => 'pending',
                'comments' => 'Request submitted for review',
            ]);

            // Send notifications to all stakeholders
            $this->notificationService->notifyRequestStakeholders(
                $userRequest->fresh(['user', 'currentDepartment']),
                NotificationService::TYPE_REQUEST_CREATED,
                'New Request Submitted',
                "A new request '{$userRequest->title}' has been submitted and is pending review.",
                ['action' => 'submitted']
            );
        }

        // Log request creation
        AuditLog::log([
            'user_id' => $request->user()->id,
            'action' => $status === 'pending' ? 'submitted' : 'created',
            'model_type' => 'Request',
            'model_id' => $userRequest->id,
            'description' => $status === 'pending'
                ? "User submitted request: {$userRequest->title}"
                : "User saved request draft: {$userRequest->title}",
        ]);

        return response()->json([
            'message' => $status === 'pending' ? 'Idea submitted successfully' : 'Draft saved successfully',
            'request' => $userRequest->load(['ideaType', 'department', 'currentDepartment', 'workflowPath', 'attachments', 'employees'])
        ], 201);
    }

    public function show($id, HttpRequest $request)
    {
        $userRequest = Request::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with(['user', 'ideaType', 'department', 'currentDepartment', 'workflowPath', 'attachments', 'employees', 'transitions.actionedBy', 'transitions.toDepartment'])
            ->firstOrFail();

        return response()->json([
            'request' => $userRequest
        ]);
    }

    public function update($id, HttpRequest $request)
    {
        $userRequest = Request::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['draft', 'need_more_details'])
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:200',
            'description' => 'sometimes|required|string|min:25',
            'idea_type' => 'nullable|string', // Can be idea type ID
            'department' => 'nullable|string',
            'benefits' => 'nullable|string',
            'additional_details' => 'sometimes|nullable|string',
            'status' => 'nullable|string|in:draft,pending',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240', // Max 10MB per file
            'idea_ownership_type' => 'nullable|string|in:individual,shared',
            'employees' => 'nullable|array',
            'employees.*.employee_name' => 'required|string',
            'employees.*.employee_email' => 'nullable|email',
            'employees.*.employee_department' => 'nullable|string',
            'employees.*.employee_title' => 'nullable|string',
        ], [
            'idea_ownership_type.in' => __('validation.idea_ownership_type_invalid'),
        ]);

        // Handle idea_type conversion to idea_type_id
        $updateData = $validated;
        if (isset($validated['idea_type'])) {
            $updateData['idea_type_id'] = (int) $validated['idea_type'];
            unset($updateData['idea_type']);
        }

        // Handle department conversion to department_id
        if (isset($validated['department'])) {
            if ($validated['department'] === 'unknown') {
                $updateData['department_id'] = null;
            } else {
                $updateData['department_id'] = (int) $validated['department'];
            }
            unset($updateData['department']);
        }

        // Handle idea_ownership_type
        if (isset($validated['idea_ownership_type'])) {
            $updateData['idea_type'] = $validated['idea_ownership_type'];
            unset($updateData['idea_ownership_type']);
        }

        // Determine if status should change
        $status = $validated['status'] ?? $userRequest->status;
        $departmentId = $userRequest->current_department_id;

        // If submitting (changing from draft/need_more_details to pending)
        if ($status === 'pending' && in_array($userRequest->status, ['draft', 'need_more_details'])) {
            $departmentA = \App\Models\Department::where('is_department_a', true)->first();
            if ($departmentA) {
                $departmentId = $departmentA->id;
                $updateData['current_department_id'] = $departmentId;
                $updateData['submitted_at'] = now();

                $previousStatus = $userRequest->status;

                // Create transition
                \App\Models\RequestTransition::create([
                    'request_id' => $userRequest->id,
                    'to_department_id' => $departmentId,
                    'actioned_by' => $request->user()->id,
                    'action' => 'submit',
                    'from_status' => $userRequest->status,
                    'to_status' => 'pending',
                    'comments' => 'Request resubmitted for review',
                ]);

                $userRequest->update($updateData);

                // Send notifications to all stakeholders
                $this->notificationService->notifyRequestStakeholders(
                    $userRequest->fresh(['user', 'currentDepartment']),
                    NotificationService::TYPE_REQUEST_STATUS_CHANGED,
                    'Request Resubmitted',
                    "Request '{$userRequest->title}' has been resubmitted for review.",
                    ['previous_status' => $previousStatus, 'action' => 'resubmitted']
                );
            }
        } else {
            $userRequest->update($updateData);
        }

        // Handle employees update if provided
        if (isset($validated['employees'])) {
            // Delete existing employees
            \App\Models\RequestEmployee::where('request_id', $userRequest->id)->delete();

            // Add new employees only if idea_ownership_type is 'shared'
            $ownershipType = $validated['idea_ownership_type'] ?? $userRequest->idea_type;
            if ($ownershipType === 'shared' && is_array($validated['employees'])) {
                foreach ($validated['employees'] as $employee) {
                    \App\Models\RequestEmployee::create([
                        'request_id' => $userRequest->id,
                        'employee_name' => $employee['employee_name'],
                        'employee_email' => $employee['employee_email'] ?? null,
                        'employee_department' => $employee['employee_department'] ?? null,
                        'employee_title' => $employee['employee_title'] ?? null,
                    ]);
                }
            }
        } elseif (isset($validated['idea_ownership_type']) && $validated['idea_ownership_type'] === 'individual') {
            // If changing to individual, remove all employees
            \App\Models\RequestEmployee::where('request_id', $userRequest->id)->delete();
        }

        // Handle file attachments
        if ($request->hasFile('attachments')) {
            $existingAttachmentsCount = $userRequest->attachments()->count();
            $newAttachmentsCount = count($request->file('attachments'));

            // Check if total attachments would exceed limit
            if ($existingAttachmentsCount + $newAttachmentsCount > 5) {
                return response()->json([
                    'message' => 'Cannot add files. Maximum 5 files allowed per request.',
                    'errors' => [
                        'attachments' => ['Maximum 5 files allowed per request']
                    ]
                ], 422);
            }

            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments', 'public');

                RequestAttachment::create([
                    'request_id' => $userRequest->id,
                    'uploaded_by' => $request->user()->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        // Log request update
        AuditLog::log([
            'user_id' => $request->user()->id,
            'action' => 'updated',
            'model_type' => 'Request',
            'model_id' => $userRequest->id,
            'description' => "User updated request: {$userRequest->title}",
        ]);

        return response()->json([
            'message' => $status === 'pending' ? 'Idea submitted successfully' : 'Draft updated successfully',
            'request' => $userRequest->load(['currentDepartment', 'workflowPath', 'attachments'])
        ]);
    }

    public function destroy($id, HttpRequest $request)
    {
        $userRequest = Request::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'draft')
            ->firstOrFail();

        $requestTitle = $userRequest->title;
        $userRequest->delete();

        // Log request deletion
        AuditLog::log([
            'user_id' => $request->user()->id,
            'action' => 'deleted',
            'model_type' => 'Request',
            'model_id' => $id,
            'description' => "User deleted request draft: {$requestTitle}",
        ]);

        return response()->json([
            'message' => 'Request deleted successfully'
        ]);
    }

    public function submit($id, HttpRequest $request)
    {
        $userRequest = Request::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['draft', 'need_more_details'])
            ->firstOrFail();

        // Get Department A
        $departmentA = \App\Models\Department::where('is_department_a', true)->first();

        if (!$departmentA) {
            return response()->json([
                'message' => 'Department A not found. Please contact administrator.'
            ], 500);
        }

        $userRequest->update([
            'current_department_id' => $departmentA->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Create transition record
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'to_department_id' => $departmentA->id,
            'actioned_by' => $request->user()->id,
            'action' => 'submit',
            'from_status' => 'draft',
            'to_status' => 'pending',
            'comments' => 'Request submitted for review',
        ]);

        // Send notifications to all stakeholders
        $this->notificationService->notifyRequestStakeholders(
            $userRequest->fresh(['user', 'currentDepartment']),
            NotificationService::TYPE_REQUEST_CREATED,
            'New Request Submitted',
            "A new request '{$userRequest->title}' has been submitted and is pending review.",
            ['action' => 'submitted']
        );

        return response()->json([
            'message' => 'Request submitted successfully',
            'request' => $userRequest->load(['currentDepartment', 'workflowPath'])
        ]);
    }

    public function uploadAttachment($id, HttpRequest $request)
    {
        $userRequest = Request::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $path = $file->store('attachments', 'public');

        $attachment = RequestAttachment::create([
            'request_id' => $userRequest->id,
            'uploaded_by' => $request->user()->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return response()->json([
            'message' => 'File uploaded successfully',
            'attachment' => $attachment
        ], 201);
    }

    public function deleteAttachment($requestId, $attachmentId, HttpRequest $request)
    {
        $attachment = RequestAttachment::where('id', $attachmentId)
            ->where('request_id', $requestId)
            ->whereHas('request', function($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json([
            'message' => 'Attachment deleted successfully'
        ]);
    }

    /**
     * Get dashboard statistics for the authenticated user
     */
    public function getStatistics(HttpRequest $request)
    {
        $user = $request->user();

        // Base query - different for each role
        $baseQuery = null;

        if ($user->role === 'admin') {
            // Admin sees all requests
            $baseQuery = Request::query();
        } elseif ($user->role === 'manager') {
            // Check if manager is in Department A
            $managedDepartments = $user->departments()
                ->where('department_user.role', 'manager')
                ->pluck('departments.id');

            $deptA = \App\Models\Department::where('is_department_a', true)->first();

            // If user manages Department A, they see ALL requests
            if ($deptA && $managedDepartments->contains($deptA->id)) {
                $baseQuery = Request::query();
            } else {
                // Other managers see only requests in their departments
                $baseQuery = Request::whereIn('current_department_id', $managedDepartments);
            }
        } elseif ($user->role === 'employee') {
            // Employee sees:
            // 1. Requests assigned to them
            // 2. Requests in their departments
            $userDepartments = $user->departments()->pluck('departments.id');

            $baseQuery = Request::where(function($query) use ($user, $userDepartments) {
                $query->where('current_user_id', $user->id)
                      ->orWhereIn('current_department_id', $userDepartments);
            });
        } else {
            // Regular user sees only their own requests
            $baseQuery = Request::where('user_id', $user->id);
        }

        // Calculate statistics
        $stats = [
            'totalRequests' => (clone $baseQuery)->count(),
            'pendingRequests' => (clone $baseQuery)->whereIn('status', ['pending', 'in_review'])->count(),
            'inProgressRequests' => (clone $baseQuery)->where('status', 'in_progress')->count(),
            'approvedRequests' => (clone $baseQuery)->where('status', 'approved')->count(),
            'rejectedRequests' => (clone $baseQuery)->where('status', 'rejected')->count(),
            'draftRequests' => (clone $baseQuery)->where('status', 'draft')->count(),
            'completedRequests' => (clone $baseQuery)->where('status', 'completed')->count(),
        ];

        return response()->json([
            'stats' => $stats
        ]);
    }
}
