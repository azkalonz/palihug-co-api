<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public $loginAfterSignup = true;
    public function login(Request $request)
    {
        $creds = $request->only(["email", "password"]);
        $token = null;
        if (!$token = auth()->attempt($creds)) {
            return response()->json([
                "status" => $token,
                "message" => "Unauthorized",
            ]);
        }
        return response()->json([
            "status" => true,
            "token" => $token,
        ]);
    }
    public function register(Request $request)
    {
        $this->validate($request, [
            "name" => "required|string",
            "email" => "required|email|unique:users",
            "password" => "required|string|min:6|max:10",
        ]);
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = password_hash($request->password, PASSWORD_DEFAULT);
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