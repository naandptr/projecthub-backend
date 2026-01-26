<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role; 
use App\Models\User;

class RoleController extends Controller
{
    /* GET ALL ROLES */
    public function index()
    {        
        $roles = Role::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'List of roles',
            'data' => $roles
        ]);
    }

    /* CREATE ROLE */
    public function store(Request $request)
    {       
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

    /* UPDATE ROLE */
    public function update(Request $request, $roleId)
    {        
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

    /* DELETE ROLE*/
    public function destroy($roleId)
    {
        $role = Role::findOrFail($roleId);

        // Prevent deletion if role is assigned to any users
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
