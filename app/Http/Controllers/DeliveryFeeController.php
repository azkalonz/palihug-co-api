<?php

namespace App\Http\Controllers;

use App\Models\DeliveryFee;
use Illuminate\Http\Request;

class DeliveryFeeController extends Controller
{
    public static $dowMap = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
    public function getFee(Request $request){
        return $this->authenticate()->http($request,function($request,$cred){
            $date = date("Y-m-d");
            $now = DeliveryFee::whereRaw("date_from <= '$date' and date_to >= '$date'")->get()->filter(function ($value, $key) {
                $dow = DeliveryFeeController::$dowMap[date("w")];
                $days = explode(",",$value["days"]);
                return in_array($dow,$days);
            })->first();
            return $now;
        });
    }
}
