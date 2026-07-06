<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\FazpassCallbackController;
use App\Http\Controllers\Api\EditorImageController;
use App\Http\Controllers\Api\FlashSaleController;
use App\Http\Controllers\Api\HeroSectionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\RajaOngkirAdminController;
use App\Http\Controllers\Api\RajaOngkirController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/forgot-password/request-otp', [AuthController::class, 'requestPasswordResetOtp']);
Route::post('/forgot-password/reset', [AuthController::class, 'resetPasswordWithOtp']);
Route::post('/fazpass/callback', [FazpassCallbackController::class, 'store']);
Route::post('/admin/login', [AuthController::class, 'adminLogin']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

Route::get('/payment-methods', [PaymentMethodController::class, 'index']);

Route::get('/announcements/active', [AnnouncementController::class, 'active']);
Route::get('/hero-sections/active', [HeroSectionController::class, 'active']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/flash-sales', [FlashSaleController::class, 'active']);

Route::get('/shipping/provinces', [RajaOngkirController::class, 'provinces']);
Route::get('/shipping/cities', [RajaOngkirController::class, 'cities']);
Route::get('/shipping/districts', [RajaOngkirController::class, 'districts']);
Route::get('/shipping/sub-districts', [RajaOngkirController::class, 'subDistricts']);
Route::get('/shipping/config', [RajaOngkirController::class, 'config']);
Route::post('/shipping/costs', [RajaOngkirController::class, 'costs']);

Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{slug}/related', [ArticleController::class, 'related']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);

Route::post('/midtrans/notification', [PaymentController::class, 'notification']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/password/request-change-otp', [AuthController::class, 'requestPasswordChangeOtp']);
    Route::post('/password/change', [AuthController::class, 'changePassword']);

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);

    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/orders', [OrderController::class, 'store']);

    Route::get('/customer/orders', [OrderController::class, 'customerOrders']);
    Route::get('/customer/orders/{id}', [OrderController::class, 'showCustomerOrder']);

    Route::post('/payments/create', [PaymentController::class, 'createPayment']);
    Route::get('/payments/check-status/{id}', [PaymentController::class, 'checkStatus']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);
    Route::post('/payments/retry/{order}', [PaymentController::class, 'retryPayment']);
});

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:super_admin,admin'])
    ->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);

        Route::get('/hero-sections', [HeroSectionController::class, 'index']);
        Route::post('/hero-sections', [HeroSectionController::class, 'store']);
        Route::post('/hero-sections/{id}', [HeroSectionController::class, 'update']);
        Route::delete('/hero-sections/{id}', [HeroSectionController::class, 'destroy']);
        Route::post('/editor/upload-image', [EditorImageController::class, 'store']);

        Route::get('/products', [ProductController::class, 'adminIndex']);
        Route::get('/products/{id}', [ProductController::class, 'showById']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::post('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        Route::middleware('role:super_admin')->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'index']);

            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::put('/users/{user}', [AdminUserController::class, 'update']);
            Route::put('/users/{user}/password', [AdminUserController::class, 'updatePassword']);
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);

            Route::get('/customers', [CustomerController::class, 'index']);
            Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);

            Route::get('/announcements', [AnnouncementController::class, 'index']);
            Route::post('/announcements', [AnnouncementController::class, 'store']);
            Route::put('/announcements/{id}', [AnnouncementController::class, 'update']);
            Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);

            Route::get('/categories', [CategoryController::class, 'adminIndex']);
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::get('/categories/{id}', [CategoryController::class, 'show']);
            Route::post('/categories/{id}', [CategoryController::class, 'update']);
            Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

            Route::get('/flash-sales', [FlashSaleController::class, 'index']);
            Route::post('/flash-sales', [FlashSaleController::class, 'store']);
            Route::post('/flash-sales/{id}', [FlashSaleController::class, 'update']);
            Route::delete('/flash-sales/{id}', [FlashSaleController::class, 'destroy']);

            Route::get('/articles', [ArticleController::class, 'adminIndex']);
            Route::get('/articles/{id}', [ArticleController::class, 'adminShow']);
            Route::post('/articles', [ArticleController::class, 'store']);
            Route::post('/articles/{id}', [ArticleController::class, 'update']);
            Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

            Route::get('/payment-methods', [PaymentMethodController::class, 'adminIndex']);
            Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
            Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update']);
            Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy']);

            Route::get('/rajaongkir', [RajaOngkirAdminController::class, 'show']);
            Route::put('/rajaongkir/setting', [RajaOngkirAdminController::class, 'updateSetting']);
            Route::get('/rajaongkir/destinations', [RajaOngkirAdminController::class, 'destinations']);
            Route::post('/rajaongkir/couriers', [RajaOngkirAdminController::class, 'storeCourier']);
            Route::put('/rajaongkir/couriers/{courier}', [RajaOngkirAdminController::class, 'updateCourier']);
            Route::delete('/rajaongkir/couriers/{courier}', [RajaOngkirAdminController::class, 'destroyCourier']);
        });
    });
