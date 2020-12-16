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

    public function update_notifications(Request $request)
    {
        return $this->authenticate()->http($request, function($request, $cred) {
            if($cred->user_type->name == "admin"){
                $notification = Notification::create(array_merge($request->all(),[
                    "provider_user_id"=>$cred->user_id
                ]));
                $notification = $notification->toArray();
                $u = DB::select("select * from users where user_id = ?",[$cred->user_id]);
                $notification['provider_name'] = $u[0]->user_fname.' '.$u[0]->user_lname;
                Socket::broadcast("notifications:update", $notification);
                return $notification;
            } else {
                return ["error"=>true,"message"=>"Unauthorized"];
            }
        });
    }

    public function getNotifications(Request $request)
    {
        return $this->authenticate()->http($request, function ($request, $cred) {
            if(!isset($request->count)){
                $notification = DB::select("select 
                concat(provider.user_fname,' ',provider.user_lname) as provider_name,
                n1.* from notifications as n1
                inner join 
                    users as provider on provider.user_id = n1.provider_user_id
                where 
                    (
                        n1.order_id
                        in (
                        select DISTINCT(order_id) from notifications as n2
                        where 
                        consumer_user_id = ?
                        )
                    and n1.noti_id
                        in (
                            select max(noti_id) from notifications as n3
                            where n3.order_id = n1.order_id and n1.consumer_user_id = ? and n3.provider_user_id = n1.provider_user_id
                        )
                        ) 
                        or (n1.consumer_user_id = -1 or 
                        n1.noti_id in (
                                select max(noti_id) from notifications
                                where consumer_user_id = ? and order_id = -1
                            )
                        
                        )",[$cred->user_id,$cred->user_id,$cred->user_id]);
                return $notification;
            }
            else 
               { 
                $cart_count = Cart::where("user_id","=",$cred->user_id)->get(["total_items"])->first();   
                if($cart_count){
                    $cart_count = $cart_count->toArray()['total_items'];
                } else {
                    $cart_count = 0;
                }
                return [
                    "notifications"=>Notification::where("consumer_user_id", "=", $cred->user_id)->where("viewed","=",0)->get()->count(),
                    "cart"=>$cart_count
                ];}
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
