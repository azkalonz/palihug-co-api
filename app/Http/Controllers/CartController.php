<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;
use \Validator;

class CartController extends Controller
{
    public function getCart(Request $request)
    {
        return $this->authenticate()->http($request, function ($request, $cred) {
            return Cart::where("user_id", "=", $cred->user_id)->get()->first();
        });
    }
    public function removeCart(Request $request)
    {
        return $this->authenticate()->http($request, function ($request, $cred) {
            if (Cart::where("user_id", "=", $cred->user_id)->delete()) {
                return response()->json([
                    "success" => true,
                ]);
            } else {
                return response()->json([
                    "success" => false,
                ]);
            }
        });
    }
    public function setCart(Request $request)
    {
        $validation = Validator::make($request->all(), [
            "meta" => "required",
            "total_items" => "required",
            "total_amount" => "required",
            "user_id" => "required",
        ]);
        if ($validation->fails()) {
            return $validation->messages();
        }

        return $this->authenticate()->http($request, function ($request, $cred) {
            $cart = Cart::where("user_id", "=", $cred->user_id);
            if ($cart->get()->first()) {
                $cart->update($request->except(["token"]));
            } else {
                $cart = Cart::create($request->all());
            }
            return $cart->get()->first();
        });
    }
}
