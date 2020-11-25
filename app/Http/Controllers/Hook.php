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
    public function chat_notifications(Request $request, $params)
    {
        return $this->authenticate()->http($request, function($request, $cred, $params) {
            $notification = Notification::create($params);
            $notification = $notification->toArray();
            $u = DB::select("select * from users where user_id = ?",[$params['provider_user_id']]);
            $notification['provider_name'] = $u[0]->user_fname.' '.$u[0]->user_lname;
            Socket::broadcast("notifications:chat", $notification);
            return $notification;
        }, $params);
    }

    public function getNotifications(Request $request)
    {
        return $this->authenticate()->http($request, function ($request, $cred) {
            if(!isset($request->count)){
                $notification = DB::select("select concat(provider.user_fname,' ',provider.user_lname) as provider_name, notifications.* from notifications inner join users as provider on provider.user_id = notifications.provider_user_id where notifications.noti_id in (select max(noti_id) from notifications where consumer_user_id = ? and provider_user_id = provider.user_id)",[$cred->user_id]);
                return $notification;
            }
            else 
                return [
                    "notifications"=>Notification::where("consumer_user_id", "=", $cred->user_id)->where("viewed","=",0)->get()->count(),
                    "cart"=>Cart::where("user_id","=",$cred->user_id)->get(["total_items"])->first()->toArray()['total_items']
                ];
        });
    }

    public function seen(Request $request){
        return $this->authenticate()->http($request, function ($request, $cred) {
            $notifications = Notification::where("order_id","=",$request->order_id)->where("consumer_user_id",'=',$cred->user_id);
            $total = $notifications->where("viewed","=",0)->update(["viewed"=>1]);
            $update = [
                'notification'=>Notification::where("viewed","=",1)->where("order_id","=",$request->order_id)->where("consumer_user_id",'=',$cred->user_id)->get()->last(),
                'total'=>$total
            ];
            Socket::broadcast("notifications:chat:remove",$update);
            return $update;
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
