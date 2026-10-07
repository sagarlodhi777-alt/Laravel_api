# Olivia Supermarket — REST API Documentation

**Base URL:** `http://127.0.0.1:8000/api/v1`

---

## 🛒 Overview & Key Features
This API powers the **Olivia Supermarket** web and mobile applications (Catalog, Cart, Checkout, Order Tracking, and Admin Portal):
- **Cart Management**: Real-time stock checks, guest cart sessions via `X-Session-ID`, automatic guest-to-user cart merging upon login, $35 free delivery progress calculation, taxes, and coupon discounts (`FRESH10`).
- **Multi-Step Checkout**: Delivery Address, Delivery Time Slot Selection (`asap`, `today_18_19`, etc.), Payment method selection (`credit_card`, `cash_on_delivery`, `apple_pay`, `google_pay`, `ebt_snap`), and automatic stock decrement.
- **Dispatch & Live Delivery Tracking**: Automatic tracking number generation (`TRK-US-...`), real-time driver assignment, GPS coordinate updates, delivery proof photos, and public/authenticated live tracking.
- **Admin Management**: Full CRUD for Categories, Subcategories, Products, Order status workflows, and Delivery dispatch operations.

---

## 🔐 Authentication & Headers

All authenticated routes require a Sanctum Bearer Token:
```http
Authorization: Bearer <your_access_token>
```

Guest shoppers can interact with Cart endpoints by providing a Session ID:
```http
X-Session-ID: <uuid_or_custom_guest_id>
```

---

## 1. Authentication Endpoints

### Register
- **Endpoint:** `POST /api/v1/auth/register`
- **Request Body:**
  ```json
  {
      "name": "Michael O.",
      "email": "michael@example.com",
      "phone": "+15125550142",
      "password": "password123",
      "password_confirmation": "password123"
  }
  ```
- **Response (201 Created):**
  ```json
  {
      "message": "User registered successfully",
      "user": {
          "id": 1,
          "name": "Michael O.",
          "email": "michael@example.com",
          "phone": "+15125550142",
          "role": "user"
      },
      "token": "1|sanctum_token_string..."
  }
  ```

### Login
- **Endpoint:** `POST /api/v1/auth/login`
- **Request Body:**
  ```json
  {
      "email": "michael@example.com",
      "password": "password123"
  }
  ```
- **Response (200 OK):**
  ```json
  {
      "message": "Login successful",
      "user": {
          "id": 1,
          "name": "Michael O.",
          "email": "michael@example.com",
          "role": "user"
      },
      "token": "2|sanctum_token_string..."
  }
  ```

### Social Login (Google / Facebook)
- **Endpoint:** `POST /api/v1/auth/social-login`
- **Request Body:**
  ```json
  {
      "provider": "google",
      "token": "oauth_token_from_provider"
  }
  ```

### Forgot Password
- **Endpoint:** `POST /api/v1/auth/forgot-password`
- **Request Body:**
  ```json
  {
      "email": "michael@example.com"
  }
  ```

### Reset Password
- **Endpoint:** `POST /api/v1/auth/reset-password`
- **Request Body:**
  ```json
  {
      "token": "reset_token_received_in_email",
      "email": "michael@example.com",
      "password": "newpassword123",
      "password_confirmation": "newpassword123"
  }
  ```

### Logout (Requires Auth)
- **Endpoint:** `POST /api/v1/auth/logout`
- **Headers:** `Authorization: Bearer <token>`
- **Response (200 OK):**
  ```json
  {
      "message": "Logged out successfully"
  }
  ```

---

## 2. Storefront Catalog Browsing (Public)

### Categories
- **List Categories:** `GET /api/v1/categories` (Includes nested subcategories)
- **Single Category:** `GET /api/v1/categories/{category_id}`

### Subcategories
- **List Subcategories:** `GET /api/v1/subcategories` (Includes parent category)
- **Single Subcategory:** `GET /api/v1/subcategories/{subcategory_id}`

### Products
- **List Products:** `GET /api/v1/products`
  - **Supported Query Filters:**
    - `search=apples` (Searches name, brand, SKU, UPC, slug, description)
    - `category_id=1`
    - `subcategory_id=2`
    - `brand=Chobani`
    - `tag=Organic`
    - `is_organic=1` (Boolean filter)
    - `is_gluten_free=1`
    - `is_perishable=1`
    - `local=1`
    - `min_price=1.99`
    - `max_price=10.00`
    - `status=active` (or `inactive`, `out_of_stock`)
    - `sort_by=price_asc` (`price_desc`, `name_asc`, `name_desc`, `latest`)
    - `per_page=20` (Pagination limit)
    - `all=true` (Returns full unpaginated list)
- **Single Product:** `GET /api/v1/products/{product_id}`

---

## 3. Cart APIs (Public & Authenticated)

Works for authenticated users (via Bearer token) and guest shoppers (via `X-Session-ID` header or `session_id` param).

