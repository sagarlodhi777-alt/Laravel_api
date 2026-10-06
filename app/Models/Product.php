<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'subcategory_id',
        'sku',
        'upc_barcode',
        'name',
        'brand',
        'description',
        'unit_size',
        'price',
        'sale_price',
        'cost_price',
        'stock',
        'low_stock_threshold',
        'image',
        'gallery_images',
        'nutrition_facts',
        'ingredients',
        'is_organic',
        'is_gluten_free',
        'is_perishable',
        'status'
        'category_id', 'sub_category_id', 'name', 'slug', 'description', 
        'size', 'short_size', 'price', 'old_price', 'save_pct', 
        'stock', 'image', 'emoji', 'tint', 'tag', 'rating', 
        'reviews_count', 'local'
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'gallery_images' => 'array',
            'nutrition_facts' => 'array',
            'is_organic' => 'boolean',
            'is_gluten_free' => 'boolean',
            'is_perishable' => 'boolean',
        ];
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }
}
