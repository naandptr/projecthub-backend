<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('role')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function show($userId)
    {
        $user = User::with('role')->find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'role_id'   => 'required|exists:roles,id',
            'full_name' => 'required|string|max:100',
            'username'  => 'required|string|max:50|unique:users,username',
            'email'     => 'nullable|email|unique:users,email'
        ]);

        $defaultPassword = User::generateDefaultPassword();

        $user = User::create([
            'role_id'    => $request->role_id,
            'full_name'  => $request->full_name,
            'username'   => $request->username,
            'email'      => $request->email,
            'password'   => Hash::make($defaultPassword),
            'is_default_password' => 1,
            'user_status' => User::STATUS_PENDING
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'role' => $user->role->role_name,
                'default_password' => $defaultPassword
            ]
        ]);
    }

    public function update(Request $request, $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $request->validate([
            'role_id'   => 'nullable|exists:roles,id',
            'full_name' => 'nullable|string|max:100',
            'username'  => 'nullable|string|max:50|unique:users,username,' . $userId,
            'email'     => 'nullable|email|unique:users,email,' . $userId,
            'user_status' => 'nullable|in:Pending,Active,Non-Active'
        ]);

        $update = [];

        if ($request->has('role_id')) $update['role_id'] = $request->role_id;
        if ($request->has('full_name')) $update['full_name'] = $request->full_name;
        if ($request->has('username'))  $update['username'] = $request->username;
        if ($request->has('email'))     $update['email'] = $request->email;
        if ($request->has('user_status')) $update['user_status'] = $request->user_status;

        $user->update($update);

        return response()->json([
            'success' => true,
            'message' => 'User updated',
            'data' => $user
        ]);
    }

    public function resetPassword($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $defaultPassword = User::generateDefaultPassword();

        $user->update([
            'password' => Hash::make($defaultPassword),
            'is_default_password' => 1
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset to default',
            'default_password' => $defaultPassword
        ]);
    }

    public function destroy($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted'
        ]);
    }
}
