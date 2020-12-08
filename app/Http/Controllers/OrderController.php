<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Order;
use App\Models\OrderDetail;
use App\PhpMailer\EmailTemplate;
use App\Socket\Socket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use \Validator;

class OrderController extends Controller
{
    public function createOrder(Request $request){
        $validation = Validator::make($request->all(), [
            "consumer_user_id" => "required",
            "service_id" => "required",
            "payment_id" => "required",
            "total" => "required",
            "delivery_info" => "required",
        ]);
        if ($validation->fails()) {
            return $validation->messages();
        }
        return $this->authenticate()->http($request, function($request,$cred){
            $order = Order::create($request->all());
            $order = Order::where("order_id","=",$order->id)->get()->first();
            $order->success = true;
            $this->createOrderDetails($request,$order->order_id);
            Socket::broadcast("order:new",$order->toArray());
            $this->orderUpdateHook($request,$order,false);
            return $order;
        });
    }
    public function createOrderDetails(Request $request,$order_id){
        $products = (array)$request->products;
        for($i=0; $i<sizeof($products); $i++){
            $products[$i]['order_id'] = $order_id;
        }
        OrderDetail::insert($products);
    }
    public function getOrder(Request $request, $user_type = "customer"){
        $request->user_type = $user_type;
        return $this->authenticate()->http($request,function($request,$cred){
            if($request->user_type == "customer")
                return Order::where("consumer_user_id","=",$cred->user_id)->get();
            else if($request->user_type == "driver")
                return Order::where("provider_user_id","=",$cred->user_id)->orWhere("provider_user_id","=",null)->get();
        });
    }
    public function updateOrder(Request $request){
        $validation = Validator::make($request->all(), [
            "order_id" => "required",
        ]);
        if ($validation->fails()) {
            return $validation->messages();
        }
        return $this->authenticate()->http($request, function($request,$cred){
            $user_type = $cred->user_type->name;
            if($user_type==='driver'){
                $order = Order::where("order_id","=",$request->order_id);
                $order->update(array_merge($request->except(["token"]),[
                    "provider_user_id"=>$cred->user_id,
                    ]));
                Socket::broadcast("order:update",$order->get()->first()->toArray());
                $order = $order->get()->first();
                $order_details = OrderDetail::where("order_id","=",$request->order_id)->get();
                $order->products = $order_details;
                $this->orderUpdateHook($request,$order,true);
                return $order;
            }
        });
    }
    public function orderUpdateHook($request,$order,$is_driver_triggered){
        return $this->authenticate()->http($request, function($request, $cred, $params) {
            $order = $params['order'];
            $is_driver_triggered = $params['is_driver_triggered'];
            if(!$is_driver_triggered){
                $order['status'] = 'created';
            }
            $msg_body = DB::table('order_msg_templates')->where("order_status","=",$order['status'])->first();
            $msg_body = $msg_body->message;
            $customer = DB::table("users")->where("user_id","=",$order['consumer_user_id'])->first();
            $order_detail = OrderDetail::where("order_id","=",$order['order_id'])->get()->toArray();
            $order_text = '';
            foreach($order_detail as $detail){
                $product = json_decode($detail['product_meta']);
                $order_text = "{$product->name} x {$detail['order_qty']}, ";
            }
            $params = array(
                '$customer' => $customer->user_fname.' '.$customer->user_lname,
                '$driver' => $cred->user_fname.' '.$cred->user_lname,
                '$order_num' => $order['order_id'],
                '$order' => $order_text,
                '$order_total' => 'PHP '.$order['total']
            );
            if($order['est_total']){
                $params['$order_total'] = '~ PHP '.$order['est_total'];
            }
            $msg_body = strtr($msg_body, $params);
            $otp_email = new EmailTemplate(false);
            $otp_email = $otp_email->OrderUpdateTemplate($customer->user_email, "Update for your order #".$order['order_id'],$msg_body,$order_detail,$order);

            if(!$is_driver_triggered){
                return true;
            } else {
                $chat = Chat::create([
                    'order_id'=>$order['order_id'], 
                    'receiver_id'=>$order['consumer_user_id'], 
                    'sender_id'=>$cred->user_id, 
                    'chat_meta'=>json_encode([
                        "message"=>$msg_body,
                        "type"=>"text"
                    ])
                ]);
                Socket::broadcast('send:room:orders', $chat->toArray());
                return $this->hook()->chat_notifications($request,[
                    "consumer_user_id" => $chat->receiver_id,
                    "provider_user_id" => $chat->sender_id,
                    "order_id" => $chat->order_id,
                    "notif_action" => json_encode([
                        "pathname"=>"/chat/$chat->order_id"
                    ]),
                    "notif_meta"=>json_encode([
                        "title" => "Message for order #$chat->order_id",
                        "body" => $msg_body
                    ]),
                    "notif_type"=>"chat"
                ]);
            }
        },["order"=>$order->toArray(),"is_driver_triggered"=>$is_driver_triggered]);
    }
    
    public function orderInfo(Request $request,$order_id=null){
        if($order_id!=null){
            $request->order_id = $order_id;
            return $this->authenticate()->http($request, function($request,$cred){
                $order = Order::where("order_id","=",$request->order_id)->get()->first();
                $order_details = OrderDetail::where("order_id","=",$request->order_id)->get();
                $order->products = $order_details;
                $user_type = $cred->user_type->name;
                if($user_type == "driver"){
                    return response()->json($order);
                } else if($cred->user_id == $order->consumer_user_id){
                    return response()->json($order);
                } else {
                    return response()->json([
                        "error"=>true,
                        "message"=>"Unauthorized access"
                    ]);
                }
            });
        }
    }
}
