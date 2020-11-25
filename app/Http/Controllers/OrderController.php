<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Socket\Socket;
use Illuminate\Http\Request;
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
    public function acceptOrder(Request $request){
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
                $order->update([
                    "provider_user_id"=>$cred->user_id,
                    "status"=>"processing",
                    "status_text"=>"Preparing your food",
                    ]);
                Socket::broadcast("order:update",$order->get()->first()->toArray());
                $order = $order->get()->first();
                $order_details = OrderDetail::where("order_id","=",$request->order_id)->get();
                $order->products = $order_details;

                return $order;
            }
        });
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
