<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login success',
            'token'   => $token,
            'user'    => [
                'id'       => $user->id,
                'full_name'     => $user->full_name,
                'username' => $user->username,
                'role'     => $user->role->role_name,
            ]
        ]);
    }

    // public function changePassword(Request $request)
    // {
    //     $request->validate([
    //         'current_password' => 'required|string',
    //         'new_password' => [
    //             'required',
    //             'max:150',
    //             'confirmed',
    //             Password::min(8)
    //             ->letters()
    //             ->mixedCase()
    //             ->numbers()
    //             ->symbols()
    //             ->uncompromised() 
    //         ]
    //     ]);

    //     $user = $request->user();

    //     if (!Hash::check($request->current_password, $user->password)) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Current password is wrong.'
    //         ], 401);
    //     }

    //     $user->password = bcrypt($request->new_password);

    //     $user->update([
    //         'is_default_password' => false,
    //         'user_status' => User::STATUS_ACTIVE
    //     ]);

    //     if ($user->save()) {
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Password changed successfully.'
    //         ], 200);
    //     } else {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Some error occured, please try again.'
    //         ], 500);
    //     }
    // }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is wrong.'
            ], 401);
        }

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

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            "message" => "Logged out"
        ]);
    }
}
