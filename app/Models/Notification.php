<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
    protected $table = "notifications";
    protected $fillable = ["consumer_user_id","provider_user_id","order_id","notif_action","notif_meta","notif_type","viewed"];
}
