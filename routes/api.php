<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ShopsController;
use App\Http\Controllers\ProductController;

Route::middleware('auth:api')->group(function () {
    
    Route::get('get-user', function (Request $request) {
        return $request->user();
    });

    Route::post('/store-tokens', [UserController::class, 'storeTokens']);

    Route::get('/uzum/shops', [ShopsController::class, 'getUzumShops']);
    Route::get('/yandex/shops', [ShopsController::class, 'getYandexShops']);
    
    Route::get('/uzum/products/{shopId}', [ProductController::class, 'getUzumProducts']);
    Route::get('/yandex/products/{businessId}', [ProductController::class, 'getYandexProducts']);
    Route::post('/mapProducts', [ProductController::class, 'storeMapProducts']);
    Route::get('/mapProducts', [ProductController::class, 'getMappedProducts']);
    Route::get('/products/unified/{uzumShopId}/{yandexBusinessId}', [ProductController::class, 'getUnifiedProducts']);

    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);