<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $table = "orders";
    protected $fillable = ["consumer_user_id","provider_user_id","service_id","payment_id","note","order_date","date_confirmed","total","est_total","status","delivery_info","status_text"];
}
