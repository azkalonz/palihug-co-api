<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use \Validator;

class UserController extends Controller
{
    public function getUsers(Request $request){
        return $this->authenticate()->http($request, function ($request, $cred) {
            if($cred->user_type->name == "admin"){
                return User::where("user_id","!=",$cred->user_id)->get();
            } 
            return [];
        });
    }
    public function updateUser(Request $request){
        $validation = Validator::make($request->all(), [
            "user_id" => "required",
        ]);
        if ($validation->fails()) {
            return $validation->messages();
        }
        return $this->authenticate()->http($request, function ($request, $cred) {
            if($cred->user_type->name == "admin"){
                $user = User::where("user_id",$request->user_id);
                $user->update($request->except(["token"]));
                return $user->get()->first();
            } else {
                return ["error"=>"Forbidden"];
            }
        });
    }
    public function deleteUser(Request $request){
        $validation = Validator::make($request->all(), [
            "user_id" => "required",
        ]);
        if ($validation->fails()) {
            return $validation->messages();
        }
        return $this->authenticate()->http($request, function ($request, $cred) {
            if($cred->user_type->name == "admin"){
                User::where("user_id",$request->user_id)->delete();
                return ["success"=>true];
            } else {
                return ["error"=>"Forbidden"];
            }
        });
    }
    public function addAddress(Request $request)
    {
        $validation = Validator::make($request->all(), [
            "city" => "required",
            "barangay" => "required",
            "province" => "required",
            "zip" => "required|numeric",
            "user_id" => "required",
            "name" => "required",
            "contact" => "required",
        ]);
        if ($validation->fails()) {
            return $validation->messages();
        }

        return $this->authenticate()->http($request, function ($request, $cred) {
            if ($request->is_default) {
                Address::where("user_id", "=", $request->user_id)->update(["is_default" => 0]);
            }
            $address = Address::where("add_id", "=", $request->add_id);
            $address->timestamps = false;
            if (!$address->get()->last()) {
                $address = Address::create($request->all());
                return response()->json([
                    "success" => true,
                    "address" => $address->get()->last(),
                ]);
            } else {
                // $address->update($request->except(['user_token']));
                DB::update('update addresses set street = ?, city = ?, barangay = ?, province = ?, zip = ?, is_default = ?, name = ?, contact = ?, house_number = ? where add_id = ?',
                    [
                        $request->street,
                        $request->city,
                        $request->barangay,
                        $request->province,
                        $request->zip,
                        $request->is_default || 0,
                        $request->name,
                        $request->contact,
                        $request->house_number,
                        $request->add_id,
                    ]);
                return response()->json([
                    "success" => true,
                    "address" => $address->get()->last(),
                ]);

            }
        });
    }

    public function defaultAddress(Request $request)
    {
        $validation = Validator::make($request->all(), [
            "add_id" => "required|numeric",
        ]);
        if ($validation->fails()) {
            return $validation->messages();
        }

        return $this->authenticate()->http($request, function ($request, $cred) {
            if ($request->add_id) {
                DB::update('update addresses set is_default = 0');
                DB::update('update addresses set is_default = 1 where add_id = ?', [$request->add_id]);
                return response()->json([
                    "success" => true,
                    "address" => Address::where("add_id", "=", $request->add_id)->first(),
                ]);
            } else {
                return response()->json([
                    "error" => true,
                    "message" => "invalid address",
                ]);
            }
        });
    }
    public function firstLogin(Request $request)
    {
        $this->validate($request, [
            "user_email" => "required",
        ]);
        return $this->authenticate()->http($request, function ($request, $cred) {
            DB::update('update users set is_first_logon = 0 where user_email = ?', [$request->user_email]);
            $user = DB::select('select * from users where user_email = ?', [$request->user_email])[0];
            $user->addresses = DB::select('select * from addresses where user_id = ?', [$request->user_email]);
            return response()->json($user);
        });
    }
}
