<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;

class UserController extends Controller
{
    public function firstLogin(Request $request){
        $this->validate($request, [
            "user_email" => "required",
        ]);
        return $this->authenticate()->http($request,function($request,$cred){
            DB::update('update users set is_first_logon = 0 where user_email = ?', [$request->user_email]);
            return response()->json(DB::select('select * from users where user_email = ?', [$request->user_email])[0]);
        });
    }
}
