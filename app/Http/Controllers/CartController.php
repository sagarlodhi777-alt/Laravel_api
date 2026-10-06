<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartController extends Controller
{
    /**
     * Helper to resolve the active Cart for an authenticated user or guest session.
     */
    protected function getOrCreateCart(Request $request): Cart
    {
        $user = $request->user('sanctum');
        $sessionId = $request->header('X-Session-ID') ?? $request->input('session_id') ?? $request->cookie('cart_session_id');

        if ($user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
            
            // If user had a guest cart with session ID, merge items into user cart
            if ($sessionId) {
                $guestCart = Cart::where('session_id', $sessionId)->whereNull('user_id')->first();
                if ($guestCart) {
                    foreach ($guestCart->items as $guestItem) {
                        $existingItem = CartItem::where('cart_id', $cart->id)
                            ->where('product_id', $guestItem->product_id)
                            ->first();

                        if ($existingItem) {
                            $existingItem->increment('quantity', $guestItem->quantity);
                        } else {
                            $guestItem->update(['cart_id' => $cart->id]);
                        }
                    }
                    $guestCart->delete();
                }
            }

            return $cart;
        }

        // Guest user cart
        if (! $sessionId) {
            $sessionId = (string) Str::uuid();
        }

        return Cart::firstOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => null]
        );
    }

    /**
     * Get the active cart and calculated summary.
     */
    public function getCart(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        $cart->load('items.product');

        return response()->json([
            'session_id' => $cart->session_id,
            'cart' => $cart->summary
        ], 200);
    }

    /**
     * Add an item to cart or increment quantity.
     */
    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1'
        ]);

        $quantity = $validated['quantity'] ?? 1;
        $product = Product::findOrFail($validated['product_id']);

        if ($product->stock < $quantity) {
            return response()->json([
                'message' => "Insufficient stock. Only {$product->stock} available in store."
            ], 400);
        }

        $cart = $this->getOrCreateCart($request);

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $quantity;
            if ($product->stock < $newQuantity) {
                return response()->json([
                    'message' => "Cannot add {$quantity} more. Maximum available stock is {$product->stock}."
                ], 400);
            }
            $cartItem->update(['quantity' => $newQuantity]);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $quantity
            ]);
        }

        $cart->load('items.product');

        return response()->json([
            'message' => "Added {$product->name} to cart",
            'session_id' => $cart->session_id,
            'cart' => $cart->summary
        ], 200);
    }

    /**
     * Update item quantity (or remove if quantity is 0).
     */
    public function updateItem(Request $request, int $itemId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0'
        ]);

        $cart = $this->getOrCreateCart($request);
        $cartItem = CartItem::where('cart_id', $cart->id)->where('id', $itemId)->firstOrFail();

        if ($validated['quantity'] == 0) {
            $cartItem->delete();
            $message = 'Item removed from cart';
        } else {
            $product = $cartItem->product;
            if ($product && $product->stock < $validated['quantity']) {
                return response()->json([
                    'message' => "Cannot set quantity to {$validated['quantity']}. Only {$product->stock} available."
                ], 400);
            }

            $cartItem->update(['quantity' => $validated['quantity']]);
            $message = 'Cart item quantity updated';
        }

        $cart->load('items.product');

        return response()->json([
            'message' => $message,
            'session_id' => $cart->session_id,
            'cart' => $cart->summary
        ], 200);
    }

    /**
     * Remove a single item from cart.
     */
    public function removeItem(Request $request, int $itemId)
    {
        $cart = $this->getOrCreateCart($request);
        $cartItem = CartItem::where('cart_id', $cart->id)->where('id', $itemId)->firstOrFail();
        $cartItem->delete();

        $cart->load('items.product');

        return response()->json([
            'message' => 'Item removed from cart',
            'session_id' => $cart->session_id,
            'cart' => $cart->summary
        ], 200);
    }

    /**
     * Clear all items in active cart.
     */
    public function clearCart(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        $cart->items()->delete();

        return response()->json([
            'message' => 'Cart cleared',
            'session_id' => $cart->session_id,
            'cart' => $cart->summary
        ], 200);
    }

    /**
     * Preview checkout financial calculations dynamically (e.g. before submitting final checkout).
     */
    public function previewCheckout(Request $request)
    {
        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'order_type' => 'nullable|in:delivery,curbside_pickup,in_store_pickup',
            'tip_amount' => 'nullable|numeric|min:0',
            'coupon_code' => 'nullable|string'
        ]);

        $subtotal = 0.00;
        $itemsBreakdown = [];

        // If items are provided in body, calculate for those items; otherwise use active Cart
        if (! empty($validated['items'])) {
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $unitPrice = (float)($product->sale_price ?? $product->price);
                $lineTotal = round($unitPrice * $item['quantity'], 2);
                $subtotal += $lineTotal;

                $itemsBreakdown[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'unit_size' => $product->unit_size,
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'quantity' => $item['quantity'],
                    'line_total' => number_format($lineTotal, 2, '.', '')
                ];
            }
        } else {
            $cart = $this->getOrCreateCart($request);
            $cart->load('items.product');
            $summary = $cart->summary;
            $subtotal = (float)$summary['subtotal'];
            $itemsBreakdown = $summary['items'];
        }

        $orderType = $validated['order_type'] ?? 'delivery';
        $freeDeliveryThreshold = 35.00;
        $deliveryFee = ($orderType === 'delivery') ? ($subtotal >= $freeDeliveryThreshold || $subtotal == 0 ? 0.00 : 2.99) : 0.00;
        $taxRate = 0.063; // Estimated ~6.3%
        $estimatedTax = round($subtotal * $taxRate, 2);
        $tipAmount = isset($validated['tip_amount']) ? round((float)$validated['tip_amount'], 2) : 0.00;
        
        // Optional coupon discount
        $discountAmount = 0.00;
        if (! empty($validated['coupon_code']) && strtoupper($validated['coupon_code']) === 'FRESH10') {
            $discountAmount = round($subtotal * 0.10, 2); // 10% off
        }

        $total = max(0.00, round($subtotal + $estimatedTax + $deliveryFee + $tipAmount - $discountAmount, 2));

        return response()->json([
            'items' => $itemsBreakdown,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'delivery_fee' => number_format($deliveryFee, 2, '.', ''),
            'estimated_tax' => number_format($estimatedTax, 2, '.', ''),
            'tip_amount' => number_format($tipAmount, 2, '.', ''),
            'discount_amount' => number_format($discountAmount, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
            'is_free_delivery' => $deliveryFee == 0.00 && $orderType === 'delivery',
            'amount_needed_for_free_delivery' => number_format(max(0.00, $freeDeliveryThreshold - $subtotal), 2, '.', ''),
        ], 200);
    }
}
