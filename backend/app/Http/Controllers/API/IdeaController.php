<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Services\IdeaWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class IdeaController extends Controller
{
    protected $workflowService;

    public function __construct(IdeaWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Get all ideas for authenticated user
     */
    public function myIdeas(Request $request)
    {
        try {
            $ideas = Idea::where('user_id', $request->user()->id)
                ->with('approvals.department', 'approvals.manager')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'ideas' => $ideas,
            ]);
        } catch (\Exception $e) {
            \Log::error('Get my ideas error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ideas'
            ], 500);
        }
    }

    /**
     * Get a single idea
     */
    public function show($id, Request $request)
    {
        try {
            $idea = Idea::with('approvals.department', 'approvals.manager', 'user')
                ->findOrFail($id);

            // Check if user owns this idea or is admin/manager
            if ($idea->user_id !== $request->user()->id &&
                !$request->user()->isAdmin() &&
                !$request->user()->isManager()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'idea' => $idea,
            ]);
        } catch (\Exception $e) {
            \Log::error('Get idea error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch idea'
            ], 500);
        }
    }

    /**
     * Create a new idea (draft)
     */
    public function store(Request $request)
    {
        try {
            // Relaxed validation for drafts - allow saving work in progress
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|min:1',
                'description' => 'required|string|max:5000|min:1',
                'pdf_file' => 'nullable|file|mimes:pdf|max:10240|mimetypes:application/pdf', // 10MB max, strict MIME check
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $pdfPath = null;
            if ($request->hasFile('pdf_file')) {
                $pdfPath = $request->file('pdf_file')->store('ideas', 'public');
            }

            // Sanitize inputs
            $name = strip_tags(trim($request->name));
            $description = strip_tags(trim($request->description));

            $idea = Idea::create([
                'user_id' => $request->user()->id,
                'name' => $name,
                'description' => $description,
                'pdf_file_path' => $pdfPath,
                'status' => 'draft',
                'current_approval_step' => 0,
            ]);

            return response()->json([
                'success' => true,
                'idea' => $idea,
                'message' => 'Idea created successfully'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Create idea error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create idea'
            ], 500);
        }
    }

    /**
     * Update an idea (only if draft or returned)
     */
    public function update(Request $request, $id)
    {
        try {
            $idea = Idea::findOrFail($id);

            // Check ownership
            if ($idea->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Only allow editing if draft or returned
            if (!in_array($idea->status, ['draft', 'returned'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit idea in current status'
                ], 422);
            }

            // Relaxed validation for draft updates
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255|min:1',
                'description' => 'sometimes|string|max:5000|min:1',
                'pdf_file' => 'nullable|file|mimes:pdf|max:10240|mimetypes:application/pdf',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Sanitize inputs
            $data = [];
            if ($request->has('name')) {
                $data['name'] = strip_tags(trim($request->name));
            }
            if ($request->has('description')) {
                $data['description'] = strip_tags(trim($request->description));
            }

            if ($request->hasFile('pdf_file')) {
                // Delete old file if exists
                if ($idea->pdf_file_path) {
                    Storage::disk('public')->delete($idea->pdf_file_path);
                }
                $data['pdf_file_path'] = $request->file('pdf_file')->store('ideas', 'public');
            }

            $idea->update($data);

            return response()->json([
                'success' => true,
                'idea' => $idea,
                'message' => 'Idea updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Update idea error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update idea'
            ], 500);
        }
    }

    /**
     * Submit idea for approval
     */
    public function submit($id, Request $request)
    {
        try {
            $idea = Idea::findOrFail($id);

            // Check ownership
            if ($idea->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Only allow submission if draft or returned
            if (!in_array($idea->status, ['draft', 'returned'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Idea already submitted'
                ], 422);
            }

            // Validate idea completeness before submission
            if (strlen($idea->name) < 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Idea name must be at least 3 characters long before submission'
                ], 422);
            }

            if (strlen($idea->description) < 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Idea description must be at least 10 characters long before submission'
                ], 422);
            }

            $this->workflowService->submitIdea($idea);

            return response()->json([
                'success' => true,
                'idea' => $idea->load('approvals.department'),
                'message' => 'Idea submitted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Submit idea error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an idea (only if draft)
     */
    public function destroy($id, Request $request)
    {
        try {
            $idea = Idea::findOrFail($id);

            // Check ownership
            if ($idea->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Only allow deletion if draft
            if ($idea->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete idea in current status'
                ], 422);
            }

            // Delete PDF file if exists
            if ($idea->pdf_file_path) {
                Storage::disk('public')->delete($idea->pdf_file_path);
            }

            $idea->delete();

            return response()->json([
                'success' => true,
                'message' => 'Idea deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete idea error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete idea'
            ], 500);
        }
    }
}
