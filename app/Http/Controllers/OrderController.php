<?php

namespace App\Http\Controllers;

use App\Exports\TransactionExport;
use App\Models\Chat;
use App\Models\Merchant;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderDetail;
use App\PhpMailer\EmailTemplate;
use App\Socket\Socket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use \Validator;

class OrderController extends Controller
{
    public static function makeStatusText($order_status){
        switch($order_status){
            case "pending": return "Finding you a rider";
            case "processing": return "Purchasing your order";
            case "cancelled": return "Order is cancelled";
            case "received": return "Order Complete";
            case "receiving": return "Delivering your order";
            case "created": return "Finding you a rider";
            default: return "Finding you a rider";
        }
    }
    public function export(Request $request){
        return $this->authenticate()->http($request,function($request,$cred){
            return Excel::download(new TransactionExport(
                $this->getMerchantTransactions($cred)
                ->makeHidden(['product_meta','delivery_info','consumer_user_id','provider_user_id','merchant_id','order_detail_id','order_total','order_qty','service_id','payment_id','prod_id','date_confirmed','created_at','updated_at'])
            ), 'Transactions'.date("-Y-m-d h:i:s").'.xlsx');

        });
    }
    public function createOrder(Request $request){
        $validation = Validator::make($request->all(), [
            "consumer_user_id" => "required",
            "service_id" => "required",
            "payment_id" => "required",
            "total" => "required",
            "delivery_info" => "required",
            "delivery_fee"=>"required"
        ]);
        if ($validation->fails()) {
            return $validation->messages();
        }
        return $this->authenticate()->http($request, function($request,$cred){
            $order = Order::create(array_merge($request->all(),["status_text"=>OrderController::makeStatusText("created")]));
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
            function thisOrAdmin($type,$cred){
                return $cred->user_type->name == $type || $cred->user_type->name == "admin";
            }
            if($request->user_type == "customer" && thisOrAdmin("customer",$cred))
            return Order::where("consumer_user_id","=",$cred->user_id)->get();
            else if($request->user_type == "driver" && thisOrAdmin("driver",$cred))
            return Order::where("provider_user_id","=",$cred->user_id)
            ->orWhere("provider_user_id",null)
            ->orWhere(function($q){
                $q->where("provider_user_id","!=",null)->where("status","pending");
            })
            ->get();
            else if($request->user_type == "merchant" && thisOrAdmin("merchant",$cred))
            return $this->getMerchantTransactions($cred)->get();
            else if(thisOrAdmin("admin",$cred))
            return Order::leftJoin("users as u1","u1.user_id","=","orders.provider_user_id")
            ->leftJoin("users as u2","u2.user_id","=","orders.consumer_user_id")
            ->selectRaw(" orders.*, CONCAT(u1.user_fname,' ',u1.user_lname) as provider_name,
            CONCAT(u2.user_fname,' ',u2.user_lname) as consumer_name")->get();
        });
    }
    public function getMerchantTransactions($cred){
        $merchant = Merchant::where("user_id",$cred->user_id)->get()->first();
        return OrderDetail::join("orders as o","o.order_id","=","order_details.order_id")
        ->leftJoin("users as u1","u1.user_id","=","o.provider_user_id")
        ->leftJoin("users as u2","u2.user_id","=","o.consumer_user_id")
        ->selectRaw(" o.*, CONCAT(u1.user_fname,' ',u1.user_lname) as provider_name,
        CONCAT(u2.user_fname,' ',u2.user_lname) as consumer_name,
            order_details.*")
        ->groupBy("order_details.order_id")
        ->where("order_details.merchant_id",$merchant->merch_wp_id);
    }
    public function updateOrder(Request $request){
        if($request->status == "received"){
            if(!isset($request->amount_paid) || is_nan((float)$request->amount_paid)){
                return ["error"=>true, "message"=>"Invalid Amount"];
            }
        }
        $validation = Validator::make($request->all(), [
            "order_id" => "required",
        ]);
        if ($validation->fails()) {
            return $validation->messages();
        }
        return $this->authenticate()->http($request, function($request,$cred){
            $user_type = $cred->user_type->name;
            if($user_type!=='customer'){
                $order = Order::where("order_id","=",$request->order_id);
                $o1 = clone $order;
                $o1 = $o1->get()->first();
                if($order->get()->first()->provider_user_id){
                    if($cred->user_type->name != "admin"){
                        if($o1->status != "pending" && $o1->provider_user_id != $cred->user_id){
                            return [
                                "error"=> true,
                                "message"=> "[Unauthorized access] Order is already accepted by other user."
                            ];
                        }
                    }
                }
                if($o1->status != "pending" && $o1->provider_user_id != null){
                    $provider_id = $o1->provider_user_id;
                } else {
                    Chat::where("order_id",$o1->order_id)->delete();
                    Notification::where("order_id",$o1->order_id)->delete();
                    $provider_id = $cred->user_id;
                }
                $should_notify = $request->status != $o1->status;
                $order->update(array_merge($request->except(["token","products"]),[
                    "provider_user_id"=>$provider_id,
                    "status_text"=>OrderController::makeStatusText($request->status)
                    ]));
                $order = $order->get()->first();
                $order_details = OrderDetail::where("order_id","=",$request->order_id)->get();
                $order->products = $order_details;
                $customer = DB::table("users")->where("user_id","=",$order->consumer_user_id)->first();
                $driver = DB::table("users")->where("user_id","=",$order->provider_user_id)->first();
                $order->consumer_name =$customer->user_fname.' '.$customer->user_lname;
                $order->provider_name = $driver->user_fname.' '.$driver->user_lname;
                Socket::broadcast("order:update",$order->toArray());
                if($should_notify)
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
                '$order_num' => $order['order_id'],
                '$order' => $order_text,
                '$order_total' => 'PHP '.((float)$order['total']+(float)$order['delivery_fee'])
            );
            if($is_driver_triggered){
                $driver = DB::table("users")->where("user_id","=",$order['provider_user_id'])->first();
                $params = array_merge($params,[
                    '$driver' => $driver->user_fname.' '.$driver->user_lname
                ]);
            }

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
                    'sender_id'=>$driver->user_id, 
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
                if($user_type == "driver" || $user_type == "merchant" || $user_type == "admin"){
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
