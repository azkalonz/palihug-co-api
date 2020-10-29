<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function all($service_id = null)
    {
        if ($service_id == null) {
            return DB::select('select * from services');
        } else {
            $service = DB::table('services')
                ->where("service_id", "=", $service_id)->first();
            $service->merchants = DB::select("SELECT * FROM merchants WHERE merch_wp_id IN (SELECT merch_id FROM merchant_services WHERE service_id = $service_id)");
            return response()->json($service);
        }
    }
}
