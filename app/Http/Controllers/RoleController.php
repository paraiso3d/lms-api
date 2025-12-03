<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * List all active roles.
     */
    public function listActiveRoles()
    {
        $roles = Role::where('is_archived', 0)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Store a new role.
     */
    public function createRole(Request $request)
    {
        $request->validate([
            'role_name' => 'required|string|max:50|unique:roles,role_name',
            'description' => 'nullable|string|max:255',
        ]);

        $role = Role::create([
            'role_name' => $request->role_name,
            'description' => $request->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => $role,
        ], 201);
    }

    /**
     * Show a single role by ID.
     */
    public function getRole($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $role,
        ]);
    }

    /**
     * Update an existing role.
     */
    public function updateRole(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        $request->validate([
            'role_name' => 'required|string|max:50|unique:roles,role_name,' . $id,
            'description' => 'nullable|string|max:255',
        ]);

        $role->update([
            'role_name' => $request->role_name,
            'description' => $request->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => $role,
        ]);
    }

    /**
     * Archive (soft delete) a role.
     */
    public function archiveRole($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        $role->update(['is_archived' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Role archived successfully.',
        ]);
    }

    /**
     * Restore an archived role.
     */
    public function restoreRole($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        $role->update(['is_archived' => 0]);

        return response()->json([
            'success' => true,
            'message' => 'Role restored successfully.',
            'data' => $role,
        ]);
    }
}
