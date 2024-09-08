<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'product_name',
        'product_type',
        'description',
        'SKU',
        'tag',
        'category_id',
        'points',
        'location_id',
        'variation',
        'available_qty',
        'created_by',
        'status',
    ];

    public function category()
    {
        return $this->belongsToMany(Category::class, 'category_product' ,'product_id', 'category_id');
    }
}
