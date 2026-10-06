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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->onDelete('cascade');
            $table->string('tracking_number')->unique(); // e.g. US-TRK-837492
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->string('vehicle_info')->nullable(); // e.g. "Toyota Prius - License #7ABC123"
            $table->string('delivery_time_slot')->default('asap');
            $table->string('delivery_time_slot_label')->nullable();
            
            // Dispatch & Delivery Tracking Status
            $table->enum('delivery_status', [
                'pending',
                'assigned',
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'failed',
                'returned'
            ])->default('pending');

            // Route & Location Tracking
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->timestamp('estimated_delivery_time')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Additional Info
            $table->text('delivery_notes')->nullable();
            $table->string('proof_of_delivery_image')->nullable(); // Photo or signature URL upon drop-off
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
