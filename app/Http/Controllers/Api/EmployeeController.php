<?php

namespace App\Http\Controllers\Api;

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
        $query = $request->input('query', '');

        if (strlen($query) < 2) {
            return response()->json([
                'data' => []
            ]);
        }

        // Mock search - in production, replace with actual AD integration
        $employees = User::where(function($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
              ->orWhere('email', 'like', "%{$query}%");
        })
        ->where('role', '!=', 'admin') // Exclude admins
        ->limit(10)
        ->get()
        ->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->departments()->first()?->name ?? 'N/A',
                'title' => $user->role ?? 'Employee',
            ];
        });

        return response()->json([
            'data' => $employees
        ]);
    }
}
