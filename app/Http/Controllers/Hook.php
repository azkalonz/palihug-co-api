<?php

namespace App\Http\Controllers;

use App\Socket\Socket;
use Illuminate\Http\Request;
use JWTAuth;
use App\Http\Controllers\AuthController;
use App\Models\User;

class Hook extends Controller
{
    public function notifications(Request $request)
    {

        return $this->authenticate()->http($request, function($request, $cred) {
            $param = $request->all();
            Socket::broadcast("notification", $param);
            return $param;
        });
    }

    public static function otp(Request $request)
    {
        $auth = new AuthController;

        return $auth->http($request, function($request, $cred) {
            $user = User::where('user_email',$request->user_email)->where('user_token',$request->user_token)->first();
            $user = [
                "duration" => 120000,
                "user_email" => $user['user_email'],
                "user_token" => $user['user_token']
            ];
            Socket::broadcast("otp", $user);
            return $user;
        });
    }
}
