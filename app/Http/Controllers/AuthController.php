<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\PhpMailer\EmailTemplate;
use App\Rules\NameCheck;
use App\Socket\Socket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use \Validator;

class AuthController extends Controller
{
    public function generateKey($str = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length = 50)
    {
        $randStr = substr(str_shuffle($str), 0, $length);
        return $randStr;
    }
    public function auth(Request $request, $isJson = true)
    {
        if (empty($_GET['token'])) {
            $this->validate($request, [
                "user_token" => "required",
            ]);
        } else {
            $request->user_token = $_GET['token'];
        }
        if ($user = User::where("user_token", "=", $request->user_token)->first()) {
            $time = explode('_', $request->user_token);
            // if(date('U') - (int)$time[0] > 10) {
            //     $token = date('U').'_'.$this->generateKey().'_'.$user['user_id'];
            //     $user['user_token'] = $token;
            //     $user['user_token_updated_at'] = date('H:i:s');
            //     User::where('user_email',$request->user_email)->update(['user_token'=>$token]);
            // }
            $user->address = DB::select('select * from addresses where user_id = ?', [$user->user_id]);
            $user->default_address = DB::select('select * from addresses where user_id = ? and is_default = 1', [$user->user_id]);
            if ($user->default_address) {
                $user->default_address = $user->default_address[0];
            }
            $user->user_type = DB::select("select * from user_types where user_type_id = ?",[$user->user_type])[0];
            unset($user->user_token);
            return $isJson ? response()->json($user) : $user;
        } else {
            $err = '["message" => "Invalid token",
            "status" => false]';
            return $isJson ? response()->json($err) : $err;
        }
    }

    public function http(Request $request, $callback, $params=[])
    {
        if ($callback == null) {
            return;
        }

        $cred = $this->auth($request, false);

        if (gettype($cred) == 'object') {
            if(sizeof($params))
                return $callback($request, $cred, $params);
            else 
                return $callback($request, $cred);
        } else {
            return response()->json([
                "message" => "Invalid token",
                "status" => false,
            ]);
        }
    }

    public function generateAuthToken($user_id, $user_email)
    {
        $token = date('U') . '_' . $this->generateKey() . '_' . $user_id;
        User::where('user_email', $user_email)->update(['user_token' => $token]);
        return $token;
    }

    public function login(Request $request)
    {
        if (!$request->user_token) {
            $this->validate($request, [
                "user_email" => "required",
                "user_password" => "required",
            ]);

            if ($user = User::where("user_email", "=", $request->user_email)->first()) {
                $user->address = DB::select('select * from addresses where user_id = ?', [$user->user_id]);
                $user->default_address = DB::select('select * from addresses where user_id = ? and is_default = 1', [$user->user_id]);
                if ($user->default_address) {
                    $user->default_address = $user->default_address[0];
                }
                $user->user_type = DB::select("select * from user_types where user_type_id = ?",[$user->user_type])[0];
                if (!password_verify($request->user_password, $user['user_password'])) {
                    return response()->json([
                        "message" => "Incorrect Password",
                        "status" => false,
                    ]);
                } else {
                    $mesage = '';
                    if ($user['user_status'] == 'Unverified') {
                        $message = "Please verify your account!";
                    } elseif ($user['user_status'] == 'Suspended') {
                        $message = "You're account is suspended!";
                    } elseif ($user['user_status'] == 'Banned') {
                        $message = "You're account is banned!";
                    } else {
                        $token = $this->generateAuthToken($user['user_id'], $user['user_email']);
                        $user['user_token'] = $token;
                        return response()->json($user);
                    }
                    $fake_token = ($user['user_token'] * 1234);
                    $user->user_token = $fake_token;
                    return response()->json([
                        "message" => $message,
                        "status" => false,
                        "error" => "UNVERIFIED",
                        "user_token" => $fake_token,
                        "user" => $user,
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

    public function validateUser(Request $request, $args)
    {
        $validator = Validator::make($request->all(), $args);
        return $validator;
    }

    public function register(Request $request)
    {
        $req = [
            "user_fname" => ['required', 'string', 'min:2', new NameCheck()],
            "user_lname" => ['required', 'string', 'min:2', new NameCheck()],
            "user_email" => ['required', 'email', 'unique:users'],
            "user_password" => ['required', 'string', 'min:6'],
        ];
        if($request->token){
            $client = User::where("user_token",$request->token)->get()->first();
        }
        if(!isset($client)){
            $req = array_merge($req,["user_agree" => ['accepted']]);
        }
        $validateUser = $this->validateUser($request, $req);
        if ($validateUser->fails()) {
            return $validateUser->messages();
        } else {
            $user = User::create(array_merge($request->except(["token","is_admin"]),[
                "user_token"=>$this->generateKey('0123456789', 4),
                "user_password"=>password_hash($request->user_password, PASSWORD_DEFAULT)
            ]));
            
            if($request->is_admin){
                $this->generateAuthToken($user->id, $user->user_email);
                $user = User::where("user_id",$user->id)->get()->first()->toArray();
                return response()->json([
                    "user" => $user,
                ]);
            }
            

            Socket::broadcast('otp', ['user_email' => $user->user_email, 'duration' => 120000, 'start_date'=>date("F d, Y h:i:s A")]);

            $otp_email = new EmailTemplate(false);
            $otp_email = $otp_email->OTPVerificationTemplate($user->user_email, $user->user_token);
            $user->user_type = $user->user_type ? $user->user_type:1;
            $user->user_type = DB::select("select * from user_types where user_type_id = ?",[$user->user_type])[0];
            if(!isset($request->return_token)){
                unset($user->user_token);
            } else {
                $user->user_token = (int)$user->user_token * 4567;
            }
            if ($otp_email['status']) {
                return response()->json([
                    "status" => true,
                    "user" => $user,
                ]);
            } else {
                return response()->json($otp_email);
            }
        }
    }

    public function resendOTP(Request $request)
    {
        $token = $this->generateKey('0123456789', 4);
        User::where('user_email', $request->user_email)->update(['user_token' => $token]);
        $user = User::where("user_email", "=", $request->user_email)->first();

        $otp_email = new EmailTemplate(false);
        $otp_email = $otp_email->OTPVerificationTemplate($request->user_email, $user->user_token);
        $fake_token = ($user['user_token'] * 1234);
        $user->user_token = $fake_token;
        if ($otp_email['status']) {
            Socket::broadcast('otp', ['user_email' => $user->user_email, 'duration' => 120000,'start_date'=>date("F d, Y h:i:s A")]);
            return response()->json([
                "status" => true,
                "user" => $user,
            ]);
        } else {
            return response()->json($otp_email);
        }
    }

    public function verifyOTP(Request $request)
    {
        $validateUser = $this->validateUser($request, [
            "user_token" => "required|string",
            "user_email" => "required|email",
        ]);
        if ($validateUser->fails()) {
            return $validateUser->messages();
        } else {
            if (!$user = User::where("user_email", '=', $request->user_email)->where("user_token", "=", (int) $request->user_token)->first()) {
                return response()->json([
                    "message" => "Incorrect Pin",
                    "status" => false,
                ]);
            } else {
                User::where('user_email', $request->user_email)->update(['user_status' => 'Verified', 'is_first_logon' => '1']);
                $token = $this->generateAuthToken($user['user_id'], $user['user_email']);
                $request->user_token = $token;
                return $this->http($request, function ($request, $cred) {
                    $cred->user_token = $request->user_token;
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
