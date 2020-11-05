<?php

namespace App\Http\Controllers;

use App\WC\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MerchantController extends Controller
{
    public function all($merch_id = null)
    {
        if ($merch_id === null) {
            $merchants = DB::table('merchants')
                ->get();
            return $merchants;
        } else {
            $merchant = DB::table('merchants')
                ->where("merch_wp_id", "=", $merch_id)
                ->first();
            $merchant->vendor = Api::wp("wcfmmp/v1")->get("store-vendors/" . $merch_id);
            return response()->json($merchant);
        }

    }
    public function category_exists($categories, $category)
    {
        foreach ($categories as $c) {
            if ($c->id == $category->id) {
                return true;
            }

        }
        return false;
    }
    public function products(Request $request, $merch_id = null)
    {
        if ($merch_id == null) {
            return response()->json([
                "message" => "missing merchant id",
            ]);
        } else {
            $categories = [];
            $products = Api::wp("wcfmmp/v1")->get("store-vendors/" . $merch_id . "/products");
            foreach ($products as $product) {
                foreach ($product->categories as $category) {
                    if (!$this->category_exists($categories, $category)) {
                        array_push($categories, $category);
                    }
                }
            }
            return response()->json([
                "merchant" => $this->all($merch_id)->original,
                "categories" => $categories,
                "products" => $products,
            ]);
        }
    }

    public function productArchive(Request $request)
    {
        return Api::wp("wc/v3")->get("products", $request->all());
    }

}
