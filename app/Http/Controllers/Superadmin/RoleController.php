<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role; 
use App\Models\User;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('user')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    public function store(Request $request)
    {
        $existsRole = Role::where('role_name', $request->role_name)->exists();

        if ($existsRole) {
            return response()->json([
                'success' => false,
                'message' => 'Role name already in use!'
            ], 400);
        }
        
        $request->validate([
            'role_name' => 'required|string|max:255|unique:roles,role_name'
        ]);

        $role = Role::create([
            'role_name'    => $request->role_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => [
                'id' => $role->id,
                'role_name' => $role->role_name
            ]
        ]);
    }

    public function update(Request $request, $roleId)
    {
        $existsRole = Role::where('role_name', $request->role_name)
        ->where('id', '!=', $roleId)
        ->exists();

        if ($existsRole) {
            return response()->json([
                'success' => false,
                'message' => 'Role name already in use!'
            ], 400);
        }

        $request->validate([
            'role_name' => 'required|string|max:255|unique:roles,role_name,' . $roleId
        ]);

        $role = Role::findOrFail($roleId);

        $role->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role
        ]);
    }

    public function destroy($roleId)
    {
        $role = Role::findOrFail($roleId);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        if (
            User::where('role_id', $role->id)->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Role is used in other data!'
            ], 400);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }
}
