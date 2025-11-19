<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\DesignController as AdminDesignController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/whoami', fn(Request $r) => $r->user());

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    // DASHBOARD
    Route::get('/dashboard', function () {
        return response()->json([
            "message" => "Dashboard Access"
        ]);
    })->middleware('role:superadmin,admin,designer_pic,production_pic');

    // ORDER (ADMIN)
    Route::middleware('role:admin')->group(function () {

        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::put('/orders/{id}', [OrderController::class, 'update']);
        Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

        Route::get('/designs', [AdminDesignController::class, 'index']);
        Route::get('/designs/{id}', [AdminDesignController::class, 'show']);
        Route::put('/design-items/{id}', [AdminDesignController::class, 'updateItemStatus']);
        Route::post('/designs/{id}/confirm', [AdminDesignController::class, 'confirmDesign']);
    });
});
