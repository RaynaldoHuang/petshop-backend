<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Api\HeroSectionController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FlashSaleController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\EditorImageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\PaymentMethodController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);

// Route::get('/admin/products/{id}', [ProductController::class, 'edit']);
Route::get('/products/by-id/{id}', [ProductController::class, 'showById']);
Route::post('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);

Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);
Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);

Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
Route::post(
    '/payments/create',
    [PaymentController::class, 'createPayment']
);
Route::get(
    '/payments/check-status/{id}',
    [PaymentController::class, 'checkStatus']
);
Route::get(
    '/payments/{id}',
    [PaymentController::class, 'show']
);

Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/announcements/active', [AnnouncementController::class, 'active']);
Route::post('/announcements', [AnnouncementController::class, 'store']);
Route::put('/announcements/{id}', [AnnouncementController::class, 'update']);
Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);

Route::get('/hero-sections', [HeroSectionController::class, 'index']);
Route::get('/hero-sections/active', [HeroSectionController::class, 'active']);
Route::post('/hero-sections', [HeroSectionController::class, 'store']);
Route::post('/hero-sections/{id}', [HeroSectionController::class, 'update']);
Route::delete('/hero-sections/{id}', [HeroSectionController::class, 'destroy']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::post('/categories/{id}', [CategoryController::class, 'update']);
Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

Route::get('/flash-sales', [FlashSaleController::class, 'active']);
Route::get('/flash-sales/all', [FlashSaleController::class, 'index']);
Route::post('/flash-sales', [FlashSaleController::class, 'store']);
Route::post('/flash-sales/{id}', [FlashSaleController::class, 'update']);
Route::delete('/flash-sales/{id}', [FlashSaleController::class, 'destroy']);

Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);
Route::get('/articles/{slug}/related', [ArticleController::class, 'related']);

Route::get('/admin/articles', [ArticleController::class, 'adminIndex']);
Route::get('/admin/articles/{id}', [ArticleController::class, 'adminShow']);
Route::post('/admin/articles', [ArticleController::class, 'store']);
Route::post('/admin/articles/{id}', [ArticleController::class, 'update']);
Route::delete('/admin/articles/{id}', [ArticleController::class, 'destroy']);

Route::post('/editor/upload-image', [EditorImageController::class, 'store']);

Route::get('/admin/customers', [CustomerController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);

    Route::post('/checkout', [CheckoutController::class, 'store']);

    Route::get(
        '/customer/orders',
        [OrderController::class, 'customerOrders']
    );

    Route::get(
        '/customer/orders/{id}',
        [OrderController::class, 'showCustomerOrder']
    );

    Route::post(
        '/payments/retry/{order}',
        [PaymentController::class, 'retryPayment']
    );
});
