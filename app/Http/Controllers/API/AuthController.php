<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'is_active' => true,
        ]);

        // Log user registration
        AuditLog::log([
            'user_id' => $user->id,
            'action' => 'registered',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => "User {$user->name} registered with email {$user->email}",
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /*
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Account is inactive'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Log user login
        AuditLog::log([
            'user_id' => $user->id,
            'action' => 'logged_in',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => "User {$user->name} logged in",
        ]);

        // Load user with relationships and permissions
        $user->load(['departments', 'roles.permissions']);

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames()
        ]);
    }
    */

    public function login(Request $request)
    {
        // First, check if user exists locally with a password (custom/test users)
        $user = User::where('email', $request->username)
            ->orWhere('name', $request->username)
            ->orWhere('username', $request->username)
            ->first();

        // If user exists and has a password set, authenticate locally
        if ($user && !empty($user->password)) {
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid credentials'
                ], 401);
            }

            if (!$user->is_active) {
                return response()->json([
                    'message' => 'Account is inactive'
                ], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            // Log user login
            AuditLog::log([
                'user_id' => $user->id,
                'action' => 'logged_in',
                'model_type' => 'User',
                'model_id' => $user->id,
                'description' => "User {$user->name} logged in (local auth)",
            ]);

            // Load user with relationships and permissions
            $user->load(['departments', 'roles.permissions']);

            return response()->json([
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'roles' => $user->getRoleNames()
            ]);
        }

        // If no local user with password, try external API
        $response = \Http::withOptions(['verify' => false])
                ->withHeaders(['Accept-Language' => 'ar'])
                ->timeout(10)
                ->post(env('LDAP_LOGIN_API'), [
                    'username' => $request->username,
                    'password' => $request->password,
                ]);

        if($response && $response['status'] == true) {
            $user = User::where('name', $request->username)->orWhere('username', $request->username)->first();

            if ($user && !$user->is_active) {
                return response()->json([
                    'message' => __('messages.accountIsInactive')
                ], 403);
            }

            if(!$user) {
                $user = User::create([
                    'name' => $response['data']['username'] ?? 'N/A',
                    'username' => $response['data']['full_name'] ?? 'N/A',
                    'email' => $response['data']['email'] ?? 'N/A',
                    'role' => 'user',
                    'is_active' => true,
                ]);

                $role = Role::where('name', 'LIKE', 'user')->first();
                $user->assignRole($role);

                if($response['data']['ou'] && $response['data']['ou'] != null) {
                    $department = Department::whereIn('title', $response['data']['ou'])->first();
                    if($department) {
                        // Check if already assigned
                        if (!$user->departments()->where('departments.id', $department->id)->exists()) {
                            $user->departments()->attach($department->id, ['role' => 'employee']);
                        }
                    }
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            // Log user login
            AuditLog::log([
                'user_id' => $user->id,
                'action' => 'logged_in',
                'model_type' => 'User',
                'model_id' => $user->id,
                'description' => "User {$user->name} logged in (external API)",
            ]);

            // Load user with relationships and permissions
            $user->load(['departments', 'roles.permissions']);

            return response()->json([
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'roles' => $user->getRoleNames()
            ]);
        } else {
            return response()->json([
                'message' => $response['message'] ?? 'Login failed'
            ], $response['code'] ?? 401);
        }
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        // Log user logout
        AuditLog::log([
            'user_id' => $user->id,
            'action' => 'logged_out',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => "User {$user->name} logged out",
        ]);

        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user()->load(['departments', 'roles.permissions']);

        return response()->json([
            'user' => $user,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames()
        ]);
    }

    public function getDemoAccounts()
    {
        // Get all active users with passwords (custom/test users) for demo purposes
        $users = User::where('is_active', true)
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->with('departments')
            ->orderBy('id')
            ->get()
            ->map(function ($user) {
                // Determine icon based on role
                $icon = '👤';
                if ($user->role === 'admin') {
                    $icon = '👨‍💼';
                } elseif ($user->departments->isNotEmpty()) {
                    // Check if user is a manager in any department
                    $isManager = $user->departments->contains(function ($dept) use ($user) {
                        return $dept->pivot->role === 'manager';
                    });
                    $icon = $isManager ? '👔' : '🔧';
                }

                // Use username, name, or email for login (whichever is available)
                $loginIdentifier = $user->username ?? $user->name ?? $user->email;

                return [
                    'icon' => $icon,
                    'name' => $user->name,
                    'username' => $loginIdentifier,
                    'email' => $user->email,
                    'role' => ucfirst($user->role),
                ];
            });

        return response()->json([
            'users' => $users
        ]);
    }
}
