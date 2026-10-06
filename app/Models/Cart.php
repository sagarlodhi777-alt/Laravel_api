<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'session_id'];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Compute subtotal, free delivery progress, taxes, delivery fee, and totals.
     */
    public function getSummaryAttribute(): array
    {
        $subtotal = 0.00;
        $totalItemsCount = 0;
        $formattedItems = [];

        foreach ($this->items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            $unitPrice = (float)($product->sale_price ?? $product->price);
            $lineTotal = round($unitPrice * $item->quantity, 2);
            $subtotal += $lineTotal;
            $totalItemsCount += $item->quantity;

            $formattedItems[] = [
                'id' => $item->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'brand' => $product->brand,
                'unit_size' => $product->unit_size,
                'image' => $product->image,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'quantity' => $item->quantity,
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'in_stock' => $product->stock >= $item->quantity,
                'available_stock' => $product->stock
            ];
        }

        // Supermarket threshold logic ($35.00 for free delivery, otherwise $2.99)
        $freeDeliveryThreshold = 35.00;
        $deliveryFee = $subtotal >= $freeDeliveryThreshold || $subtotal == 0 ? 0.00 : 2.99;
        $amountNeededForFreeDelivery = max(0.00, round($freeDeliveryThreshold - $subtotal, 2));
        $freeDeliveryProgressPercentage = min(100, round(($subtotal / $freeDeliveryThreshold) * 100, 1));
        
        // Estimated sales tax (6.25% - 8.25%)
        $taxRate = 0.063; // ~6.3% sales tax
        $estimatedTax = round($subtotal * $taxRate, 2);
        
        $total = round($subtotal + $deliveryFee + $estimatedTax, 2);

        return [
            'items_count' => $totalItemsCount,
            'items' => $formattedItems,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'delivery_fee' => number_format($deliveryFee, 2, '.', ''),
            'estimated_tax' => number_format($estimatedTax, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
            'free_delivery_threshold' => number_format($freeDeliveryThreshold, 2, '.', ''),
            'amount_needed_for_free_delivery' => number_format($amountNeededForFreeDelivery, 2, '.', ''),
            'free_delivery_progress_percentage' => $freeDeliveryProgressPercentage,
            'is_eligible_for_free_delivery' => $subtotal >= $freeDeliveryThreshold
        ];
    }
}
