<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Delivery;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OlivaApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected Category $category;
    protected SubCategory $subCategory;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+12025550143',
            'password' => Hash::make('password123'),
            'role' => 'user'
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@oliva.com',
            'phone' => '+12025550199',
            'password' => Hash::make('adminpassword123'),
            'role' => 'admin'
        ]);

        $this->category = Category::create([
            'name' => 'Produce',
            'description' => 'Fresh fruits and vegetables'
        ]);

        $this->subCategory = SubCategory::create([
            'category_id' => $this->category->id,
            'name' => 'Fresh Fruits',
            'slug' => 'fresh-fruits',
            'description' => 'Organic and fresh fruits'
        ]);

        $this->product = Product::create([
            'subcategory_id' => $this->subCategory->id,
            'sku' => 'US-PROD-101',
            'name' => 'Organic Honeycrisp Apples',
            'brand' => 'Oliva Farms',
            'unit_size' => '2 lbs bag',
            'price' => 4.99,
            'sale_price' => 3.99,
            'stock' => 50,
            'is_organic' => true,
            'status' => 'active'
        ]);
    }

    /* -------------------------------------------------------------
     * 1. AUTHENTICATION ENDPOINTS
     * ------------------------------------------------------------- */

    public function test_auth_registration()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '+12025550188',
            'password' => 'securepass123',
            'password_confirmation' => 'securepass123'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'user', 'token']);
    }

    public function test_auth_login_and_logout()
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password123'
        ]);

        $loginRes->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'token']);

        $token = $loginRes->json('token');

        $logoutRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $logoutRes->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);
    }

    /* -------------------------------------------------------------
     * 2. SHOP BROWSING ENDPOINTS
     * ------------------------------------------------------------- */

    public function test_get_categories_and_single_category()
    {
        $listRes = $this->getJson('/api/v1/categories');
        $listRes->assertStatus(200)
            ->assertJsonFragment(['name' => 'Produce']);

        $singleRes = $this->getJson('/api/v1/categories/' . $this->category->id);
        $singleRes->assertStatus(200)
            ->assertJsonFragment(['name' => 'Produce']);
    }

    public function test_get_subcategories_and_single_subcategory()
    {
        $listRes = $this->getJson('/api/v1/subcategories');
        $listRes->assertStatus(200)
            ->assertJsonStructure(['data']);

        $singleRes = $this->getJson('/api/v1/subcategories/' . $this->subCategory->id);
        $singleRes->assertStatus(200)
            ->assertJsonFragment(['name' => 'Fresh Fruits']);
    }

    public function test_get_products_with_filters()
    {
        $listRes = $this->getJson('/api/v1/products?is_organic=1&search=Honeycrisp');
        $listRes->assertStatus(200)
            ->assertJsonFragment(['name' => 'Organic Honeycrisp Apples']);

        $singleRes = $this->getJson('/api/v1/products/' . $this->product->id);
        $singleRes->assertStatus(200)
            ->assertJsonFragment(['sku' => 'US-PROD-101']);
    }

    /* -------------------------------------------------------------
     * 3. CART ENDPOINTS
     * ------------------------------------------------------------- */

    public function test_cart_operations_for_guest_and_user()
    {
        $sessionHeader = ['X-Session-ID' => 'guest-test-session-123'];

        // 1. Add item to cart
        $addRes = $this->withHeaders($sessionHeader)->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 2
        ]);
        $addRes->assertStatus(200)
            ->assertJsonStructure(['session_id', 'cart']);

        // 2. Get cart
        $getRes = $this->withHeaders($sessionHeader)->getJson('/api/v1/cart');
        $getRes->assertStatus(200);
        $items = $getRes->json('cart.items');
        $this->assertNotEmpty($items);
        $itemId = $items[0]['id'];

        // 3. Update quantity
        $updateRes = $this->withHeaders($sessionHeader)->putJson('/api/v1/cart/items/' . $itemId, [
            'quantity' => 3
        ]);
        $updateRes->assertStatus(200);

        // 4. Preview Checkout with Promo Code
        $previewRes = $this->withHeaders($sessionHeader)->postJson('/api/v1/cart/preview', [
            'order_type' => 'delivery',
            'coupon_code' => 'FRESH10'
        ]);
        $previewRes->assertStatus(200)
            ->assertJsonStructure(['items', 'subtotal', 'delivery_fee', 'estimated_tax', 'total']);

        // 5. Remove item
        $deleteRes = $this->withHeaders($sessionHeader)->deleteJson('/api/v1/cart/items/' . $itemId);
        $deleteRes->assertStatus(200);

        // 6. Clear cart
        $clearRes = $this->withHeaders($sessionHeader)->deleteJson('/api/v1/cart/clear');
        $clearRes->assertStatus(200);
    }

    /* -------------------------------------------------------------
     * 4. DELIVERY SLOTS & PUBLIC TRACKING
     * ------------------------------------------------------------- */

    public function test_delivery_slots_endpoint()
    {
        $res = $this->getJson('/api/v1/delivery-slots');
        $res->assertStatus(200)
            ->assertJsonStructure(['slots']);
    }

    /* -------------------------------------------------------------
     * 5. CUSTOMER ORDER PLACEMENT & TRACKING
     * ------------------------------------------------------------- */

    public function test_user_order_placement_and_tracking()
    {
        $orderData = [
            'order_type' => 'delivery',
            'payment_method' => 'credit_card',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2
                ]
            ],
            'shipping_name' => 'John Doe',
            'shipping_phone' => '+12025550143',
            'shipping_address_line1' => '123 Market St',
            'shipping_city' => 'San Francisco',
            'shipping_state' => 'CA',
            'shipping_zip_code' => '94103',
            'tip_amount' => 5.00
        ];

        $checkoutRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/orders', $orderData);

        $checkoutRes->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['id', 'order_number', 'total_price', 'delivery']]);

        $orderId = $checkoutRes->json('data.id');
        $trackingNumber = $checkoutRes->json('data.delivery.tracking_number');

        // Order history
        $listOrdersRes = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/user/orders');
        $listOrdersRes->assertStatus(200);

        // Single order details
        $showOrderRes = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/user/orders/' . $orderId);
        $showOrderRes->assertStatus(200);

        // Order delivery live tracking
        $trackOrderRes = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/user/orders/{$orderId}/track-delivery");
        $trackOrderRes->assertStatus(200);

        // Public tracking endpoint by tracking number
        $publicTrackRes = $this->getJson("/api/v1/tracking/{$trackingNumber}");
        $publicTrackRes->assertStatus(200)
            ->assertJsonFragment(['tracking_number' => $trackingNumber]);
    }

    public function test_user_profile()
    {
        $profileRes = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/user/profile');
        $profileRes->assertStatus(200)
            ->assertJsonFragment(['email' => 'john@example.com']);
    }

    /* -------------------------------------------------------------
     * 6. ADMIN DASHBOARD & MANAGEMENT ENDPOINTS
     * ------------------------------------------------------------- */

    public function test_admin_dashboard_and_users()
    {
        $dashRes = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/dashboard');
        $dashRes->assertStatus(200);

        $usersRes = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/users');
        $usersRes->assertStatus(200)
            ->assertJsonStructure(['status', 'users', 'count']);
    }

    public function test_admin_category_management()
    {
        // Create
        $createRes = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/categories', [
            'name' => 'Bakery & Bread',
            'description' => 'Fresh baked artisan breads and pastries'
        ]);
        $createRes->assertStatus(201);
        $catId = $createRes->json('data.id');

        // Update
        $updateRes = $this->actingAs($this->admin, 'sanctum')->putJson("/api/v1/admin/categories/{$catId}", [
            'name' => 'Artisan Bakery'
        ]);
        $updateRes->assertStatus(200);

        // Delete
        $deleteRes = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/v1/admin/categories/{$catId}");
        $deleteRes->assertStatus(200);
    }

    public function test_admin_subcategory_management()
    {
        // Create
        $createRes = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/subcategories', [
            'category_id' => $this->category->id,
            'name' => 'Citrus Fruits',
            'description' => 'Oranges, lemons, and limes'
        ]);
        $createRes->assertStatus(201);
        $subId = $createRes->json('data.id');

        // Update
        $updateRes = $this->actingAs($this->admin, 'sanctum')->putJson("/api/v1/admin/subcategories/{$subId}", [
            'name' => 'Organic Citrus Fruits'
        ]);
        $updateRes->assertStatus(200);

        // Delete
        $deleteRes = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/v1/admin/subcategories/{$subId}");
        $deleteRes->assertStatus(200);
    }

    public function test_admin_product_management()
    {
        // Create
        $createRes = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/products', [
            'subcategory_id' => $this->subCategory->id,
            'sku' => 'US-BERRY-501',
            'name' => 'Organic Fresh Blueberries',
            'brand' => 'Driscoll\'s',
            'unit_size' => '1 pt',
            'price' => 3.49,
            'stock' => 100,
            'is_organic' => true
        ]);
        $createRes->assertStatus(201);
        $prodId = $createRes->json('data.id');

        // Update
        $updateRes = $this->actingAs($this->admin, 'sanctum')->putJson("/api/v1/admin/products/{$prodId}", [
            'price' => 2.99,
            'sale_price' => 2.49
        ]);
        $updateRes->assertStatus(200);

        // Delete
        $deleteRes = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/v1/admin/products/{$prodId}");
        $deleteRes->assertStatus(200);
    }

    public function test_admin_order_and_delivery_management()
    {
        // Create order & delivery
        $order = Order::create([
            'order_number' => 'US-ORD-TEST-001',
            'user_id' => $this->user->id,
            'subtotal' => 30.00,
            'tax_amount' => 1.89,
            'delivery_fee' => 2.99,
            'total_price' => 34.88,
            'order_type' => 'delivery',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'credit_card',
            'shipping_name' => 'John Doe',
            'shipping_phone' => '+12025550143',
            'shipping_address_line1' => '123 Market St',
            'shipping_city' => 'San Francisco',
            'shipping_state' => 'CA',
            'shipping_zip_code' => '94103'
        ]);

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-TEST-9999',
            'delivery_status' => 'pending'
        ]);

        // 1. Admin order listing
        $adminOrdersRes = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/orders');
        $adminOrdersRes->assertStatus(200);

        // 2. Admin update order status
        $updateOrderRes = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'order_status' => 'confirmed'
            ]);
        $updateOrderRes->assertStatus(200);

        // 3. Admin delivery listing
        $adminDeliveriesRes = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/deliveries');
        $adminDeliveriesRes->assertStatus(200);

        // 4. Admin assign driver & dispatch
        $assignDriverRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/deliveries/{$delivery->id}/assign", [
                'driver_name' => 'Marcus Vance',
                'driver_phone' => '+14155550199',
                'vehicle_info' => 'Silver Toyota Prius (CA 7XYZ89)'
            ]);
        $assignDriverRes->assertStatus(200)
            ->assertJsonFragment(['driver_name' => 'Marcus Vance']);

        // 5. Admin update delivery status
        $statusRes = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/deliveries/{$delivery->id}/status", [
                'delivery_status' => 'out_for_delivery'
            ]);
        $statusRes->assertStatus(200);

        // 6. Admin update live GPS coordinates
        $locationRes = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/deliveries/{$delivery->id}/location", [
                'latitude' => 37.774929,
                'longitude' => -122.419416
            ]);
        $locationRes->assertStatus(200)
            ->assertJsonFragment(['latitude' => 37.774929]);
    }
}
