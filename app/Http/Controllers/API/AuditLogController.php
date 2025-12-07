<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs
     */
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by model type
        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search in description
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        // Sort by
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 50);
        $logs = $query->paginate($perPage);

        return response()->json([
            'logs' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ]
        ]);
    }

    /**
     * Display the specified audit log
     */
    public function show($id)
    {
        $log = AuditLog::with('user')->findOrFail($id);

        return response()->json([
            'log' => $log
        ]);
    }

    /**
     * Get audit log statistics
     */
    public function stats(Request $request)
    {
        $query = AuditLog::query();

        // Apply date filter if provided
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $stats = [
            'total_logs' => $query->count(),
            'by_action' => AuditLog::selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->pluck('count', 'action'),
            'by_model_type' => AuditLog::selectRaw('model_type, COUNT(*) as count')
                ->whereNotNull('model_type')
                ->groupBy('model_type')
                ->pluck('count', 'model_type'),
            'recent_activities' => AuditLog::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Get unique filter options
     */
    public function filters()
    {
        $actions = AuditLog::distinct()->pluck('action')->filter()->values();
        $modelTypes = AuditLog::distinct()->whereNotNull('model_type')->pluck('model_type')->filter()->values();

        return response()->json([
            'actions' => $actions,
            'model_types' => $modelTypes,
        ]);
    }

    /**
     * Delete old audit logs (for cleanup)
     */
    public function cleanup(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:1'
        ]);

        $cutoffDate = now()->subDays($request->days);
        $deleted = AuditLog::where('created_at', '<', $cutoffDate)->delete();

        return response()->json([
            'message' => "Deleted {$deleted} audit log(s) older than {$request->days} days",
            'deleted_count' => $deleted
        ]);
    }
}
