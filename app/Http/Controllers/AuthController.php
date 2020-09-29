<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use \Validator;
use App\Socket\Socket;
use App\PhpMailer\EmailTemplate;

class AuthController extends Controller
{   
    public function generateKey($str = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length = 50) {
        $randStr = substr(str_shuffle($str), 0, $length);
        return $randStr;
    }

    public function auth(Request $request, $isJson = true) {
        $this->validate($request,[
            "user_token" => "required",
        ]);
        
        if($user = User::where("user_token", "=", $request->user_token)->first()) {
            $time = explode('_', $request->user_token);
            // if(date('U') - (int)$time[0] > 10) {
            //     $token = date('U').'_'.$this->generateKey().'_'.$user['user_id'];
            //     $user['user_token'] = $token;
            //     $user['user_token_updated_at'] = date('H:i:s');
            //     User::where('user_email',$request->user_email)->update(['user_token'=>$token]);
            // }
            return $isJson ? response()->json($user) : $user;
        } else {
            $err = '["message" => "Invalid token",
            "status" => false]';
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

    public function generateAuthToken($user_id,$user_email){
        $token = date('U').'_'.$this->generateKey().'_'.$user_id;
        User::where('user_email',$user_email)->update(['user_token'=>$token]);
        return $token;
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
                    $mesage = '';
                    if($user['user_status'] == 'Unverified') {
                        $message = "Please verify your account!";
                    } elseif($user['user_status'] == 'Suspended') {
                        $message = "You're account is suspended!";
                    } elseif($user['user_status'] == 'Banned') {
                        $message = "You're account is banned!";
                    }else {
                        $token = $this->generateAuthToken($user['user_id'],$user['user_email']);
                        $user['user_token'] = $token;
                        return response()->json($user);
                    }
                    return response()->json([
                        "message" => $message,
                        "status" => false,
                        "error" => "UNVERIFIED",
                    ]);

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

    public function validateUser(Request $request, $args){
        $validator = Validator::make($request->all(),$args);
        return $validator;
    }

    public function register(Request $request)
    {
        $validateUser = $this->validateUser($request, [
            "user_fname" => "required|string",
            "user_lname" => "required|string",
            "user_email" => "required|email|unique:users",
            "user_password" => "required|string|min:6|max:10",
        ]);
        if($validateUser->fails()){
            return $validateUser->messages();
        } else {
            $user = new User();
            $user->user_fname = $request->user_fname;
            $user->user_lname = $request->user_lname;
            $user->user_email = $request->user_email;
            $user->user_password = password_hash($request->user_password, PASSWORD_DEFAULT);
            $user->user_status = 'Unverified';
            $user->is_first_logon = 0;
            $user->user_token = $this->generateKey('0123456789', 4);
            $user->save();

            Socket::broadcast('OTP', ['user_email' => $user->user_email, 'duration' => 120]);

            $otp_email = new EmailTemplate(false);
            $otp_email = $otp_email->OTPVerificationTemplate($user->user_email,$user->user_token);
            if($otp_email['status']){
                return response()->json([
                    "status" => true,
                    "user" => $user,
                ]); 
            } else {
                return response()->json($otp_email);
            }
        }
    }

    public function verifyOTP(Request $request) {
        $validateUser = $this->validateUser($request, [
            "user_token" => "required|string",
            "user_email" => "required|email",
        ]);
        if($validateUser->fails()){
            return $validateUser->messages();
        } else {
            if(!$user = User::where("user_email", '=', $request->user_email)->where("user_token", "=", (int)$request->user_token)->first()) {
                return response()->json([
                    "message" => "Incorrect Pin",
                    "status" => false,
                ]);
            } else {
                User::where('user_email',$request->user_email)->update(['user_status'=>'Verified','is_first_logon'=>'1']);
                $token = $this->generateAuthToken($user['user_id'],$user['user_email']);
                $request->user_token = $token;
                return $this->authMiddleWare($request, function($request, $cred) {
                    $param = $request->all();
                    return $cred;
                });
            }
        }
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