<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    /* LOGIN USER */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('api_token')->plainTextToken; // Generate API authentication token

        return response()->json([
            'success' => true,
            'message' => 'Login success',
            'token'   => $token,
            'user'    => [
                'id'        => $user->id,
                'full_name' => $user->full_name,
                'username'  => $user->username,
                'role'      => $user->role->role_name,
            ]
        ]);
    }

    /* CHANGE PASSWORD */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'confirmed',            // Requires new_password_confirmation field to match
                Password::min(8)        // Minimum 8 characters
                    ->letters()         // Must contain letters
                    ->mixedCase()       // Must contain both uppercase and lowercase
                    ->numbers()         // Must contain numbers
                    ->symbols()         // Must contain special characters
                    ->uncompromised(),  // Password hasn't been exposed in data breaches
            ],
        ]);

        $user = $request->user();

        // Verify current password is correct before allowing change
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is wrong.'
            ], 401);
        }

        // Update user password and activate account
        $user->update([
            'password' => Hash::make($request->new_password),
            'is_default_password' => false,
            'user_status' => User::STATUS_ACTIVE,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.'
        ], 200);
    }

    /* LOGOUT */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete(); // Revoke all authentication tokens for the current user

        return response()->json([
            'success' => true,
            'message' => 'Logged out.'
        ]);
    }
}
