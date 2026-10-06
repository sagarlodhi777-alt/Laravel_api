# Olivia Supermarket — REST API Documentation

Base URL: `http://127.0.0.1:8000/api/v1`

---

## 🛒 Store Overview & Frontend Integration
Designed to power the **Olivia Super Market** web and mobile storefronts (Cart: `/cart` & Checkout: `/checkout`):
* **Cart Management**: Real-time quantity adjustment, stock checks, $35 free delivery progress meter, tax calculation, and guest session sync.
* **Multi-Step Checkout**:
  1. **Delivery Address**: Full name, street address, city/state/zip, phone, and rider delivery note.
  2. **Delivery Time Slot Selection**: ASAP (~45 min fastest), scheduled time windows (e.g. `6:00 – 7:00 pm Today`, `Tomorrow morning`).
  3. **Payment Options**: Card (Visa/Mastercard/Amex), Cash on Delivery, Apple Pay, Google Pay, EBT SNAP.
* **Dispatch & Live Delivery Tracking**: Tracking number generation, courier details, live GPS coordinates, and drop-off proof photos.

---

## Authentication

All authenticated endpoints require a Bearer token in the `Authorization` header:
`Authorization: Bearer <your_token_here>`

Guest cart operations can pass a header: `X-Session-ID: <uuid>`

---

## 1. Authentication Endpoints (Public)

### Register (Customer or Admin)
- **POST** `/auth/register`
- **Request Body:**
  ```json
  {
      "name": "Michael O.",
      "email": "michael@example.com",
      "phone": "(512) 555-0142",
      "password": "password123",
      "password_confirmation": "password123",
      "role": "user"
  }
  ```

### Login
- **POST** `/auth/login`
- **Request Body:**
  ```json
  {
      "email": "michael@example.com",
      "password": "password123"
  }
  ```

### Logout (Requires Auth)
- **POST** `/auth/logout`

---

## 2. Storefront Catalog Browsing (Public)

### Departments & Categories
* **GET** `/categories` (Returns departments and nested subcategories)
* **GET** `/categories/{id}`
* **GET** `/categories/{category_id}/subcategories`

### Subcategories / Aisles
* **GET** `/subcategories` *(Optional Query: `?category_id=1`)*
* **GET** `/subcategories/{id}` (Returns subcategory with associated products)

### Product Catalog
* **GET** `/products`
  * **Query Filters:** `?search=apples&subcategory_id=1&is_organic=true&min_price=2&max_price=15&sort_by=price_asc&per_page=20`
* **GET** `/products/{id}`

---

## 3. Cart APIs (`/cart`)

Supports both authenticated users (via Bearer token) and guest shoppers (via `X-Session-ID` header or `session_id` query parameter). When a guest user logs in, guest cart items are automatically merged into their account cart.

### Get Active Cart & Calculated Summary
* **GET** `/cart`
* **Headers:** `Authorization: Bearer <token>` OR `X-Session-ID: 7a8f9c12-...`
* **Response (200 OK):**
### Social Login / Register (Google / Facebook)
- **Endpoint:** `/auth/social-login`
- **Method:** `POST`
- **Description:** Send the `access_token` you receive from Google or Facebook OAuth on the client side. The API will verify it and log the user in, or create a new user account if one doesn't exist.
- **Request Body:**
  ```json
  {
      "provider": "google", // or "facebook"
      "token": "your_access_token_from_google_or_facebook"
  }
  ```

