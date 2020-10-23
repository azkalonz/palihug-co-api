<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function all()
    {
        return DB::select('select * from services');
    }
}
