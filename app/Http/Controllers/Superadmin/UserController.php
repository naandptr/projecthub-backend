<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Order;
use App\Models\Design;
use App\Models\Production;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /* GET ALL USERS */
    public function index()
    {        
        $users = User::with('role')
            ->select('id', 'role_id', 'full_name', 'username', 'email', 'user_status')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'List of users',
            'data' => $users,
        ]);
    }

    /* GET USER BY ID */
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

    /* CREATE USER */
    public function store(Request $request)
    {        
        $request->validate([
            'role_id'   => 'required|exists:roles,id',
            'full_name' => 'required|string|max:100',
            'username'  => 'required|string|max:50|unique:users,username',
            'email'     => 'nullable|email|unique:users,email'
        ]);

        $defaultPassword = User::generateDefaultPassword(); // Generate default password for new user (123456)

        $user = User::create([
            'role_id'    => $request->role_id,
            'full_name'  => $request->full_name,
            'username'   => $request->username,
            'email'      => $request->email,
            'password'   => Hash::make($defaultPassword),
            'is_default_password' => true, // Flag to require password change on first login
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

    /* UPDATE USER */
    public function update(Request $request, $userId)
    {        
        $request->validate([
            'full_name' => 'string|max:100',
            'username'  => 'string|max:50|unique:users,username,' . $userId,
            'email'     => 'email|unique:users,email,' . $userId,
            'user_status' => 'in:Pending,Active,Non-Active'
        ]);

        $user = User::findOrFail($userId);

        $user->update($request->only([
            'full_name',
            'username',
            'email',
            'user_status'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    /* RESET PASSWORD USER */
    public function resetPassword($userId)
    {
        $user = User::findOrFail($userId);

        $defaultPassword = User::generateDefaultPassword();

        $user->update([
            'password' => Hash::make($defaultPassword),
            'is_default_password' => true
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset to default',
            'default_password' => $defaultPassword
        ]);
    }

    /* DELETE USER */
    public function destroy($userId)
    {
        $user = User::findOrFail($userId);
        
        // Prevent deletion of active users (only pending/inactive users can be deleted)
        if ($user->user_status == 'active') {
            return response()->json([
                'success' => false, 
                'message' => 'Cannot delete active user!'], 400);
        }

        // Prevent deletion if user has associated records in other tables
        if (
            Order::where('created_by', $user->id)->exists() ||
            Design::where('assigned_to', $user->id)->exists() ||
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
            'message' => 'User deleted successfully'
        ]);
    }
}
