<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Superadmin\UserController as SuperadminUserController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\DesignController as AdminDesignController;
use App\Http\Controllers\Admin\SpkController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\DesignPic\DesignController as DesignPicDesignController;
use App\Http\Controllers\PicProduction\ProductionController as PicProductionProductionController;
use App\Http\Controllers\PicProduction\VendorDetailController as PicProductionVendorDetailController;
use App\Http\Controllers\PicProduction\InhouseDetailController;



Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/whoami', fn(Request $r) => $r->user());

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', function () {
        return response()->json(["message" => "Dashboard Access"]);
    })->middleware('role:superadmin,admin,designer_pic,production_pic');

    // ==== SUPERADMIN ROUTES ====
    Route::middleware(['role:superadmin'])->prefix('superadmin')->group(function () {
        // USER
        Route::get('/users', [SuperadminUserController::class, 'index']);
        Route::get('/users/{userId}', [SuperadminUserController::class, 'show']);
        Route::post('/users', [SuperadminUserController::class, 'store']);
        Route::put('/users/{userId}', [SuperadminUserController::class, 'update']);
        Route::put('/users/{userId}/reset-password', [SuperadminUserController::class, 'resetPassword']);
        Route::delete('/users/{userId}', [SuperadminUserController::class, 'destroy']);
    });

    // ==== ADMIN ROUTES ====
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // USER
        Route::get('/users/by-role/{role}', [AdminUserController::class, 'getUsersByRole']);

        // ORDER
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{orderId}', [OrderController::class, 'show']);
        Route::patch('/orders/{orderId}', [OrderController::class, 'update']);
        Route::delete('/orders/{orderId}', [OrderController::class, 'destroy']);
        
        // DESIGN
        Route::get('/designs', [AdminDesignController::class, 'index']);
        Route::get('/designs/{designId}', [AdminDesignController::class, 'show']);
        Route::put('/design-items/{itemId}', [AdminDesignController::class, 'updateItemStatus']);
        Route::post('/designs/{designId}/confirm', [AdminDesignController::class, 'confirmDesign']);
        Route::post('/spk', [SpkController::class, 'store']);

        // PAYMENT 
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments/{orderId}', [PaymentController::class, 'store']);
        Route::put('/payments/{paymentId}', [PaymentController::class, 'update']);
        Route::delete('/payments/{paymentId}', [PaymentController::class, 'destroy']);

        // SHIPMENT 
        Route::post('/shipments/{orderId}', [ShipmentController::class, 'store']);
        Route::put('/shipments/{shipmentId}', [ShipmentController::class, 'update']);
        Route::delete('/shipments/{shipmentId}', [ShipmentController::class, 'destroy']);
    });

    // ==== DESIGN PIC ROUTES ====
    Route::middleware('role:designer_pic')->prefix('designer')->group(function () {
        // TASKS
        Route::get('/tasks', [DesignPicDesignController::class, 'index']);
        Route::get('/tasks/{orderId}', [DesignPicDesignController::class, 'show']);
        Route::post('/tasks/{orderId}/start', [DesignPicDesignController::class, 'start']);

        // DESIGN ITEMS
        Route::post('/design-items/{itemId}', [DesignPicDesignController::class, 'storeItem']);
        Route::put('/design-items/{itemId}', [DesignPicDesignController::class, 'updateItem']);
        Route::delete('/design-items/{itemId}', [DesignPicDesignController::class, 'destroyItem']);
    });

// ==== PRODUCTION PIC ROUTES ====
Route::middleware('role:production_pic')->prefix('production')->group(function () {
    Route::get('/tasks', [PicProductionProductionController::class, 'index']);
    Route::get('/tasks/{id}', [PicProductionProductionController::class, 'show']);
    Route::post('/{id}/start', [PicProductionProductionController::class, 'startProduction']);
    Route::post('/{id}/details', [PicProductionProductionController::class, 'storeDetail']);
    Route::put('/details/{detailId}', [PicProductionProductionController::class, 'updateDetail']);
    Route::post('/{id}/complete', [PicProductionProductionController::class, 'completeProduction']);
    Route::put('/{id}/confirm-ready', [PicProductionProductionController::class, 'confirmReady']);
 
   // ✅ INHOUSE Vendor ROUTES
    Route::post('/details/{detailId}/vendor', [PicProductionVendorDetailController::class, 'storeVendor']);
    Route::get('/details/{detailId}/vendor', [PicProductionVendorDetailController::class, 'getVendor']);
    Route::put('/details/{detailId}/vendor', [PicProductionVendorDetailController::class, 'updateVendor']);
    Route::delete('/details/{detailId}/vendor', [PicProductionVendorDetailController::class, 'deleteVendor']);

    // ✅ INHOUSE DETAIL ROUTES
    Route::post('/details/{detailId}/inhouse', [InhouseDetailController::class, 'store']);
    Route::get('/details/{detailId}/inhouse', [InhouseDetailController::class, 'show']);
    Route::put('/details/{detailId}/inhouse', [InhouseDetailController::class, 'update']);
    Route::delete('/details/{detailId}/inhouse', [InhouseDetailController::class, 'destroy']);

});



    
});