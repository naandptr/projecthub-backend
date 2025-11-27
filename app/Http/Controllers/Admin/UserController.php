<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
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

        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    }
}
