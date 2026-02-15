<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\IdeaType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IdeaTypeController extends Controller
{
    /**
     * Display a listing of the idea types.
     * Returns only active types for regular users, all types for admins.
     */
    public function index(Request $request)
    {
        // Check if user is admin (for admin panel usage)
        $user = $request->user();
        $isAdmin = $user && $user->role === 'admin';

        $query = IdeaType::ordered();

        // If not admin, only show active types
        if (!$isAdmin) {
            $query->active();
        }

        $ideaTypes = $query->get();

        return response()->json([
            'ideaTypes' => $ideaTypes
        ]);
    }

    /**
     * Store a newly created idea type in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $ideaType = IdeaType::create($validator->validated());

        return response()->json([
            'message' => 'Idea type created successfully',
            'ideaType' => $ideaType
        ], 201);
    }

    /**
     * Quick store - allows any authenticated user to create a new idea type
     * with just name and name_ar. Color is auto-generated.
     */
    public function quickStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $ideaType = IdeaType::create([
            'name' => $request->name,
            'name_ar' => $request->name_ar,
            'description' => '',
            'description_ar' => '',
            'color' => '#6b7280',
            'is_active' => false,
            'order' => 0,
        ]);

        return response()->json([
            'message' => 'Idea type created successfully',
            'ideaType' => $ideaType
        ], 201);
    }

    /**
     * Display the specified idea type.
     */
    public function show(IdeaType $ideaType)
    {
        return response()->json([
            'ideaType' => $ideaType
        ]);
    }

    /**
     * Update the specified idea type in storage.
     */
    public function update(Request $request, IdeaType $ideaType)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $ideaType->update($validator->validated());

        return response()->json([
            'message' => 'Idea type updated successfully',
            'ideaType' => $ideaType
        ]);
    }

    /**
     * Remove the specified idea type from storage.
     */
    public function destroy(IdeaType $ideaType)
    {
        $ideaType->delete();

        return response()->json([
            'message' => 'Idea type deleted successfully'
        ]);
    }

    /**
     * Toggle the active status of the specified idea type.
     */
    public function toggleStatus(IdeaType $ideaType)
    {
        $ideaType->update(['is_active' => !$ideaType->is_active]);

        return response()->json([
            'message' => 'Idea type status updated successfully',
            'ideaType' => $ideaType
        ]);
    }
}
