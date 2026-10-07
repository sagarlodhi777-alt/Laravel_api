<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\DeliverySlotController;
use App\Http\Middleware\IsAdmin;

Route::prefix('v1')->group(function () {
    // Public Auth Routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/social-login', [AuthController::class, 'socialLogin']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Public Shop Browsing Routes
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/categories/{category}/subcategories', [SubcategoryController::class, 'getByCategory']);

    Route::get('/subcategories', [SubcategoryController::class, 'index']);
    Route::get('/subcategories/{subcategory}', [SubcategoryController::class, 'show']);

    Route::get('/subcategories', [SubCategoryController::class, 'index']);
    Route::get('/subcategories/{subCategory}', [SubCategoryController::class, 'show']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    // Cart Endpoints (Works with Bearer token for Auth or X-Session-ID for Guest)
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'getCart']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::put('/items/{itemId}', [CartController::class, 'updateItem']);
        Route::delete('/items/{itemId}', [CartController::class, 'removeItem']);
        Route::delete('/clear', [CartController::class, 'clearCart']);
        Route::post('/preview', [CartController::class, 'previewCheckout']);
    });

    // Delivery Slots Available for Checkout
    Route::get('/delivery-slots', [DeliverySlotController::class, 'index']);

    // Public Tracking Route
    Route::get('/tracking/{tracking_number}', [DeliveryController::class, 'trackByNumber']);

    // Authenticated Routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth Routes (Protected)
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        // Admin Routes
        Route::middleware(IsAdmin::class)->prefix('admin')->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboard']);
            Route::get('/users', [AdminController::class, 'allUsers']);
            
            // Admin Category Management
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{category}', [CategoryController::class, 'update']);
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

            // Admin Subcategory Management
            Route::post('/subcategories', [SubcategoryController::class, 'store']);
            Route::put('/subcategories/{subcategory}', [SubcategoryController::class, 'update']);
            Route::delete('/subcategories/{subcategory}', [SubcategoryController::class, 'destroy']);

            // Admin Product Management
            Route::post('/subcategories', [SubCategoryController::class, 'store']);
            Route::put('/subcategories/{subCategory}', [SubCategoryController::class, 'update']);
            Route::delete('/subcategories/{subCategory}', [SubCategoryController::class, 'destroy']);

            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{product}', [ProductController::class, 'update']);
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);

            // Admin Order Management
            Route::get('/orders', [OrderController::class, 'adminIndex']);
            Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

            // Admin Dispatch & Delivery Management
            Route::get('/deliveries', [DeliveryController::class, 'adminIndex']);
            Route::post('/deliveries/{delivery}/assign', [DeliveryController::class, 'assignDriver']);
            Route::patch('/deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus']);
            Route::patch('/deliveries/{delivery}/location', [DeliveryController::class, 'updateLocation']);
        });

        // User Customer Routes
        Route::prefix('user')->group(function () {
            Route::get('/profile', [UserController::class, 'profile']);
            
            // User Orders & Checkout
            Route::get('/orders', [OrderController::class, 'index']);
            Route::post('/orders', [OrderController::class, 'store']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);
            Route::get('/orders/{order}/track-delivery', [OrderController::class, 'trackDelivery']);
        });
    });
});
