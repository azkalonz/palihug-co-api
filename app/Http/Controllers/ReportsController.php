<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function getSale($type,$merchant,$from,$to,$request){
        switch($type){
            case "gross_sales": 
                $sales = DB::select('SELECT SUM(order_total) as gross_sales from 
                order_details WHERE merchant_id = ? AND created_at BETWEEN CAST(? as DATE) AND CAST(? as DATE) and order_details.order_id IN (select order_id from orders where orders.order_id = order_details.order_id and orders.status = "received")'
                , [$merchant->merch_wp_id,$from,$to]);
                return $sales[0]->gross_sales;
            break;
            case "total_items": 
                return OrderDetail::where("merchant_id",$merchant->merch_wp_id)
                ->whereBetween('created_at',[$from,$to])
                ->whereRaw("order_details.order_id IN (select order_id from orders where orders.order_id = order_details.order_id and orders.status = 'received')")
                ->groupBy("merchant_id")
                ->sum("order_qty");
            break;
            case "total_orders": 
                $queries = ["received"=>0,"pending"=>0,"all"=>0,"cancelled"=>0,"receiving"=>0,"processing"=>0];
                foreach($queries as $q => $value){
                    $filter = "and orders.status = '$q'";
                    if($q=="all")
                        $filter = "";
                    $queries[$q] = OrderDetail::distinct("order_id")
                    ->where("merchant_id",$merchant->merch_wp_id)
                    ->whereBetween('created_at',[$from,$to])
                    ->whereRaw("order_details.order_id IN (select order_id from orders where orders.order_id = order_details.order_id $filter)")
                    ->count();
                }
                return $queries;
            break;
            case "sales_by_product": 
                return OrderDetail::where("merchant_id",$merchant->merch_wp_id)
                ->whereBetween('created_at',[$from,$to])
                ->whereRaw("order_details.order_id IN (select order_id from orders where orders.order_id = order_details.order_id and orders.status = 'received')")
                ->selectRaw('product_meta, sum(order_qty) as total_items')
                ->groupBy(["prod_id","product_meta"])
                ->get();
            break;
            case "gross_sales_monthly":
                return $this->getMonthSalesSummary($request);
        }
    }
    public function getMonthSalesSummary(Request $request){
        return $this->authenticate()->http($request, function($request, $cred){
        if($cred->user_type->name === "merchant"){
                
                $merchant = Merchant::where("user_id",$cred->user_id)->get()->first();
                if($merchant){
                    $sales = [];
                    
                    for($i=1; $i<=30; $i++){
                        $month = (int)date("m",strtotime($request->from));
                        $from = date("Y-$month-1");
                        $to = date("Y-$month-$i");
                        array_push($sales,[
                            "date"=>$to,
                            "gross_sales"=>$this->getSale("gross_sales",$merchant,$from,$to,$request),
                        ]);
                    }
                    return $sales;
                }
            } else {
                return [
                    "success"=>false,
                    "message"=>"Invalid access"
                ];
            }
        });
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
                        "gross_sales_monthly"=>[],
                        "total_items"=>[],
                        "total_orders"=>[],
                        "sales_by_product"=>[]
                    ];
                    foreach($return as $key => $value){
                        if($request->query($key)!=null || $request->all == "true"){
                            $sales = $this->getSale($key,$merchant,$from,$to,$request);
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
