<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    /**
     * Public tracking by Tracking Number.
     */
    public function trackByNumber(string $trackingNumber)
    {
        $delivery = Delivery::with(['order.items.product'])
            ->where('tracking_number', $trackingNumber)
            ->firstOrFail();

        return response()->json([
            'tracking_number' => $delivery->tracking_number,
            'delivery_status' => $delivery->delivery_status,
            'driver_name' => $delivery->driver_name,
            'driver_phone' => $delivery->driver_phone,
            'vehicle_info' => $delivery->vehicle_info,
            'current_location' => [
                'latitude' => $delivery->current_latitude,
                'longitude' => $delivery->current_longitude,
            ],
            'estimated_delivery_time' => $delivery->estimated_delivery_time,
            'dispatched_at' => $delivery->dispatched_at,
            'delivered_at' => $delivery->delivered_at,
            'order_summary' => [
                'order_number' => $delivery->order->order_number,
                'items_count' => $delivery->order->items->count(),
                'shipping_address' => [
                    'line1' => $delivery->order->shipping_address_line1,
                    'line2' => $delivery->order->shipping_address_line2,
                    'city' => $delivery->order->shipping_city,
                    'state' => $delivery->order->shipping_state,
                    'zip_code' => $delivery->order->shipping_zip_code,
                ]
            ]
        ], 200);
    }

    /**
     * Admin: Delivery & Dispatch dashboard.
     */
    public function adminIndex(Request $request)
    {
        $query = Delivery::with(['order.user']);

        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->query('delivery_status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                  ->orWhere('driver_name', 'like', "%{$search}%")
                  ->orWhere('driver_phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate($request->query('per_page', 20)), 200);
    }

    /**
     * Admin: Assign Driver & Dispatch Order.
     */
    public function assignDriver(Request $request, Delivery $delivery)
    {
        $validated = $request->validate([
            'driver_name' => 'required|string|max:255',
            'driver_phone' => 'required|string|max:20',
            'vehicle_info' => 'nullable|string|max:255',
            'estimated_delivery_time' => 'nullable|date',
            'delivery_notes' => 'nullable|string'
        ]);

        $validated['delivery_status'] = 'assigned';
        $validated['dispatched_at'] = now();

        $delivery->update($validated);

        // Update corresponding order status as out for delivery/processing
        $delivery->order->update(['order_status' => 'processing']);

        return response()->json([
            'message' => 'Delivery dispatched and driver assigned successfully',
            'data' => $delivery->load('order')
        ], 200);
    }

    /**
     * Admin / Driver: Update Delivery Status.
     */
    public function updateStatus(Request $request, Delivery $delivery)
    {
        $validated = $request->validate([
            'delivery_status' => 'required|in:pending,assigned,picked_up,in_transit,out_for_delivery,delivered,failed,returned',
            'delivery_notes' => 'nullable|string',
            'proof_of_delivery_image' => 'nullable|string'
        ]);

        if ($validated['delivery_status'] === 'delivered') {
            $validated['delivered_at'] = now();
            $delivery->order->update([
                'order_status' => 'delivered',
                'delivered_at' => now()
            ]);
        } elseif ($validated['delivery_status'] === 'out_for_delivery') {
            $delivery->order->update(['order_status' => 'out_for_delivery']);
        }

        $delivery->update($validated);

        return response()->json([
            'message' => 'Delivery status updated',
            'data' => $delivery->load('order')
        ], 200);
    }

    /**
     * Admin / Driver: Update Live GPS Coordinates for dispatch tracking.
     */
    public function updateLocation(Request $request, Delivery $delivery)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180'
        ]);

        $delivery->update([
            'current_latitude' => $validated['latitude'],
            'current_longitude' => $validated['longitude']
        ]);

        return response()->json([
            'message' => 'Live coordinates updated successfully',
            'current_location' => [
                'latitude' => $delivery->current_latitude,
                'longitude' => $delivery->current_longitude
            ]
        ], 200);
    }
}
