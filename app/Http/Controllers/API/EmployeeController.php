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
    

    // public function search(Request $request)
    // {
    //     try {
    //         $query = $request->input('query', '');

    //         if (strlen($query) < 2) {
    //             return response()->json([
    //                 'data' => []
    //             ]);
    //         }

    //         // Search for users from external API
    //         $response = \Http::withOptions(['verify' => false])
    //             ->withHeaders(['Accept-Language' => 'ar'])
    //             ->timeout(10)
    //             ->get(env('LDAP_SEARCH_API'), [
    //                 'search' => $query,
    //             ]);

    //         // Check if request was successful
    //         if (!$response->successful()) {
    //             \Log::error('External API search failed', [
    //                 'status' => $response->status(),
    //                 'query' => $query
    //             ]);
                
    //             return response()->json([
    //                 'data' => [],
    //                 'error' => 'Failed to search employees'
    //             ], 500);
    //         }

    //         $apiData = $response->json();
            
    //         // Map the external API response to your expected format
    //         $employees = collect($apiData['data'] ?? $apiData ?? [])
    //             ->filter(function($user) {
    //                 // Exclude admins if needed (adjust based on API response structure)
    //                 return ($user['role'] ?? '') !== 'admin';
    //             })
    //             ->take(10) // Limit to 10 results
    //             ->map(function($user) {
    //                 return [
    //                     'id' => $user['id'] ?? null,
    //                     'name' => $user['username'] ?? 'N/A',
    //                     'username' => $user['name_en'] ?? 'N/A',
    //                     'email' => $user['email'] ?? 'N/A',
    //                     'department' => $user['department'] ?? 'N/A',
    //                     'title' => $user['title'] ?? $user['role'] ?? 'Employee',
    //                 ];
    //             })
    //             ->values();

    //         return response()->json([
    //             'data' => $employees
    //         ]);

    //     } catch (\Illuminate\Http\Client\RequestException $e) {
    //         \Log::error('External API request error: ' . $e->getMessage(), [
    //             'query' => $request->input('query'),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'data' => [],
    //             'error' => 'Failed to search employees'
    //         ], 500);

    //     } catch (\Exception $e) {
    //         \Log::error('Employee search error: ' . $e->getMessage(), [
    //             'query' => $request->input('query'),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'data' => [],
    //             'error' => 'Failed to search employees'
    //         ], 500);
    //     }
    // }
}
