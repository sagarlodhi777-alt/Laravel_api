<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Display authenticated user's order history.
     */
    public function index(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->with(['items.product', 'delivery'])
            ->latest()
            ->paginate($request->query('per_page', 15));
            ->with('items.product')
            ->latest()
            ->get();

        return response()->json($orders, 200);
    }

    /**
     * Place a new supermarket order / Checkout.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_type' => 'nullable|in:delivery,curbside_pickup,in_store_pickup',
            'delivery_time_slot' => 'nullable|string|max:50',
            'delivery_time_slot_label' => 'nullable|string|max:100',
            'payment_method' => 'required|in:credit_card,card,debit_card,apple_pay,google_pay,ebt_snap,cash_on_delivery',
            
            // Items: Can either be passed directly or taken from active Cart
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            
            // US Shipping Address (Required if order_type is delivery)
            'shipping_name' => 'required_if:order_type,delivery|nullable|string|max:255',
            'shipping_phone' => 'required_if:order_type,delivery|nullable|string|max:20',
            'shipping_address_line1' => 'required_if:order_type,delivery|nullable|string|max:255',
            'shipping_address_line2' => 'nullable|string|max:255',
            'shipping_city' => 'required_if:order_type,delivery|nullable|string|max:100',
            'shipping_state' => 'nullable|string|size:2',
            'shipping_zip_code' => 'nullable|string|max:10',
            'delivery_instructions' => 'nullable|string',
            
            // Financials
            'tip_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0'
        ]);

        $orderType = $validated['order_type'] ?? 'delivery';
        $paymentMethod = $validated['payment_method'] === 'card' ? 'credit_card' : $validated['payment_method'];

        DB::beginTransaction();
        try {
            $itemsToProcess = [];

            // 1. Determine items (from body or active cart)
            if (! empty($validated['items'])) {
                $itemsToProcess = $validated['items'];
            } else {
                $userCart = Cart::where('user_id', $request->user()->id)->first();
                if (! $userCart || $userCart->items->isEmpty()) {
                    throw new \Exception('Your cart is empty. Please add items to checkout.');
                }
                foreach ($userCart->items as $cartItem) {
                    $itemsToProcess[] = [
                        'product_id' => $cartItem->product_id,
                        'quantity' => $cartItem->quantity
                    ];
                }
            }

            $subtotal = 0.00;
            $itemsData = [];

            // 2. Verify stock and calculate totals
            foreach ($itemsToProcess as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for item: {$product->name} (Available: {$product->stock})");
                }

                $unitPrice = (float)($product->sale_price ?? $product->price);
                $lineTotal = round($unitPrice * $item['quantity'], 2);
                $subtotal += $lineTotal;

                $itemsData[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal
                ];
            }

            // 3. Supermarket Financial Breakdown
            $freeDeliveryThreshold = 35.00;
            $deliveryFee = ($orderType === 'delivery') ? ($subtotal >= $freeDeliveryThreshold ? 0.00 : 2.99) : 0.00;
            $taxRate = 0.063; // ~6.3% sales tax
            $taxAmount = round($subtotal * $taxRate, 2);
            $tipAmount = isset($validated['tip_amount']) ? round((float)$validated['tip_amount'], 2) : 0.00;
            $discountAmount = isset($validated['discount_amount']) ? round((float)$validated['discount_amount'], 2) : 0.00;
            $totalPrice = max(0.00, round($subtotal + $taxAmount + $deliveryFee + $tipAmount - $discountAmount, 2));

            // 4. Generate unique Order Number
            $orderNumber = 'US-ORD-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'delivery_fee' => $deliveryFee,
                'tip_amount' => $tipAmount,
                'discount_amount' => $discountAmount,
                'total_price' => $totalPrice,
                'order_type' => $orderType,
                'delivery_time_slot' => $validated['delivery_time_slot'] ?? 'asap',
                'delivery_time_slot_label' => $validated['delivery_time_slot_label'] ?? 'ASAP (In ~45 min)',
                'order_status' => 'pending',
                'payment_status' => 'paid',
                'payment_method' => $paymentMethod,
                'shipping_name' => $validated['shipping_name'] ?? $request->user()->name,
                'shipping_phone' => $validated['shipping_phone'] ?? $request->user()->phone,
                'shipping_address_line1' => $validated['shipping_address_line1'] ?? null,
                'shipping_address_line2' => $validated['shipping_address_line2'] ?? null,
                'shipping_city' => $validated['shipping_city'] ?? null,
                'shipping_state' => isset($validated['shipping_state']) ? strtoupper($validated['shipping_state']) : null,
                'shipping_zip_code' => $validated['shipping_zip_code'] ?? null,
                'delivery_instructions' => $validated['delivery_instructions'] ?? null,
                'placed_at' => now(),
            ]);

            // 5. Save items & decrement stock
            foreach ($itemsData as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price']
                ]);

                $item['product']->decrement('stock', $item['quantity']);
            }

            // 6. Initialize Delivery record if order is for delivery
            if ($order->order_type === 'delivery') {
                $trackingNumber = 'TRK-US-' . strtoupper(Str::random(8));
                Delivery::create([
                    'order_id' => $order->id,
                    'tracking_number' => $trackingNumber,
                    'delivery_time_slot' => $order->delivery_time_slot,
                    'delivery_time_slot_label' => $order->delivery_time_slot_label,
                    'delivery_status' => 'pending',
                    'estimated_delivery_time' => now()->addMinutes(45),
                    'delivery_notes' => $order->delivery_instructions
                ]);
            }

            // 7. Clear user's active Cart after successful order placement
            $userCart = Cart::where('user_id', $request->user()->id)->first();
            if ($userCart) {
                $userCart->items()->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Order placed successfully',
                'data' => $order->load(['items.product', 'delivery'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $order = DB::transaction(function () use ($request, $validated) {
                $totalPrice = 0;
                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'total_price' => 0,
                    'status' => 'pending',
                ]);

                $orderItems = [];

                foreach ($validated['items'] as $item) {
                    $product = Product::whereKey($item['product_id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    $quantity = (int) $item['quantity'];

                    if ($product->stock < $quantity) {
                        throw new \Exception("Not enough stock for {$product->name}");
                    }

                    $itemPrice = $product->price;
                    $totalPrice += $itemPrice * $quantity;

                    $orderItems[] = [
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => $itemPrice,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $product->stock -= $quantity;
                    $product->save();
                }

                if (!empty($orderItems)) {
                    $order->items()->createMany($orderItems);
                }

                $order->update(['total_price' => $totalPrice]);

                return $order->load('items.product');
            });

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => $order,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Unable to place order', [
                'user_id' => $request->user()->id,
                'exception' => $e,
            ]);

            return response()->json(['error' => 'Unable to place order. ' . $e->getMessage()], 400);
        }
    }

    /**
     * Get details of a single order.
     */
    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized access to this order.'], 403);
        }

        return response()->json($order->load(['items.product', 'delivery']), 200);
    }

    /**
     * Customer live tracking endpoint for their order dispatch.
     */
    public function trackDelivery(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $order->delivery) {
            return response()->json(['message' => 'No delivery tracking associated with this order (e.g. pickup order).'], 404);
        }

        return response()->json([
            'order_number' => $order->order_number,
            'delivery' => $order->delivery,
            'destination_address' => [
                'line1' => $order->shipping_address_line1,
                'line2' => $order->shipping_address_line2,
                'city' => $order->shipping_city,
                'state' => $order->shipping_state,
                'zip_code' => $order->shipping_zip_code
            ]
        ], 200);
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ((int) $order->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order->load('items.product'), 200);
    }

    // Admin endpoints
    public function adminIndex(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $orders = Order::with('user', 'items.product')->latest()->get();

        return response()->json($orders, 200);
    }

    /**
     * Admin: List all supermarket orders with filtering.
     */
    public function adminIndex(Request $request)
    {
        $query = Order::with(['user', 'items.product', 'delivery']);

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->query('order_status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->query('payment_status'));
        }

        if ($request->filled('order_type')) {
            $query->where('order_type', $request->query('order_type'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('shipping_name', 'like', "%{$search}%")
                  ->orWhere('shipping_phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate($request->query('per_page', 20)), 200);
    }

    /**
     * Admin: Update order status.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $user = $request->user();

        if (! $user || ! $user->is_admin) {
            Log::error('Forbidden attempt to update order status', [
                'user_id' => $user?->id,
                'order_id' => $order->id,
                'requested_status' => $request->input('status'),
            ]);

            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'order_status' => 'required|in:pending,confirmed,processing,ready_for_pickup,out_for_delivery,delivered,cancelled',
            'payment_status' => 'sometimes|in:pending,paid,failed,refunded'
        ]);

        if (isset($validated['order_status']) && $validated['order_status'] === 'delivered') {
            $validated['delivered_at'] = now();
        }

        $order->update($validated);

        return response()->json([
            'message' => 'Order updated successfully',
            'data' => $order->load(['items.product', 'delivery'])
        ], 200);
            'status' => ['required', 'string', 'in:pending,processing,completed,cancelled'],
        ]);

        $currentStatus = $order->status;
        $allowedTransitions = [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];

        if ($currentStatus === $validated['status']) {
            return response()->json([
                'message' => 'Order status is already set to ' . $validated['status'],
                'order' => $order->fresh(),
            ], 200);
        }

        if (isset($allowedTransitions[$currentStatus]) && ! in_array($validated['status'], $allowedTransitions[$currentStatus], true)) {
            Log::error('Invalid order status transition attempted', [
                'user_id' => $user->id,
                'order_id' => $order->id,
                'current_status' => $currentStatus,
                'requested_status' => $validated['status'],
            ]);

            return response()->json([
                'message' => 'Invalid status transition from ' . $currentStatus . ' to ' . $validated['status'],
            ], 422);
        }

        $order->update(['status' => $validated['status']]);

        return response()->json(['message' => 'Order status updated', 'order' => $order->fresh()], 200);
    }
}
