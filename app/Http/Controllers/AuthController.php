<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{   
    public function generateKey() {
        $str = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $randStr = substr(str_shuffle($str), 0, 50);
        return $randStr;
    }

    public function auth(Request $request, $isJson = true) {
        $this->validate($request,[
            "user_token" => "required",
        ]);
        
        if($user = User::where("user_token", "=", $request->user_token)->first()) {
            $time = explode('_', $request->user_token);
            if(date('U') - (int)$time[0] > 3600) {
                $token = date('U').'_'.$this->generateKey().'_'.$user['user_id'];
                $user['user_token'] = $token;
                $user['user_token_updated_at'] = date('H:i:s');
                User::where('user_email',$request->user_email)->update(['user_token'=>$token]);
            }
            
            return $isJson ? response()->json($user) : $user;
        } else {
            $err = ["message" => "Invalid token",
            "status" => false];
            return $isJson ? response()->json($err) : $err;
        }
    }

    public function authMiddleWare(Request $request, $callback) {
        if($callback == null) {
            return;
        }

        $cred = $this->auth($request, false);

        if(gettype($cred) == 'object') {
            return $callback($request, $cred);
        } else {
            return response()->json([
                "message" => "Invalid token",
                "status" => false,
            ]);
        }
    }

    public function login(Request $request)
    {
        if(!$request->user_token) {
            $this->validate($request,[
                "user_email" => "required",
                "user_password" => "required",
            ]);
            
            if($user = User::where("user_email", "=", $request->user_email)->first()){
                if(!password_verify($request->user_password, $user['user_password'])) {
                    return response()->json([
                        "message" => "Incorrect Password",
                        "status" => false,
                    ]);
                } else {
                    $token = date('U').'_'.$this->generateKey().'_'.$user['user_id'];
                    $user['user_token'] = $token;
                    User::where('user_email',$request->user_email)->update(['user_token'=>$token]);
                    return response()->json($user);
                }
            } else {
                return response()->json([
                    "message" => "User not found",
                    "status" => false,
                ]);
            }
        } else {
            return $this->auth($request);
        }
    }

    public function register(Request $request)
    {
        $this->validate($request, [
            "user_fname" => "required|string",
            "user_lname" => "required|string",
            "user_email" => "required|email|unique:users",
            "user_password" => "required|string|min:6|max:10",
        ]);
        $user = new User();
        $user->user_fname = $request->user_fname;
        $user->user_lname = $request->user_lname;
        $user->user_email = $request->user_email;
        $user->user_password = password_hash($request->user_password, PASSWORD_DEFAULT);
        $user->save();

        if ($this->loginAfterSignup) {
            $this->login($request);
        }

        return response()->json([
            "status" => true,
            "user" => $user,
        ]);
    }
    public function logout(Request $request)
    {
        $this->validate($request, [
            "token" => "required",
        ]);

        try {
            JWTAuth::invalidate($request->token);
            return response()->json([
                "status" => true,
                "message" => "User logged out successfully",
            ]);
        } catch (JWTException $e) {
            return response()->json([
                "status" => false,
                "message" => "Logout failed",
            ]);
        }
    }
}