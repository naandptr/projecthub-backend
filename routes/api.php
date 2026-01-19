<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TrackOrderController;
use App\Http\Controllers\Superadmin\RoleController;
use App\Http\Controllers\Superadmin\UserController as SuperadminUserController;
use App\Http\Controllers\Superadmin\ProgressTrackController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\DesignController as AdminDesignController;
use App\Http\Controllers\Admin\SpkController;
use App\Http\Controllers\Admin\ProductionController as AdminProductionController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\DesignPic\DesignController as DesignPicDesignController;
use App\Http\Controllers\PicProduction\ProductionController as PicProductionProductionController;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/track-order', [TrackOrderController::class, 'index']);

Route::middleware('auth:sanctum')->get('/whoami', fn(Request $r) => $r->user());

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::put('/change-password', [AuthController::class, 'changePassword']);

    Route::get('/dashboard', function () {
        return response()->json(["message" => "Dashboard Access"]);
    })->middleware('role:superadmin,admin,designer_pic,production_pic');

    // ==== SUPERADMIN ROUTES ====
    Route::middleware(['role:superadmin'])->prefix('superadmin')->group(function () {
        // ROLE
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{roleId}', [RoleController::class, 'update']);
        Route::delete('/roles/{roleId}', [RoleController::class, 'destroy']);

        // USER
        Route::get('/users', [SuperadminUserController::class, 'index']);
        Route::get('/users/{userId}', [SuperadminUserController::class, 'show']);
        Route::post('/users', [SuperadminUserController::class, 'store']);
        Route::put('/users/{userId}', [SuperadminUserController::class, 'update']);
        Route::put('/users/{userId}/reset-password', [SuperadminUserController::class, 'resetPassword']);
        Route::delete('/users/{userId}', [SuperadminUserController::class, 'destroy']);

        // PROGRESS TRACK
        Route::get('/progress', [ProgressTrackController::class, 'index']);
    });

    // ==== ADMIN ROUTES ====
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // USER
        Route::get('/users/by-role/{role}', [AdminUserController::class, 'getUsersByRole']);

        // ORDER
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{orderId}', [OrderController::class, 'show']);
        Route::put('/orders/{orderId}', [OrderController::class, 'update']);
        Route::delete('/orders/{orderId}', [OrderController::class, 'destroy']);
        Route::get('/completed', [OrderController::class, 'completed']);
        
        // DESIGN
        Route::get('/designs', [AdminDesignController::class, 'index']);
        Route::get('/designs/{designId}', [AdminDesignController::class, 'show']);
        Route::put('/design-items/{itemId}', [AdminDesignController::class, 'updateItemStatus']);
        Route::post('/designs/{designId}/confirm', [AdminDesignController::class, 'confirmDesign']);
        Route::post('/spk', [SpkController::class, 'store']);

        // PRODUCTION
        Route::get('/productions', [AdminProductionController::class, 'index']);
        Route::get('/productions/{productionId}', [AdminProductionController::class, 'show']);
        Route::post('/productions/{productionId}/confirm', [AdminProductionController::class, 'confirmProduction']);

        // PAYMENT 
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{orderId}', [PaymentController::class, 'show']);
        Route::post('/payments/{orderId}', [PaymentController::class, 'store']);
        Route::put('/payments/{paymentId}', [PaymentController::class, 'update']);
        Route::delete('/payments/{paymentId}', [PaymentController::class, 'destroy']);

        // SHIPMENT 
        Route::get('/shipments/{orderId}', [ShipmentController::class, 'show']);
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
        // GET all tasks
        Route::get('/tasks', [PicProductionProductionController::class, 'index']);
        
        // GET filter tasks (MUST BE BEFORE {id} ROUTE)
        Route::get('/tasks/filter', [PicProductionProductionController::class, 'indexWithFilter']);
        
        // GET single task by ID
        Route::get('/tasks/{id}', [PicProductionProductionController::class, 'show']);
        
        // Start production
        Route::post('/{id}/start', [PicProductionProductionController::class, 'startProduction']);
        
        // COMPLETE production
        Route::post('/{id}/complete', [PicProductionProductionController::class, 'completeProduction']);
    
    // GET all tasks
    Route::get('/tasks', [PicProductionProductionController::class, 'index']);
    
    // GET filter tasks (MUST BE BEFORE {id} ROUTE)
    Route::get('/tasks/filter', [PicProductionProductionController::class, 'indexWithFilter']);
    
    // GET single task by ID
    Route::get('/tasks/{id}', [PicProductionProductionController::class, 'show']);
    
    // Start production
    Route::post('/{id}/start', [PicProductionProductionController::class, 'startProduction']);
    
      // COMPLETE production
    Route::post('/{id}/complete', [PicProductionProductionController::class, 'completeProduction']);
  

    // Production details
    Route::get('/{id}/details', [PicProductionProductionController::class, 'getDetail']);
    Route::post('/{id}/details', [PicProductionProductionController::class, 'storeDetail']);
    Route::put('/details/{detailId}', [PicProductionProductionController::class, 'updateDetail']);
    Route::delete('/details/{detailId}', [PicProductionProductionController::class, 'deleteDetail']);

        
     
     // Production Results (Upload file)
    Route::post('/details/{detailId}/result', [PicProductionProductionController::class, 'storeResult']);
    Route::put('/results/{resultId}', [PicProductionProductionController::class, 'updateResult']);
    Route::delete('/results/{resultId}', [PicProductionProductionController::class, 'deleteResult']);
    Route::get('/results/{resultId}', [PicProductionProductionController::class, 'getResult']);

   });
});
