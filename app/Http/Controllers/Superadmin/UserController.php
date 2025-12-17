<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\Order;
use App\Models\Design;
use App\Models\Spk;
use App\Models\Production;
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
        $existsUser = User::where('username', $request->username)->exists();

        if ($existsUser) {
            return response()->json([
                'success' => false,
                'message' => 'Username already in use!'
            ], 400);
        }

        $existsEmail = User::where('email', $request->email)->exists();

        if ($existsEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Email already in use!'
            ], 400);
        }

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
        $existsUser = User::where('username', $request->username)
        ->where('id', '!=', $userId)
        ->exists();

        $existsEmail = User::where('email', $request->email)
        ->where('id', '!=', $userId)
        ->exists();

        if ($existsUser) {
            return response()->json([
                'success' => false,
                'message' => 'Username already in use!'
            ], 400);
        }

        if ($existsEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Email already in use!'
            ], 400);
        }

        $request->validate([
            'role_id'   => '|exists:roles,id',
            'full_name' => 'required|string|max:100',
            'username'  => 'required|string|max:50|unique:users,username,' . $userId,
            'email'     => 'required|email|unique:users,email,' . $userId,
            'user_status' => 'required|in:Pending,Active,Non-Active'
        ]);

        $user = User::findOrFail($userId);

        $user->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    public function resetPassword($userId)
    {
        $user = User::findorFail($userId);

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
        $user = User::findorFail($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->user_status == 'active') {
            return response()->json([
                'success' => false, 
                'message' => 'Cannot delete active user!'], 400);
        }

        if (
            Order::where('created_by', $user->id)->exists() ||
            Design::where('assigned_to', $user->id)->exists() ||
            Spk::where('assigned_to', $user->id)->exists() ||
            Production::where('assigned_to', $user->id)->exists() 
        ) {
            return response()->json([
                'success' => false,
                'message' => 'User is used in other data!'
            ], 400);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted'
        ]);
    }
}
