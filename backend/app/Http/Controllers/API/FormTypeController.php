<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FormType;

class FormTypeController extends Controller
{
    /**
     * Get all active form types
     */
    public function index()
    {
        try {
            $formTypes = FormType::active()
                ->with('activeWorkflowTemplate')
                ->get();

            return response()->json([
                'formTypes' => $formTypes,
                'count' => $formTypes->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch form types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single form type with its workflow
     */
    public function show($id)
    {
        try {
            $formType = FormType::with(['activeWorkflowTemplate.steps' => function ($query) {
                $query->orderBy('step_order');
            }])->findOrFail($id);

            return response()->json([
                'formType' => $formType
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Form type not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
