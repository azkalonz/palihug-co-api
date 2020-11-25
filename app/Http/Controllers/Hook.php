<?php

namespace App\Http\Controllers;

use App\Socket\Socket;
use Illuminate\Http\Request;
use JWTAuth;
use App\Http\Controllers\AuthController;
use App\Models\Cart;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Hook extends Controller
{
    public function notifications(Request $request, $params)
    {
        return $this->authenticate()->http($request, function($request, $cred, $params) {
            $notification = Notification::create($params);
            Socket::broadcast("notifications:chat", $params);
            return $params;
        }, $params);
    }

    public function getNotifications(Request $request)
    {
        return $this->authenticate()->http($request, function ($request, $cred) {
            if(!isset($request->count)){
                $notification = DB::select("select concat(provider.user_fname,' ',provider.user_lname) as provider_name, notifications.* from notifications inner join users as provider on provider.user_id = notifications.provider_user_id where notifications.created_at in (select max(created_at) from notifications where consumer_user_id = ? and provider_user_id = provider.user_id)",[$cred->user_id]);
                return $notification;
            }
            else 
                return [
                    "notifications"=>Notification::where("consumer_user_id", "=", $cred->user_id)->get()->count(),
                    "cart"=>Cart::where("user_id","=",$cred->user_id)->get()->count()
                ];
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
