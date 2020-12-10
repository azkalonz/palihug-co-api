<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use HasFactory;
    protected $table = "order_details";
    protected $fillable = ["prod_id","order_id","order_qty","order_total","merchant_id","product_meta","order_detail_id"];
    public function scopeExclude($query, $value = []) 
    {
        return $query->select(array_diff($this->columns, (array) $value));
    }
}
