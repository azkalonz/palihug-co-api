<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Hook;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post("/register", [AuthController::class, "register"]);
Route::post("/login", [AuthController::class, "login"]);
Route::post("/verify-otp", [AuthController::class, "verifyOTP"]);
Route::post("/resend-otp", [AuthController::class, "resendOTP"]);
Route::post("/hook/otp", [Hook::class, "otp"]);
Route::get("/chat", [ChatController::class, "getConvo"]);
Route::post("/chat", [ChatController::class, "sendMessage"]);
Route::get('/services/{service_id?}', [ServiceController::class, "all"]);
Route::post('/first-login', [UserController::class, "firstLogin"]);
Route::post('/add-address', [UserController::class, "addAddress"]);
Route::post('/add-address/default', [UserController::class, "defaultAddress"]);

Route::get("/merchants/{merch_id?}", [MerchantController::class, "all"]);
Route::get("/merchants/{merch_id?}/data", [MerchantController::class, "products"]);
Route::get('/products', [MerchantController::class, "productArchive"]);
Route::post('/products', [MerchantController::class, "productArchive"]);

Route::get("/cart", [CartController::class, "getCart"]);
Route::post("/cart", [CartController::class, "setCart"]);
Route::delete("/cart", [CartController::class, "removeCart"]);

Route::post("/checkout", [OrderController::class, "createOrder"]);

Route::get("/orders/{user_type}", [OrderController::class, "getOrder"]);
Route::get("/order/{order_id}", [OrderController::class, "orderInfo"]);

Route::post("/accept-order", [OrderController::class, "acceptOrder"]);

Route::get("/notifications", [Hook::class, "getNotifications"]);
Route::post("/seen", [Hook::class, "seen"]);