### Get Active Cart & Summary
- **Endpoint:** `GET /api/v1/cart`
- **Response (200 OK):**
  ```json
  {
      "session_id": "7a8f9c12-3456-4789-abcd-1234567890ef",
      "cart": {
          "items_count": 3,
          "items": [
              {
                  "id": 101,
                  "product_id": 1,
                  "name": "Organic Honeycrisp Apples",
                  "brand": "Oliva Farms",
                  "unit_size": "2 lbs bag",
                  "image": "https://...",
                  "unit_price": "3.99",
                  "quantity": 2,
                  "line_total": "7.98",
                  "in_stock": true,
                  "available_stock": 48
              }
          ],
          "subtotal": "7.98",
          "delivery_fee": "2.99",
          "estimated_tax": "0.50",
          "total": "11.47",
          "free_delivery_threshold": "35.00",
          "amount_needed_for_free_delivery": "27.02",
          "free_delivery_progress_percentage": 22.8,
          "is_eligible_for_free_delivery": false
      }
  }
  ```

### Add Item to Cart
- **Endpoint:** `POST /api/v1/cart/items`
- **Request Body:**
  ```json
  {
      "product_id": 1,
      "quantity": 2
  }
  ```

### Update Item Quantity
- **Endpoint:** `PUT /api/v1/cart/items/{cart_item_id}`
- **Request Body:**
  ```json
  {
      "quantity": 3
  }
  ```
  *(Note: Setting `quantity: 0` removes the item)*

### Remove Item from Cart
- **Endpoint:** `DELETE /api/v1/cart/items/{cart_item_id}`

### Clear Entire Cart
- **Endpoint:** `DELETE /api/v1/cart/clear`

### Preview Checkout Breakdown (Dynamic Calculations)
- **Endpoint:** `POST /api/v1/cart/preview`
- **Request Body (Optional overrides / coupon test):**
  ```json
  {
      "order_type": "delivery",
      "tip_amount": 3.00,
      "coupon_code": "FRESH10"
  }
  ```
- **Response (200 OK):**
  ```json
  {
      "items": [...],
      "subtotal": "38.00",
      "delivery_fee": "0.00",
      "estimated_tax": "2.39",
      "tip_amount": "3.00",
      "discount_amount": "3.80",
      "total": "39.59",
      "is_free_delivery": true,
      "amount_needed_for_free_delivery": "0.00"
  }
  ```

---

## 4. Delivery Slots & Public Tracking

### Available Delivery Windows
- **Endpoint:** `GET /api/v1/delivery-slots`
- **Response (200 OK):**
  ```json
  {
      "slots": [
          {
              "id": "asap",
              "title": "ASAP",
              "subtitle": "In about 45 min",
              "badge": "FASTEST",
              "is_default": true,
              "available": true
          },
          {
              "id": "today_18_19",
              "title": "6:00 – 7:00 pm",
              "subtitle": "Today",
              "badge": null,
              "is_default": false,
              "available": true
          }
      ]
  }
  ```

### Public Order Tracking (No Login Required)
- **Endpoint:** `GET /api/v1/tracking/{tracking_number}`
- **Response (200 OK):**
  ```json
  {
      "tracking_number": "TRK-US-A1B2C3D4",
      "delivery_status": "in_transit",
      "driver_name": "Marcus Vance",
      "driver_phone": "+14155550199",
      "vehicle_info": "Silver Toyota Prius (CA 7XYZ89)",
      "current_location": {
          "latitude": 37.774929,
          "longitude": -122.419416
      },
      "estimated_delivery_time": "2026-10-07T14:30:00.000000Z",
      "order_summary": {
          "order_number": "US-ORD-20261007-AB12CD",
          "items_count": 4,
          "shipping_address": {
              "line1": "123 Market St",
              "line2": "Apt 4B",
              "city": "San Francisco",
              "state": "CA",
              "zip_code": "94103"
          }
      }
  }
  ```

---

## 5. Customer Profile & Orders (Requires Auth)

### Customer Profile
- **Endpoint:** `GET /api/v1/user/profile`

