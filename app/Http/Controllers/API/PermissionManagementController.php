<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class PermissionManagementController extends Controller
{
    /**
     * Get all roles with their permissions
     */
    public function getRoles()
    {
        $roles = Role::with('permissions')->get();

        // Manually add users_count to each role
        $roles->each(function ($role) {
            $role->users_count = User::role($role->name)->count();
        });

        return response()->json([
            'roles' => $roles
        ]);
    }

    /**
     * Get all permissions
     */
    public function getPermissions()
    {
        $permissions = Permission::all();

        return response()->json([
            'permissions' => $permissions
        ]);
    }

    /**
     * Create a new role
     */
    public function createRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'sanctum'
        ]);

        if ($request->has('permissions')) {
            $role->givePermissionTo($request->permissions);
        }

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role->load('permissions')
        ], 201);
    }

    /**
     * Update a role
     */
    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name,' . $id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $role->update(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role->load('permissions')
        ]);
    }

    /**
     * Delete a role
     */
    public function deleteRole($id)
    {
        $role = Role::findOrFail($id);

        // Prevent deletion of system roles
        if (in_array($role->name, ['Super Admin', 'Admin'])) {
            return response()->json([
                'message' => 'Cannot delete system role'
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Assign role to user
     */
    public function assignRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_name' => 'required|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        $user->syncRoles([$request->role_name]);

        return response()->json([
            'message' => 'Role assigned successfully',
            'user' => $user->load('roles')
        ]);
    }

    /**
     * Remove role from user
     */
    public function removeRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_name' => 'required|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        $user->removeRole($request->role_name);

        return response()->json([
            'message' => 'Role removed successfully',
            'user' => $user->load('roles')
        ]);
    }

    /**
     * Get user's roles and permissions
     */
    public function getUserPermissions($userId)
    {
        $user = User::with(['roles.permissions'])->findOrFail($userId);

        return response()->json([
            'user' => $user,
            'roles' => $user->roles,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'direct_permissions' => $user->getDirectPermissions()->pluck('name')
        ]);
    }

    /**
     * Give direct permission to user
     */
    public function givePermissionToUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'permission_name' => 'required|exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        $user->givePermissionTo($request->permission_name);

        return response()->json([
            'message' => 'Permission granted successfully',
            'user' => $user->load('permissions')
        ]);
    }

    /**
     * Revoke direct permission from user
     */
    public function revokePermissionFromUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'permission_name' => 'required|exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        $user->revokePermissionTo($request->permission_name);

        return response()->json([
            'message' => 'Permission revoked successfully',
            'user' => $user->load('permissions')
        ]);
    }

    /**
     * Check if user has specific permission
     */
    public function checkPermission(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'permission' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($userId);
        $hasPermission = $user->hasPermissionTo($request->permission);

        return response()->json([
            'has_permission' => $hasPermission
        ]);
    }

    /**
     * Get role details with permissions
     */
    public function getRoleDetails($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'role' => $role,
            'users_count' => $role->users()->count()
        ]);
    }
}
