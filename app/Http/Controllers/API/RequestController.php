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
        $perPage = $request->input('per_page', 12);
        $status = $request->input('status');
        $search = $request->input('search');

        $query = Request::where('user_id', $user->id)
            ->with(['ideaTypes', 'department', 'currentDepartment', 'workflowPath', 'attachments.uploader', 'employees']);

        // Filter by status if provided
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Search by title or description
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Get status counts for the filter tabs (only user's own ideas)
        $statusCounts = [
            'all' => Request::where('user_id', $user->id)->count(),
            'draft' => Request::where('user_id', $user->id)->where('status', 'draft')->count(),
            'pending' => Request::where('user_id', $user->id)->where('status', 'pending')->count(),
            'in_review' => Request::where('user_id', $user->id)->where('status', 'in_review')->count(),
            'need_more_details' => Request::where('user_id', $user->id)->where('status', 'need_more_details')->count(),
            'approved' => Request::where('user_id', $user->id)->where('status', 'approved')->count(),
            'rejected' => Request::where('user_id', $user->id)->where('status', 'rejected')->count(),
            'completed' => Request::where('user_id', $user->id)->where('status', 'completed')->count(),
        ];

        // Get ideas shared with the current user (where they are a collaborating employee but not the owner)
        $sharedIdeas = Request::where('user_id', '!=', $user->id)
            ->whereHas('employees', function($q) use ($user) {
                $q->where('employee_email', $user->email);
            })
            ->with(['ideaTypes', 'department', 'currentDepartment', 'workflowPath', 'employees', 'user:id,username'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'requests' => $requests->items(),
            'shared_ideas' => $sharedIdeas,
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'from' => $requests->firstItem(),
                'to' => $requests->lastItem(),
            ],
            'status_counts' => $statusCounts,
        ]);
    }

    public function store(HttpRequest $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'idea_types' => 'nullable|array', // Array of idea type IDs
            'idea_types.*' => 'integer|exists:idea_types,id', // Each idea type ID must exist
            'department' => 'required|string', // Can be department ID or "unknown"
            'benefits' => 'nullable|string',
            'status' => 'nullable|string|in:draft,pending',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,ppt,pptx|max:10240', // Max 10MB per file
            'idea_ownership_type' => 'nullable|string|in:individual,shared', // individual or shared
            'employees' => 'nullable|array',
            'employees.*.employee_name' => 'required|string',
            'employees.*.employee_email' => 'nullable|email',
            'employees.*.employee_department' => 'nullable|string',
            'employees.*.employee_title' => 'nullable|string',
            'resubmission_reason' => 'nullable|string|max:1000', // Reason for resubmitting rejected request
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

        // Log the ownership type being saved
        \Log::info('Creating request with idea_ownership_type', [
            'received_idea_ownership_type' => $validated['idea_ownership_type'] ?? 'NOT SET',
            'saving_as_idea_type' => $validated['idea_ownership_type'] ?? 'individual'
        ]);

        $userRequest = Request::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'department_id' => $selectedDepartmentId, // Store selected department
            'benefits' => $validated['benefits'] ?? null,
            'user_id' => $request->user()->id,
            'status' => $status,
            'idea_type' => $validated['idea_ownership_type'] ?? 'individual',
            'current_department_id' => $currentDepartmentId,
            'submitted_at' => $status === 'pending' ? now() : null,
        ]);

        // Sync idea types (many-to-many relationship)
        if (isset($validated['idea_types']) && is_array($validated['idea_types']) && !empty($validated['idea_types'])) {
            $userRequest->ideaTypes()->sync($validated['idea_types']);
        }

        // Log what was actually saved
        \Log::info('Request created with idea_type', [
            'request_id' => $userRequest->id,
            'saved_idea_type' => $userRequest->idea_type,
            'idea_types_count' => isset($validated['idea_types']) ? count($validated['idea_types']) : 0
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
                    'stage' => $status,
                    'uploaded_at' => now(),
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
            'request' => $userRequest->load(['ideaTypes', 'department', 'currentDepartment', 'workflowPath', 'attachments', 'employees'])
        ], 201);
    }

    public function show($id, HttpRequest $request)
    {
        $user = $request->user();

        // Get departments where user is manager or employee
        $userDepartments = $user->departments()->pluck('departments.id');

        // Find the request with permissions check
        $userRequest = Request::where('id', $id)
            ->where(function($query) use ($user, $userDepartments) {
                // User owns the request
                $query->where('user_id', $user->id)
                    // OR user has permission to view all requests (admin)
                    ->orWhere(function($q) use ($user) {
                        if ($user->hasPermissionTo('request.view-all')) {
                            $q->whereRaw('1=1'); // Allow all
                        }
                    })
                    // OR request is in user's department (as manager or employee)
                    ->orWhereIn('current_department_id', $userDepartments)
                    // OR request is assigned to user
                    ->orWhere('current_user_id', $user->id);
            })
            ->with(['user', 'ideaTypes', 'department', 'currentDepartment', 'workflowPath', 'attachments.uploader', 'employees', 'transitions.actionedBy', 'transitions.toDepartment'])
            ->firstOrFail();

        return response()->json([
            'request' => $userRequest
        ]);
    }

    public function update($id, HttpRequest $request)
    {
        $userRequest = Request::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['draft', 'need_more_details', 'rejected'])
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:200',
            'description' => 'sometimes|required|string',
            'idea_types' => 'nullable|array', // Array of idea type IDs
            'idea_types.*' => 'integer|exists:idea_types,id', // Each idea type ID must exist
            'department' => 'nullable|string',
            'benefits' => 'nullable|string',
            'additional_details' => 'sometimes|nullable|string',
            'status' => 'nullable|string|in:draft,pending',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,ppt,pptx|max:10240', // Max 10MB per file
            'idea_ownership_type' => 'nullable|string|in:individual,shared',
            'employees' => 'nullable|array',
            'employees.*.employee_name' => 'required|string',
            'employees.*.employee_email' => 'nullable|email',
            'employees.*.employee_department' => 'nullable|string',
            'employees.*.employee_title' => 'nullable|string',
            'resubmission_reason' => 'nullable|string|max:1000', // Reason for resubmitting rejected request
        ], [
            'idea_ownership_type.in' => __('validation.idea_ownership_type_invalid'),
        ]);

        // Prepare update data (excluding idea_types which will be synced separately)
        $updateData = $validated;
        if (isset($validated['idea_types'])) {
            unset($updateData['idea_types']);
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
        if ($status === 'pending' && in_array($userRequest->status, ['draft', 'need_more_details', 'rejected'])) {
            $departmentA = \App\Models\Department::where('is_department_a', true)->first();
            if ($departmentA) {
                $previousStatus = $userRequest->status;
                $isResubmit = $previousStatus === 'rejected';

                // Determine target department based on whether this is a resubmission
                $targetDepartmentId = $departmentA->id;
                $targetStatus = 'pending';
                $routeLeaderId = null;

                if ($isResubmit) {
                    // Find the last rejection transition to get the department and route leader who rejected
                    $lastRejection = \App\Models\RequestTransition::where('request_id', $userRequest->id)
                        ->whereIn('action', ['reject', 'reject_idea'])
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($lastRejection && $lastRejection->from_department_id) {
                        // Use the department where the request was rejected from directly
                        $targetDepartmentId = $lastRejection->from_department_id;
                        $routeLeaderId = $lastRejection->actioned_by;

                        // If resubmitting to route leader department (not Dept A), keep it in_review status
                        if ($targetDepartmentId !== $departmentA->id) {
                            $targetStatus = 'in_review';
                        }
                    }
                }

                $departmentId = $targetDepartmentId;
                $updateData['current_department_id'] = $departmentId;
                $updateData['status'] = $targetStatus;
                $updateData['submitted_at'] = now();
                $updateData['current_stage_started_at'] = now();

                // Determine action type based on previous status
                $actionType = $isResubmit ? 'resubmit' : 'submit';
                $comments = $isResubmit
                    ? ($validated['resubmission_reason'] ?? 'Request resubmitted after rejection')
                    : 'Request resubmitted for review';

                // Clear rejection reason if resubmitting
                if ($isResubmit) {
                    $updateData['rejection_reason'] = null;
                    $updateData['current_user_id'] = null; // Leave unassigned for manager to see
                }

                // Create transition
                \App\Models\RequestTransition::create([
                    'request_id' => $userRequest->id,
                    'to_department_id' => $departmentId,
                    'to_user_id' => $routeLeaderId,
                    'actioned_by' => $request->user()->id,
                    'action' => $actionType,
                    'from_status' => $userRequest->status,
                    'to_status' => $targetStatus,
                    'comments' => $comments,
                ]);

                $userRequest->update($updateData);

                // Send notifications to all stakeholders
                $this->notificationService->notifyRequestStakeholders(
                    $userRequest->fresh(['user', 'currentDepartment']),
                    NotificationService::TYPE_REQUEST_STATUS_CHANGED,
                    'Request Resubmitted',
                    "Request '{$userRequest->title}' has been resubmitted for review with explanation.",
                    ['previous_status' => $previousStatus, 'action' => 'resubmitted', 'resubmission_reason' => $validated['resubmission_reason'] ?? null]
                );
            }
        } else {
            $userRequest->update($updateData);
        }

        // Sync idea types if provided
        if (isset($validated['idea_types'])) {
            if (is_array($validated['idea_types']) && !empty($validated['idea_types'])) {
                $userRequest->ideaTypes()->sync($validated['idea_types']);
            } else {
                // If empty array provided, detach all
                $userRequest->ideaTypes()->detach();
            }
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

        // Handle deletion of existing attachments
        if ($request->has('delete_attachments')) {
            $attachmentIds = $request->input('delete_attachments');
            if (is_array($attachmentIds) && !empty($attachmentIds)) {
                // Only delete attachments that belong to this request
                $attachmentsToDelete = RequestAttachment::where('request_id', $userRequest->id)
                    ->whereIn('id', $attachmentIds)
                    ->get();

                foreach ($attachmentsToDelete as $attachment) {
                    $attachment->delete(); // This will also delete the file from storage
                }
            }
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
                    'stage' => $userRequest->status,
                    'uploaded_at' => now(),
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
            ->whereIn('status', ['draft', 'need_more_details', 'rejected'])
            ->firstOrFail();

        $validated = $request->validate([
            'resubmission_reason' => 'nullable|string|max:1000',
        ]);

        // Get Department A
        $departmentA = \App\Models\Department::where('is_department_a', true)->first();

        if (!$departmentA) {
            return response()->json([
                'message' => 'Department A not found. Please contact administrator.'
            ], 500);
        }

        $previousStatus = $userRequest->status;
        $isResubmit = $previousStatus === 'rejected';

        // Determine target department based on whether this is a resubmission
        $targetDepartmentId = $departmentA->id;
        $targetStatus = 'pending';
        $routeLeaderId = null;

        if ($isResubmit) {
            // Find the last rejection transition to get the department and route leader who rejected
            $lastRejection = \App\Models\RequestTransition::where('request_id', $userRequest->id)
                ->whereIn('action', ['reject', 'reject_idea'])
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastRejection && $lastRejection->from_department_id) {
                // Use the department where the request was rejected from directly
                $targetDepartmentId = $lastRejection->from_department_id;
                $routeLeaderId = $lastRejection->actioned_by;

                // If resubmitting to route leader department (not Dept A), keep it in_review status
                if ($targetDepartmentId !== $departmentA->id) {
                    $targetStatus = 'in_review';
                }
            }
        }

        $updateData = [
            'current_department_id' => $targetDepartmentId,
            'status' => $targetStatus,
            'submitted_at' => now(),
            'current_stage_started_at' => now(),
            'sla_reminder_sent_at' => null,
        ];

        // Clear rejection reason if resubmitting
        if ($isResubmit) {
            $updateData['rejection_reason'] = null;
            $updateData['current_user_id'] = null; // Leave unassigned for manager to see
        }

        $userRequest->update($updateData);

        // Determine action type and comments based on previous status
        $actionType = $isResubmit ? 'resubmit' : 'submit';
        $comments = $isResubmit
            ? ($validated['resubmission_reason'] ?? 'Request resubmitted after rejection')
            : 'Request submitted for review';

        // Create transition record
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'to_department_id' => $targetDepartmentId,
            'to_user_id' => $routeLeaderId,
            'actioned_by' => $request->user()->id,
            'action' => $actionType,
            'from_status' => $previousStatus,
            'to_status' => $targetStatus,
            'comments' => $comments,
        ]);

        // Send notifications to all stakeholders
        $notificationType = $isResubmit
            ? NotificationService::TYPE_REQUEST_STATUS_CHANGED
            : NotificationService::TYPE_REQUEST_CREATED;
        $notificationTitle = $isResubmit ? 'Request Resubmitted' : 'New Request Submitted';
        $notificationMessage = $isResubmit
            ? "Request '{$userRequest->title}' has been resubmitted for review with explanation."
            : "A new request '{$userRequest->title}' has been submitted and is pending review.";

        $this->notificationService->notifyRequestStakeholders(
            $userRequest->fresh(['user', 'currentDepartment']),
            $notificationType,
            $notificationTitle,
            $notificationMessage,
            ['action' => $isResubmit ? 'resubmitted' : 'submitted', 'previous_status' => $previousStatus, 'resubmission_reason' => $validated['resubmission_reason'] ?? null]
        );

        return response()->json([
            'message' => $isResubmit ? 'Request resubmitted successfully' : 'Request submitted successfully',
            'request' => $userRequest->load(['currentDepartment', 'workflowPath', 'currentAssignee'])
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
            'stage' => $userRequest->status,
            'uploaded_at' => now(),
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
     * Get all approved ideas for the Ideas Bank
     * Shows only ideas that are in progress or completed (excludes under review)
     * Visible to all authenticated users
     */
    public function getApprovedIdeas(HttpRequest $request)
    {
        $search = $request->input('search', '');
        $filter = $request->input('filter', 'all'); // all, in_progress, completed
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 12);

        // Show only ideas that are in_progress or completed (exclude under review)
        $query = Request::whereIn('status', ['in_progress', 'completed'])
            ->with(['user:id,username', 'ideaTypes:id,name,name_ar,color', 'department:id,name', 'workflowPath:id,name', 'currentAssignee:id,username', 'employees']);

        // Apply search filter
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($filter === 'in_progress') {
            $query->where('status', 'in_progress');
        } elseif ($filter === 'completed') {
            $query->where('status', 'completed');
        }
        // If filter is 'all', show both in_progress and completed (already filtered above)

        // Get total count for statistics
        $totalInProgress = Request::where('status', 'in_progress')->count();
        $totalCompleted = Request::where('status', 'completed')->count();
        $totalIdeas = $totalInProgress + $totalCompleted;

        // Get paginated results
        $ideas = $query->orderBy('updated_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'ideas' => $ideas->items(),
            'pagination' => [
                'current_page' => $ideas->currentPage(),
                'last_page' => $ideas->lastPage(),
                'per_page' => $ideas->perPage(),
                'total' => $ideas->total(),
            ],
            'stats' => [
                'total' => $totalIdeas,
                'in_progress' => $totalInProgress,
                'completed' => $totalCompleted,
            ]
        ]);
    }

    /**
     * Get dashboard statistics for the authenticated user
     */
    public function getStatistics(HttpRequest $request)
    {
        $user = $request->user();

        // Base query - different for each role
        // Note: Drafts from other users are excluded from statistics
        $baseQuery = null;

        if ($user->role === 'admin') {
            // Admin sees all non-draft requests + their own drafts
            $baseQuery = Request::where(function($query) use ($user) {
                $query->where('status', '!=', 'draft')
                      ->orWhere('user_id', $user->id);
            });
        } elseif ($user->role === 'manager') {
            // Check if manager is in Department A
            $managedDepartments = $user->departments()
                ->where('department_user.role', 'manager')
                ->pluck('departments.id');

            $deptA = \App\Models\Department::where('is_department_a', true)->first();

            // If user manages Department A, they see ALL non-draft requests + their own drafts
            if ($deptA && $managedDepartments->contains($deptA->id)) {
                $baseQuery = Request::where(function($query) use ($user) {
                    $query->where('status', '!=', 'draft')
                          ->orWhere('user_id', $user->id);
                });
            } else {
                // Other managers see only non-draft requests in their departments + their own drafts
                $baseQuery = Request::where(function($query) use ($user, $managedDepartments) {
                    $query->where(function($q) use ($managedDepartments) {
                        $q->whereIn('current_department_id', $managedDepartments)
                          ->where('status', '!=', 'draft');
                    })->orWhere('user_id', $user->id);
                });
            }
        } elseif ($user->role === 'employee') {
            // Employee sees non-draft requests (assigned to them or in their departments) + their own drafts
            $userDepartments = $user->departments()->pluck('departments.id');

            $baseQuery = Request::where(function($query) use ($user, $userDepartments) {
                $query->where(function($q) use ($user, $userDepartments) {
                    $q->where('current_user_id', $user->id)
                      ->orWhereIn('current_department_id', $userDepartments);
                })->where('status', '!=', 'draft');
            })->orWhere('user_id', $user->id);
        } else {
            // Regular user sees only their own requests (including drafts)
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
