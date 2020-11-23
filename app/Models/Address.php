<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = "addresses";
    protected $fillable = [
        'city', 'street', 'province', 'country', 'house_number', 'is_default', 'name', 'contact', 'user_id', 'barangay', 'zip',"lat","lng"
    ];
}
