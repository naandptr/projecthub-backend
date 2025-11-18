<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\OrderController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/whoami', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('role:superadmin')->group(function () {
        Route::get('/superadmin/dashboard', function () {
            return response()->json([
                "message" => "Welcome Superadmin"
            ]);
        });
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', function () {
            return response()->json([
                "message" => "Welcome Admin"
            ]);
        });

        Route::get('/admin/orders', [OrderController::class, 'index']);
        Route::post('/admin/orders', [OrderController::class, 'store']);
        Route::get('/admin/orders/{id}', [OrderController::class, 'show']);
        Route::put('/admin/orders/{id}', [OrderController::class, 'update']);
        Route::delete('/admin/orders/{id}', [OrderController::class, 'destroy']);
    });

    Route::middleware('role:designer_pic')->group(function () {
        Route::get('/pic-design/dashboard', function () {
            return response()->json([
                "message" => "Welcome PIC Design"
            ]);
        });
    });

    Route::middleware('role:production_pic')->group(function () {
        Route::get('/pic-production/dashboard', function () {
            return response()->json([
                "message" => "Welcome PIC Production"
            ]);
        });
    });
});