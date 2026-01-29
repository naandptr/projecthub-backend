<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TrackOrderController;
use App\Http\Controllers\Superadmin\RoleController;
use App\Http\Controllers\Superadmin\UserController as SuperadminUserController;
use App\Http\Controllers\Superadmin\VendorController as SuperadminVendorController;
use App\Http\Controllers\Superadmin\ProgressTrackController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\DesignController as AdminDesignController;
use App\Http\Controllers\Admin\ProductionController as AdminProductionController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\DesignPic\DesignController as DesignPicDesignController;
use App\Http\Controllers\PicProduction\ProductionController as ProductionPicProductionController;
use App\Http\Controllers\PicProduction\VendorController as ProductionPicVendorController;


Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1'); // Max 5 attempts per minute

Route::get('/track-order', [TrackOrderController::class, 'show'])
    ->middleware('throttle:20,1'); // Max 20 requests per minute

Route::middleware('auth:sanctum')->get('/whoami', fn(Request $r) => $r->user());

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('throttle:5,1'); // Max 5 attempts per minute

    Route::put('/change-password', [AuthController::class, 'changePassword'])
        ->middleware('throttle:1,1'); // Max 1 attempts per minute

    /* SUPERADMIN ROUTES */
    Route::middleware(['role:superadmin', 'rateLimit'])->prefix('superadmin')->group(function () {
        // ROLE
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute
        Route::put('/roles/{roleId}', [RoleController::class, 'update']);
        Route::delete('/roles/{roleId}', [RoleController::class, 'destroy']);

        // USER
        Route::get('/users', [SuperadminUserController::class, 'index']);
        Route::get('/users/{userId}', [SuperadminUserController::class, 'show']);
        Route::post('/users', [SuperadminUserController::class, 'store'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute
        Route::put('/users/{userId}', [SuperadminUserController::class, 'update']);
        Route::put('/users/{userId}/reset-password', [SuperadminUserController::class, 'resetPassword']);
        Route::delete('/users/{userId}', [SuperadminUserController::class, 'destroy']);

        // VENDOR
        Route::get('/vendors', [SuperadminVendorController::class, 'index']);
        Route::get('/vendors/{vendorId}', [SuperadminVendorController::class, 'show']);
        Route::post('/vendors', [SuperadminVendorController::class, 'store'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute
        Route::put('/vendors/{vendorId}', [SuperadminVendorController::class, 'update']);
        Route::delete('/vendors/{vendorId}', [SuperadminVendorController::class, 'destroy']);
        
        // PROGRESS TRACK
        Route::get('/progress', [ProgressTrackController::class, 'index']);
    });

    /* ADMIN ROUTES */
    Route::middleware('role:admin', 'rateLimit')->prefix('admin')->group(function () {
        // USER
        Route::get('/users/by-role/{role}', [AdminUserController::class, 'getUsersByRole']);

        // ORDER
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute
        Route::get('/orders/{orderId}', [OrderController::class, 'show']);
        Route::put('/orders/{orderId}', [OrderController::class, 'update']);
        Route::delete('/orders/{orderId}', [OrderController::class, 'destroy']);
        Route::get('/completed', [OrderController::class, 'completed']);
        
        // DESIGN
        Route::get('/designs', [AdminDesignController::class, 'index']);
        Route::get('/designs/{designId}', [AdminDesignController::class, 'show']);
        Route::put('/design-items/{itemId}', [AdminDesignController::class, 'updateItemStatus']);
        Route::post('/designs/{designId}/confirm', [AdminDesignController::class, 'confirmDesign'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute

        // PRODUCTION
        Route::get('/productions', [AdminProductionController::class, 'index']);
        Route::get('/productions/{productionId}', [AdminProductionController::class, 'show']);
        Route::post('/productions/{productionId}/confirm', [AdminProductionController::class, 'confirmProduction'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute

        // PAYMENT 
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{orderId}', [PaymentController::class, 'show']);
        Route::post('/payments/{orderId}', [PaymentController::class, 'store'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute
        Route::put('/payments/{paymentId}', [PaymentController::class, 'update']);
        Route::delete('/payments/{paymentId}', [PaymentController::class, 'destroy']);

        // SHIPMENT 
        Route::get('/shipments/{orderId}', [ShipmentController::class, 'show']);
        Route::post('/shipments/{orderId}', [ShipmentController::class, 'store'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute
        Route::put('/shipments/{shipmentId}', [ShipmentController::class, 'update']);
        Route::delete('/shipments/{shipmentId}', [ShipmentController::class, 'destroy']);
    });

    /* DESIGNER_PIC ROUTES */
    Route::middleware('role:designer_pic', 'rateLimit')->prefix('designer')->group(function () {
        // TASKS
        Route::get('/tasks', [DesignPicDesignController::class, 'index']);
        Route::get('/tasks/{orderId}', [DesignPicDesignController::class, 'show']);
        Route::post('/tasks/{orderId}/start', [DesignPicDesignController::class, 'startDesign'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute

        // DESIGN ITEMS
        Route::post('/design-items/{designId}', [DesignPicDesignController::class, 'storeItem'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute
        Route::put('/design-items/{itemId}', [DesignPicDesignController::class, 'updateItem']);
        Route::delete('/design-items/{itemId}', [DesignPicDesignController::class, 'destroyItem']);
    });

    /* PRODUCTION_PIC ROUTES */
    Route::middleware('role:production_pic', 'rateLimit')->prefix('production')->group(function () {
        // TASKS
        Route::get('/tasks', [ProductionPicProductionController::class, 'index']);
        Route::get('/tasks/{id}', [ProductionPicProductionController::class, 'show']);
        Route::post('/{id}/start', [ProductionPicProductionController::class, 'startProduction'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute

        // PRODUCTION DETAILS
        Route::get('/vendors', [ProductionPicVendorController::class, 'index']);
        Route::get('/{id}/details', [ProductionPicProductionController::class, 'getDetail']);
        Route::post('/{id}/details', [ProductionPicProductionController::class, 'storeDetail'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute
        Route::put('/details/{detailId}', [ProductionPicProductionController::class, 'updateDetail']);
        Route::delete('/details/{detailId}', [ProductionPicProductionController::class, 'deleteDetail']);

        // PRODUCTION RESULTS
        Route::get('/results/{productionId}', [ProductionPicProductionController::class, 'getResult']);
        Route::post('/details/{productionId}/result', [ProductionPicProductionController::class, 'storeResult'])
            ->middleware('throttle:1,1'); // Max 1 attempts per minute
        Route::put('/results/{resultId}', [ProductionPicProductionController::class, 'updateResult']);
        Route::delete('/results/{productionId}', [ProductionPicProductionController::class, 'deleteResult']);
   });
});
