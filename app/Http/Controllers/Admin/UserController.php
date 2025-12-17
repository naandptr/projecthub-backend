<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function getUsersByRole($role)
    {
        $users = User::whereHas('role', function ($q) use ($role) {
            $q->where('role_name', $role);
        })
        ->where('user_status', 'active')
        ->select('id', 'full_name')
        ->get();

        $role = Role::where('role_name', $role)->first();

        if ($role->role_name == 'designer_pic') {
            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        }

        if ($role->role_name == 'production_pic') {
            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        }

        return response()->json([
            'success' => false, 
            'message' => 'Siapa lu mau liat-liat :P'], 403);
    }
}
