<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Socket\Socket;

class Hook extends Controller
{
    public function notifications(Request $request){
        $param = $request->all();
        Socket::broadcast("notification",$param);
        return $param;
    }
}
