<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'subcategory_id',
        'sub_category_id',
        'name',
        'slug',
        'brand',
        'sku',
        'upc_barcode',
        'description',
        'size',
        'short_size',
        'unit_size',
        'price',
        'old_price',
        'sale_price',
        'cost_price',
        'save_pct',
        'stock',
        'low_stock_threshold',
        'image',
        'gallery_images',
        'emoji',
        'tint',
        'tag',
        'rating',
        'reviews_count',
        'local',
        'is_organic',
        'is_gluten_free',
        'is_perishable',
        'nutrition_facts',
        'ingredients',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock' => 'integer',
            'save_pct' => 'integer',
            'low_stock_threshold' => 'integer',
            'rating' => 'decimal:2',
            'reviews_count' => 'integer',
            'local' => 'boolean',
            'is_organic' => 'boolean',
            'is_gluten_free' => 'boolean',
            'is_perishable' => 'boolean',
            'gallery_images' => 'array',
            'nutrition_facts' => 'array',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(SubCategory::class, 'subcategory_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
