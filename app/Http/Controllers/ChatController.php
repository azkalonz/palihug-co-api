<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Socket\Socket;
use App\Models\Chat;
use App\Http\Controllers\AuthController;

class ChatController extends Controller
{
    public function getConvo(Request $request) {
        if(empty($_GET['trans_id']) || empty($_GET['receiver_id']) || empty($_GET['sender_id'])){
            return response()->json([
                'status'=>false,
                'message'=>'Invalid parameters'
            ]);
        }

        return $this->authenticate()->http($request, function($request, $cred) {
            $convo = Chat::where("trans_id","=",$_GET['trans_id'])->where(function ($query) {
                $query->where('receiver_id', '=', $_GET['receiver_id'])
                      ->orWhere('receiver_id', '=', $_GET['sender_id']);
            })->where(function ($query) {
                $query->where('sender_id', '=', $_GET['sender_id'])
                      ->orWhere('sender_id', '=', $_GET['receiver_id']);
            })->get();

            return $convo;
        });
    }

    public function sendMessage(Request $request) {
        request()->validate([
            'trans_id' => ['required'],
            'sender_id' => ['required'],
            'receiver_id' => ['required'],
            'chat_message' => ['required'],
        ]);

        return $this->authenticate()->http($request, function($request, $cred) {
            $chat = new Chat();
            $chat->trans_id = $request->trans_id;
            $chat->receiver_id = $request->receiver_id;
            $chat->sender_id = $request->sender_id;
            $chat->chat_message = $request->chat_message;
            $chat->chat_time = date('Y-m-d h:i:s');
            $chat->save();

            Socket::broadcast('chat', ['trans_id' => $chat->trans_id, 'receiver_id' => $chat->receiver_id, 'sender_id' => $chat->sender_id, 'chat_message' => $chat->chat_message, 'chat_time' => $chat->chat_time]);

            return response()->json([
                "status" => true
            ]);
        });
    }
}
