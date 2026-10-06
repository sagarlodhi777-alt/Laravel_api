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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcategory_id')->constrained()->onDelete('cascade');
            $table->string('sku')->unique(); // e.g., US-GROC-10023
            $table->string('upc_barcode')->nullable()->index(); // 12-digit US UPC barcode
            $table->string('name');
            $table->string('brand')->nullable(); // e.g. Chobani, Kraft, Great Value
            $table->text('description')->nullable();
            $table->string('unit_size')->nullable(); // e.g., 16 oz, 1 gal, 2 lbs, 12 pk
            $table->decimal('price', 8, 2); // Regular retail price in USD
            $table->decimal('sale_price', 8, 2)->nullable(); // Promotional discount price
            $table->decimal('cost_price', 8, 2)->nullable(); // Wholesale cost for margin calculation
            $table->integer('stock')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->string('image')->nullable(); // Primary product image
            $table->json('gallery_images')->nullable(); // Additional product images
            $table->json('nutrition_facts')->nullable(); // Calories, fats, carbs, protein, sodium
            $table->text('ingredients')->nullable();
            $table->boolean('is_organic')->default(false); // USDA Organic flag
            $table->boolean('is_gluten_free')->default(false);
            $table->boolean('is_perishable')->default(false); // Needs refrigeration/cold chain
            $table->enum('status', ['active', 'inactive', 'out_of_stock'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
