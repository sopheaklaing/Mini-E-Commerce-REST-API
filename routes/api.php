<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Cart\CartController;
use App\Http\Controllers\Api\Category\CategoryController;
use App\Http\Controllers\Api\Order\OrderController;
use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Product\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth:api')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // Products
    Route::apiResource(
        'products',
        ProductController::class
    );

    // Categories
    Route::apiResource(
        'categories',
        CategoryController::class
    );

    // Cart
    Route::get(
        'cart',
        [CartController::class, 'index']
    );

    Route::post(
        'cart',
        [CartController::class, 'store']
    );

    Route::put(
        'cart/items/{cartItem}',
        [CartController::class, 'update']
    );

    Route::delete(
        'cart/items/{cartItem}',
        [CartController::class, 'destroy']
    );

    Route::delete(
        'cart',
        [CartController::class, 'clear']
    );

    // Orders
    Route::apiResource(
        'orders',
        OrderController::class
    )->only([
        'index',
        'store',
        'show',
    ]);

    Route::post(
        'orders/{id}/cancel',
        [OrderController::class, 'cancel']
    );

    // Payment
    Route::post(
        'orders/{order}/payment',
        [PaymentController::class, 'create']
    );

    Route::post(
        'payments/{payment}/generate-qr',
        [PaymentController::class, 'generateQr']
    );

    Route::get(
        'payments/{payment}/status',
        [PaymentController::class, 'status']
    );
});
