<?php

namespace App\Http\Controllers;

use App\Socket\Socket;
use Illuminate\Http\Request;
use JWTAuth;

class Hook extends Controller
{
    public function __construct()
    {
        $this->user = JWTAuth::parseToken()->authenticate();
    }
    public function notifications(Request $request)
    {
        $param = $request->all();
        Socket::broadcast("notification", $param);
        return $param;
    }
}