<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use \Validator;

class UserController extends Controller
{
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
                DB::update('update address set is_default = 0 where user_id = ?', [$request->user_id]);
            }
            if (!$request->add_id) {
                $address = new Address;
                $address->city = $request->city;
                $address->street = $request->street;
                $address->barangay = $request->barangay;
                $address->province = $request->province;
                $address->zip = $request->zip;
                $address->user_id = $request->user_id;
                $address->name = $request->name;
                $address->contact = $request->contact;
                $address->is_default = $request->is_default ? $request->is_default : 0;
                $address->house_number = $request->house_number;
                $address->save();
                return response()->json([
                    "success" => true,
                    "address" => Address::where("user_id", "=", $request->user_id)->orderBy('add_id', 'desc')->first(),
                ]);
            } else {
                DB::update('update address set street = ?, city = ?, barangay = ?, province = ?, zip = ?, is_default = ?, name = ?, contact = ?, house_number = ? where add_id = ?',
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
                    "address" => Address::where("add_id", "=", $request->add_id)->first(),
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
                DB::update('update address set is_default = 0');
                DB::update('update address set is_default = 1 where add_id = ?', [$request->add_id]);
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
            $user->address = DB::select('select * from address where user_id = ?', [$request->user_email]);
            return response()->json($user);
        });
    }
}
