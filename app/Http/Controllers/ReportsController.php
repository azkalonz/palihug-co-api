<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportsController extends Controller
{
    public function getSale($type,$merchant,$from,$to){
        switch($type){
            case "gross_sales": 
                return OrderDetail::where("merchant_id",$merchant->merch_wp_id)
                ->whereBetween('created_at',[$from,$to])
                ->selectRaw('SUM(order_total * order_qty) as gross_sales')
                ->get()->first()['gross_sales'];
            break;
            case "total_items": 
                return OrderDetail::where("merchant_id",$merchant->merch_wp_id)
                ->whereBetween('created_at',[$from,$to])
                ->groupBy("merchant_id")
                ->sum("order_qty");
            break;
            case "total_orders": 
                return OrderDetail::distinct("order_id")
                ->where("merchant_id",$merchant->merch_wp_id)
                ->whereBetween('created_at',[$from,$to])
                ->count();
            break;
            case "sales_by_product": 
                return OrderDetail::where("merchant_id",$merchant->merch_wp_id)
                ->whereBetween('created_at',[$from,$to])
                ->selectRaw('product_meta, sum(order_qty) as total_items')
                ->groupBy(["prod_id","product_meta"])
                ->get();
            break;
        }
    }
    public function getSalesCount(Request $request, Response $response){
        return $this->authenticate()->http($request,function($request, $cred){
            if($cred->user_type->name === "merchant"){
                $merchant = Merchant::where("user_id",$cred->user_id)->get()->first();
                if($merchant){
                    $from = date($request->query("from","Y-m-1"));
                    if(!($to = date($request->to))){
                        $to = date('Y-m-1', strtotime("+30 days"));
                    }
                    $return = [
                        "gross_sales"=>[],
                        "total_items"=>[],
                        "total_orders"=>[],
                        "sales_by_product"=>[]
                    ];
                    foreach($return as $key => $value){
                        if($request->query($key)!=null || $request->all == "true"){
                            $sales = $this->getSale($key,$merchant,$from,$to);
                            $return[$key] = $sales==null?false:$sales;
                        }  else {
                            unset($return[$key]);
                        }
                    }
                    return $return;
                }
            } else {
                return [
                    "success"=>false,
                    "message"=>"Invalid access"
                ];
            }
        });
    }
}
