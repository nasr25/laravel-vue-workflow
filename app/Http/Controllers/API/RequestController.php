<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Request;
use App\Models\RequestAttachment;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Storage;

class RequestController extends Controller
{
    public function getDepartments()
    {
        $departments = \App\Models\Department::select('id', 'name')->get();

        return response()->json([
            'departments' => $departments
        ]);
    }

    public function index(HttpRequest $request)
    {
        $user = $request->user();

        $requests = Request::where('user_id', $user->id)
            ->with(['currentDepartment', 'workflowPath', 'attachments'])
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
            'idea_type' => 'nullable|string|in:x,y,z',
            'department' => 'nullable|string',
            'benefits' => 'nullable|string',
            'status' => 'nullable|string|in:draft,pending',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240', // Max 10MB per file
        ]);

        // Determine initial department and status
        $status = $validated['status'] ?? 'draft';
        $departmentId = null;

        // If submitting directly (not draft), assign to Department A
        if ($status === 'pending') {
            $departmentA = \App\Models\Department::where('is_department_a', true)->first();
            if ($departmentA) {
                $departmentId = $departmentA->id;
            }
        }

        $userRequest = Request::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'user_id' => $request->user()->id,
            'status' => $status,
            'current_department_id' => $departmentId,
            'submitted_at' => $status === 'pending' ? now() : null,
        ]);

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
        if ($status === 'pending' && $departmentId) {
            \App\Models\RequestTransition::create([
                'request_id' => $userRequest->id,
                'to_department_id' => $departmentId,
                'actioned_by' => $request->user()->id,
                'action' => 'submit',
                'from_status' => 'draft',
                'to_status' => 'pending',
                'comments' => 'Request submitted for review',
            ]);
        }

        return response()->json([
            'message' => $status === 'pending' ? 'Idea submitted successfully' : 'Draft saved successfully',
            'request' => $userRequest->load(['currentDepartment', 'workflowPath', 'attachments'])
        ], 201);
    }

    public function show($id, HttpRequest $request)
    {
        $userRequest = Request::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with(['currentDepartment', 'workflowPath', 'attachments', 'transitions.actionedBy', 'transitions.toDepartment'])
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
            'idea_type' => 'nullable|string|in:x,y,z',
            'department' => 'nullable|string',
            'benefits' => 'nullable|string',
            'additional_details' => 'sometimes|nullable|string',
            'status' => 'nullable|string|in:draft,pending',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240', // Max 10MB per file
        ]);

        // Determine if status should change
        $status = $validated['status'] ?? $userRequest->status;
        $departmentId = $userRequest->current_department_id;

        // If submitting (changing from draft/need_more_details to pending)
        if ($status === 'pending' && in_array($userRequest->status, ['draft', 'need_more_details'])) {
            $departmentA = \App\Models\Department::where('is_department_a', true)->first();
            if ($departmentA) {
                $departmentId = $departmentA->id;
                $validated['current_department_id'] = $departmentId;
                $validated['submitted_at'] = now();

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
            }
        }

        $userRequest->update($validated);

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

        $userRequest->delete();

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
}
