<?php

namespace App\Http\Controllers;

use App\Socket\Socket;
use Illuminate\Http\Request;
use JWTAuth;
use App\Http\Controllers\AuthController;

class Hook extends Controller
{
    public function notifications(Request $request)
    {
        $auth = new AuthController;
        
        return $auth->authMiddleWare($request, function($request, $cred) {
            $param = $request->all();
            Socket::broadcast("notification", $param);
            return $param;
        }); 
    }
}