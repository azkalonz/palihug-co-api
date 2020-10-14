<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Hook;
use App\Http\Controllers\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\WC\Api;

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
Route::get("/test", function(Request $request){
    return Api::wp('wp/v2')->get("users/me");
});
Route::post("/register", [AuthController::class, "register"]);
Route::post("/login", [AuthController::class, "login"]);
Route::post("/verify-otp", [AuthController::class, "verifyOTP"]);
Route::post("/resend-otp", [AuthController::class, "resendOTP"]);
Route::post("/hook/notifications", [Hook::class, "notifications"]);
Route::post("/hook/otp", [Hook::class, "otp"]);
Route::get("/chat", [ChatController::class, "getConvo"]);
Route::post("/chat", [ChatController::class, "sendMessage"]);