### Forgot Password
- **Endpoint:** `/auth/forgot-password`
- **Method:** `POST`
- **Request Body:**
  ```json
  {
      "session_id": "7a8f9c12-3456-4789-abcd-1234567890ef",
      "cart": {
          "items_count": 5,
          "items": [
              {
                  "id": 101,
                  "product_id": 1,
                  "name": "Fuji Apples",
                  "brand": "Local Orchards",
                  "unit_size": "1 kg pack",
                  "image": "https://olivia-ruby.vercel.app/images/apples.png",
                  "unit_price": "3.49",
                  "quantity": 2,
                  "line_total": "6.98",
                  "in_stock": true,
                  "available_stock": 45
              },
              {
                  "id": 102,
                  "product_id": 2,
                  "name": "Roma Tomatoes",
                  "brand": "Fresh Farms",
                  "unit_size": "500 g pack",
                  "image": "https://olivia-ruby.vercel.app/images/tomatoes.png",
                  "unit_price": "2.10",
                  "quantity": 1,
                  "line_total": "2.10",
                  "in_stock": true,
                  "available_stock": 30
              },
              {
                  "id": 103,
                  "product_id": 3,
                  "name": "Whole Milk",
                  "brand": "Horizon Organic",
                  "unit_size": "1 gallon",
                  "image": "https://olivia-ruby.vercel.app/images/milk.png",
                  "unit_price": "3.89",
                  "quantity": 1,
                  "line_total": "3.89",
                  "in_stock": true,
                  "available_stock": 20
              },
              {
                  "id": 104,
                  "product_id": 4,
                  "name": "Sourdough Loaf",
                  "brand": "Artisan Bakery",
                  "unit_size": "each",
                  "image": "https://olivia-ruby.vercel.app/images/bread.png",
                  "unit_price": "4.49",
                  "quantity": 1,
                  "line_total": "4.49",
                  "in_stock": true,
                  "available_stock": 15
              }
          ],
          "subtotal": "17.46",
          "delivery_fee": "2.99",
          "estimated_tax": "1.10",
          "total": "21.55",
          "free_delivery_threshold": "35.00",
          "amount_needed_for_free_delivery": "17.54",
          "free_delivery_progress_percentage": 49.9,
          "is_eligible_for_free_delivery": false
      }
  }
  ```

### Add Item to Cart
* **POST** `/cart/items`
* **Body:**
  ```json
  {
      "product_id": 1,
      "quantity": 2
  }
  ```

### Update Item Quantity
* **PUT** `/cart/items/{cart_item_id}`
* **Body:**
  ```json
  {
      "quantity": 3 // Set to 0 to remove item
  }
  ```

### Remove Single Item
* **DELETE** `/cart/items/{cart_item_id}`

### Clear Cart
* **DELETE** `/cart/clear`
### Get All SubCategories
- **Endpoint:** `/subcategories`
- **Method:** `GET`

### Get Single SubCategory
- **Endpoint:** `/subcategories/{id}`
- **Method:** `GET`

### Get All Products
- **Endpoint:** `/products`
- **Method:** `GET`

### Preview Checkout Breakdown
* **POST** `/cart/preview`
* **Body (Optional Overrides):**
  ```json
  {
      "order_type": "delivery",
      "tip_amount": 3.00,
      "coupon_code": "FRESH10"
  }
  ```

---

## 4. Delivery Slots & Options