### Place Order / Checkout
- **Endpoint:** `POST /api/v1/user/orders`
- **Request Body:**
  ```json
  {
      "order_type": "delivery",
      "delivery_time_slot": "asap",
      "delivery_time_slot_label": "ASAP (In ~45 min)",
      "payment_method": "credit_card",
      "items": [
          {
              "product_id": 1,
              "quantity": 2
          }
      ],
      "shipping_name": "John Doe",
      "shipping_phone": "+12025550143",
      "shipping_address_line1": "123 Market St",
      "shipping_address_line2": "Suite 500",
      "shipping_city": "San Francisco",
      "shipping_state": "CA",
      "shipping_zip_code": "94103",
      "delivery_instructions": "Leave at front desk",
      "tip_amount": 5.00
  }
  ```
  *(Note: If `items` array is omitted, the user's active Cart items will be used and the cart cleared automatically upon success)*
- **Response (201 Created):**
  ```json
  {
      "message": "Order placed successfully",
      "data": {
          "id": 1,
          "order_number": "US-ORD-20261007-XK92P1",
          "subtotal": "7.98",
          "tax_amount": "0.50",
          "delivery_fee": "2.99",
          "tip_amount": "5.00",
          "total_price": "16.47",
          "order_status": "pending",
          "payment_status": "paid",
          "delivery": {
              "tracking_number": "TRK-US-88219034",
              "delivery_status": "pending",
              "estimated_delivery_time": "2026-10-07T12:45:00.000000Z"
          }
      }
  }
  ```

### Customer Order History
- **Endpoint:** `GET /api/v1/user/orders` *(Paginated)*

### Single Order Details
- **Endpoint:** `GET /api/v1/user/orders/{order_id}`

### Customer Live Delivery Tracking
- **Endpoint:** `GET /api/v1/user/orders/{order_id}/track-delivery`

---

## 6. Admin Management APIs (Requires Admin Role)

All admin routes require `Authorization: Bearer <token>` where the user's role is `admin`.

### Dashboard & Users
- **Dashboard Overview:** `GET /api/v1/admin/dashboard`
- **User List:** `GET /api/v1/admin/users`

### Category Management
- **Create Category:** `POST /api/v1/admin/categories`
  - Body: `{"name": "Bakery", "description": "Fresh artisan breads", "image": "https://..."}`
- **Update Category:** `PUT /api/v1/admin/categories/{id}`
- **Delete Category:** `DELETE /api/v1/admin/categories/{id}`

### Subcategory Management
- **Create Subcategory:** `POST /api/v1/admin/subcategories`
  - Body: `{"category_id": 1, "name": "Artisan Breads", "description": "Sourdough, baguettes", "image": "https://..."}`
- **Update Subcategory:** `PUT /api/v1/admin/subcategories/{id}`
- **Delete Subcategory:** `DELETE /api/v1/admin/subcategories/{id}`

### Product Management
- **Create Product:** `POST /api/v1/admin/products`
  ```json
  {
      "subcategory_id": 1,
      "sku": "US-PROD-201",
      "name": "Organic Whole Milk",
      "brand": "Horizon Organic",
      "unit_size": "1 gallon",
      "price": 4.99,
      "old_price": 5.49,
      "sale_price": 4.49,
      "cost_price": 3.10,
      "stock": 40,
      "low_stock_threshold": 10,
      "is_organic": true,
      "is_perishable": true,
      "status": "active"
  }
  ```
- **Update Product:** `PUT /api/v1/admin/products/{id}`
- **Delete Product:** `DELETE /api/v1/admin/products/{id}`

### Admin Order Management
- **List All Orders:** `GET /api/v1/admin/orders`
  - Query filters: `?order_status=pending&payment_status=paid&order_type=delivery&search=US-ORD`
- **Update Order Status:** `PATCH /api/v1/admin/orders/{id}/status`
  ```json
  {
      "order_status": "processing", // "pending", "confirmed", "processing", "ready_for_pickup", "out_for_delivery", "delivered", "cancelled"
      "payment_status": "paid"      // "pending", "paid", "failed", "refunded"
  }
  ```

### Admin Dispatch & Delivery Management
- **List Deliveries:** `GET /api/v1/admin/deliveries`
  - Query filters: `?delivery_status=assigned&search=TRK-US`
- **Assign Driver & Dispatch:** `POST /api/v1/admin/deliveries/{id}/assign`
  ```json
  {
      "driver_name": "Marcus Vance",
      "driver_phone": "+14155550199",
      "vehicle_info": "Silver Toyota Prius (CA 7XYZ89)",
      "estimated_delivery_time": "2026-10-07 14:30:00"
  }
  ```
- **Update Delivery Status:** `PATCH /api/v1/admin/deliveries/{id}/status`
  ```json
  {
      "delivery_status": "out_for_delivery", // "pending", "assigned", "picked_up", "in_transit", "out_for_delivery", "delivered", "failed", "returned"
      "delivery_notes": "Handed directly to customer",
      "proof_of_delivery_image": "https://..."
  }
  ```
- **Update Live GPS Coordinates:** `PATCH /api/v1/admin/deliveries/{id}/location`
  ```json
  {
      "latitude": 37.774929,
      "longitude": -122.419416
  }
  ```

---

## 7. HTTP Response Codes

| Code | Status | Meaning |
| :--- | :--- | :--- |
| `200` | **OK** | Request completed successfully. |
| `201` | **Created** | Resource (User, Product, Order, Category) created successfully. |
| `400` | **Bad Request** | Invalid request, empty cart checkout, or insufficient stock. |
| `401` | **Unauthorized** | Missing or invalid Bearer authentication token. |
| `403` | **Forbidden** | User lacks required privileges (e.g. non-admin accessing admin route). |
| `404` | **Not Found** | Resource, order, product, or tracking number not found. |
| `422` | **Unprocessable Entity** | Validation failed (missing required field, duplicate email/SKU, etc.). |
| `500` | **Internal Server Error** | Unexpected server error. |
