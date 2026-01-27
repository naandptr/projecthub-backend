<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Role;
use App\Http\Controllers\Controller;

class UserController extends Controller
{
    /* GET USER BY ROLE */
    public function getUsersByRole($role)
    {
        // Validate role exists first
        $roleRecord = Role::where('role_name', $role)->first();

        if (!$roleRecord) {
            return response()->json([
                'success' => false, 
                'message' => 'Role not found.'
            ], 404);
        }

        // Only allow specific roles
        if (!in_array($roleRecord->role_name, ['designer_pic', 'production_pic'])) {
            return response()->json([
                'success' => false, 
                'message' => 'Forbidden access.'
            ], 403);
        }

        $users = User::whereHas('role', function ($q) use ($role) {
            $q->where('role_name', $role);
        })
        ->where('user_status', 'active')
        ->select('id', 'full_name')
        ->latest()
        ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }
}
