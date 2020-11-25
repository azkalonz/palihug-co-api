<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Socket\Socket;
use App\Models\Chat;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use \Validator;

class ChatController extends Controller
{
    public function getConvo(Request $request) {
        if(empty($_GET['order_id'])){
            return response()->json([
                'status'=>false,
                'message'=>'Invalid parameters'
            ]);
        }

        return $this->authenticate()->http($request, function($request, $cred) {
            $convo = DB::select("select * from chats where order_id = ?",[$_GET['order_id']]);
            $chat = [];
            $chat['messages'] = $convo;
            $order = Order::where("order_id","=",$_GET['order_id'])->get()->first();
            $p1 = $order->consumer_user_id;
            $p2 = $order->provider_user_id;
            $chat['participants'] = DB::select("select * from users where user_id = ? or user_id = ?",[$p1,$p2]);

            // $convo = Chat::where("order_id","=",$_GET['order_id'])->where(function ($query) {
            //     $query->where('receiver_id', '=', $_GET['receiver_id'])
            //           ->orWhere('receiver_id', '=', $_GET['sender_id']);
            // })->where(function ($query) {
            //     $query->where('sender_id', '=', $_GET['sender_id'])
            //           ->orWhere('sender_id', '=', $_GET['receiver_id']);
            // })->get();
            return $chat;
        });
    }

    public function sendMessage(Request $request) {
        $validation = Validator::make($request->all(), [
            'order_id' => ['required'],
            'sender_id' => ['required'],
            'receiver_id' => ['required'],
            'chat_meta' => ['required'],
        ]);
        if ($validation->fails()) {
            return $validation->messages();
        }

        return $this->authenticate()->http($request, function($request, $cred) {
            $chat = Chat::create($request->except(["token"]));
            Socket::broadcast('send:room:orders', $chat->toArray());
            $decoded_message = json_decode($chat->chat_meta);
            if($decoded_message->type == 'text'){
                $msg_body = $decoded_message->message;
            } else if ($decoded_message->type == 'map'){
                $msg_body = "Sent a location";
            }
            $this->hook()->chat_notifications($request,[
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
            return response()->json([
                "message" => $chat
            ]);
        });
    }
}
