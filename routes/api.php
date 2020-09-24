<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Hook;
/*
|--------------------------------------------------------------------------
| API Routes2
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post("/hook/notifications",[Hook::class,"notifications"]);

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
