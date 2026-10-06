<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'tracking_number',
        'driver_name',
        'driver_phone',
        'vehicle_info',
        'delivery_time_slot',
        'delivery_time_slot_label',
        'delivery_status',
        'current_latitude',
        'current_longitude',
        'estimated_delivery_time',
        'dispatched_at',
        'delivered_at',
        'delivery_notes',
        'proof_of_delivery_image'
    ];

    protected function casts(): array
    {
        return [
            'current_latitude' => 'decimal:7',
            'current_longitude' => 'decimal:7',
            'estimated_delivery_time' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