### Get Available Delivery Windows
* **GET** `/delivery-slots`
* **Response (200 OK):**
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
          },
          {
              "id": "today_19_20",
              "title": "7:00 – 8:00 pm",
              "subtitle": "Today",
              "badge": null,
              "is_default": false,
              "available": true
          },
          {
              "id": "tomorrow_09_10",
              "title": "9:00 – 10:00 am",
              "subtitle": "Tomorrow",
              "badge": null,
              "is_default": false,
              "available": true
          }
      ]
  }
  ```

---

## 5. Checkout & Customer Orders (Requires Auth)

### Place Order / Checkout
Converts the customer's active Cart or explicit item list into a confirmed order.
* **POST** `/user/orders`
* **Headers:** `Authorization: Bearer <token>`
* **Request Body:**
  ```json
  {
      "order_type": "delivery",
      "delivery_time_slot": "asap",
      "delivery_time_slot_label": "ASAP (In ~45 min)",
      "payment_method": "card", // "card", "cash_on_delivery", "apple_pay", "google_pay", "ebt_snap"
      "shipping_name": "Michael O.",
      "shipping_phone": "(512) 555-0142",
      "shipping_address_line1": "221 Maple St, Apt 4B",
      "shipping_city": "Austin, TX",
      "shipping_state": "TX",
      "shipping_zip_code": "78701",
      "delivery_instructions": "Leave at the door, thank you!",
      "tip_amount": 0.00
  }
  ```
* **Response (201 Created):**
  ```json
  {
      "message": "Order placed successfully",
      "data": {
          "order_number": "US-ORD-20261006-K9F2A1",
          "subtotal": "17.46",
          "delivery_fee": "2.99",
          "tax_amount": "1.10",
          "tip_amount": "0.00",
          "discount_amount": "0.00",
          "total_price": "21.55",
          "order_type": "delivery",
          "delivery_time_slot": "asap",
          "order_status": "pending",
          "payment_status": "paid",
          "payment_method": "credit_card",
          "delivery": {
              "tracking_number": "TRK-US-778129",
              "delivery_status": "pending",
              "estimated_delivery_time": "2026-10-06T12:00:00.000000Z"
          }
      }
  }
  ```

### Customer Order History
* **GET** `/user/orders`
* **GET** `/user/orders/{id}`

### Live Delivery Tracking (Customer)
* **GET** `/user/orders/{id}/track-delivery`
* **GET** `/tracking/{tracking_number}` (Public tracking without login)

---

## 6. Admin Management APIs (Requires Admin Auth)

### Category & Department Management
* **POST** `/admin/categories`
* **PUT** `/admin/categories/{id}`
* **DELETE** `/admin/categories/{id}`

### Subcategory / Aisle Management
* **POST** `/admin/subcategories`
* **PUT** `/admin/subcategories/{id}`
* **DELETE** `/admin/subcategories/{id}`

### Product Inventory Management
* **POST** `/admin/products`
* **PUT** `/admin/products/{id}`
* **DELETE** `/admin/products/{id}`

### Supermarket Orders Dispatch
* **GET** `/admin/orders` *(Query filters: `?order_status=pending`)*
* **PATCH** `/admin/orders/{id}/status`
### Manage Categories
- **POST** `/admin/categories`
  - Body (JSON): `{"name": "Fruits", "description": "Fresh fruits", "image": "url"}`
- **PUT** `/admin/categories/{id}`
- **DELETE** `/admin/categories/{id}`

### Manage SubCategories
- **POST** `/admin/subcategories`
  - Body (JSON): `{"category_id": 1, "name": "Apples", "slug": "apples-pears", "description": "All apples"}`
- **PUT** `/admin/subcategories/{id}`
- **DELETE** `/admin/subcategories/{id}`

### Manage Products
- **POST** `/admin/products`
  - Body (JSON): 
  ```json
  {
      "category_id": 1, 
      "sub_category_id": 2, 
      "name": "Fuji Apples", 
      "slug": "fuji-apples",
      "size": "1 kg pack",
      "short_size": "1 kg",
      "price": 3.49, 
      "old_price": 4.30,
      "save_pct": 20,
      "stock": 100,
      "emoji": "🍎",
      "tint": "peach",
      "tag": "Fruits",
      "local": true
  }
  ```
- **PUT** `/admin/products/{id}`
- **DELETE** `/admin/products/{id}`

### Live Delivery Courier Dispatch
* **GET** `/admin/deliveries`
* **POST** `/admin/deliveries/{id}/assign` *(Assign driver name, phone, and vehicle)*
* **PATCH** `/admin/deliveries/{id}/status` *(Update status to `assigned`, `picked_up`, `in_transit`, `out_for_delivery`, `delivered`)*
* **PATCH** `/admin/deliveries/{id}/location` *(Update live latitude & longitude)*

---

## 7. HTTP Status Codes
* `200 OK`: Successful retrieval or update.
* `201 Created`: Order placed or resource created.
* `400 Bad Request`: Out of stock item or invalid cart action.
* `401 Unauthorized`: Unauthenticated request on protected route.
* `403 Forbidden`: Insufficient permissions.
* `404 Not Found`: Item, order, or cart not found.
* `422 Unprocessable Entity`: Form validation failed.
