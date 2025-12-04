<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * Search for employees (mock AD search)
     * In production, this would integrate with Active Directory
     */
    public function search(Request $request)
    {
        try {
            $query = $request->input('query', '');

            if (strlen($query) < 2) {
                return response()->json([
                    'data' => []
                ]);
            }

            // Search for users from the users table
            $employees = User::where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->where('role', '!=', 'admin') // Exclude admins
            ->with('departments') // Eager load departments to avoid N+1
            ->limit(10)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'department' => $user->departments->first()?->name ?? 'N/A',
                    'title' => $user->role ?? 'Employee',
                ];
            });

            return response()->json([
                'data' => $employees
            ]);
        } catch (\Exception $e) {
            \Log::error('Employee search error: ' . $e->getMessage(), [
                'query' => $request->input('query'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'data' => [],
                'error' => 'Failed to search employees'
            ], 500);
        }
    }
}
