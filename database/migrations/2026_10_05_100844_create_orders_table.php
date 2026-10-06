<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // e.g. US-ORD-20261006-8A7B
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Financial Breakdown (in USD)
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 8, 2)->default(0.00); // US State & Local Sales Tax
            $table->decimal('delivery_fee', 8, 2)->default(0.00);
            $table->decimal('tip_amount', 8, 2)->default(0.00); // US standard courier/driver tip
            $table->decimal('discount_amount', 8, 2)->default(0.00);
            $table->decimal('total_price', 10, 2)->default(0.00); // Grand Total

            // Order & Payment Status
            $table->enum('order_type', ['delivery', 'curbside_pickup', 'in_store_pickup'])->default('delivery');
            $table->string('delivery_time_slot')->default('asap'); // e.g., 'asap', 'today_18_19', 'today_19_20', 'tomorrow_09_10'
            $table->string('delivery_time_slot_label')->nullable(); // e.g., "ASAP (In ~45 min)", "6:00 – 7:00 pm Today"
            $table->enum('order_status', ['pending', 'confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'cancelled'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('payment_method', ['credit_card', 'debit_card', 'apple_pay', 'google_pay', 'ebt_snap', 'cash_on_delivery'])->default('credit_card');

            // US Shipping / Delivery Address
            $table->string('shipping_name')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->string('shipping_address_line1')->nullable(); // Street address
            $table->string('shipping_address_line2')->nullable(); // Apt/Suite/Unit
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state', 2)->nullable(); // 2-letter US State Code (e.g. CA, NY, TX)
            $table->string('shipping_zip_code', 10)->nullable(); // US ZIP Code (e.g. 90210 or 90210-1234)
            $table->text('delivery_instructions')->nullable(); // Gate code, "Leave at door", etc.

            // Timestamps
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
