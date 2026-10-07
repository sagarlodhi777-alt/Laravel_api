<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of products with filters.
     */
    public function index(Request $request)
    {
        $query = Product::with(['subcategory.category', 'category']);

        // Subcategory filter
        if ($request->filled('subcategory_id')) {
            $query->where('subcategory_id', $request->query('subcategory_id'));
        } elseif ($request->filled('sub_category_id')) {
            $query->where(function ($q) use ($request) {
                $subId = $request->query('sub_category_id');
                $q->where('subcategory_id', $subId)
                  ->orWhere('sub_category_id', $subId);
            });
        }

        // Category filter
        if ($request->filled('category_id')) {
            $categoryId = $request->query('category_id');
            $query->where(function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)
                  ->orWhereHas('subcategory', function ($sq) use ($categoryId) {
                      $sq->where('category_id', $categoryId);
                  });
            });
        }

        // Search by keyword, brand, SKU, UPC, name, slug, description
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('upc_barcode', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Tag / Brand filter
        if ($request->filled('tag')) {
            $query->where('tag', $request->query('tag'));
        }
        if ($request->filled('brand')) {
            $query->where('brand', $request->query('brand'));
        }

        // Dietary / Supermarket flags
        if ($request->has('is_organic')) {
            $query->where('is_organic', filter_var($request->query('is_organic'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('is_gluten_free')) {
            $query->where('is_gluten_free', filter_var($request->query('is_gluten_free'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('is_perishable')) {
            $query->where('is_perishable', filter_var($request->query('is_perishable'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('local')) {
            $query->where('local', filter_var($request->query('local'), FILTER_VALIDATE_BOOLEAN));
        }

        // Price range filters
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->query('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->query('max_price'));
        }

        // Status filter (defaults to active unless specified)
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'latest');
        match ($sortBy) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            default => $query->latest(),
        };

        if ($request->has('all') && filter_var($request->query('all'), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json($query->get(), 200);
        }

        return response()->json($query->paginate($request->query('per_page', 20)), 200);
    }

    /**
     * Store a newly created product (Admin only).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subcategory_id' => 'required_without:sub_category_id|nullable|exists:subcategories,id',
            'sub_category_id' => 'nullable|integer',
            'category_id' => 'nullable|exists:categories,id',
            'sku' => 'nullable|string|max:50|unique:products,sku',
            'upc_barcode' => 'nullable|string|max:20',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'brand' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'unit_size' => 'nullable|string|max:50',
            'size' => 'nullable|string|max:50',
            'short_size' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'save_pct' => 'nullable|integer',
            'stock' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'image' => 'nullable|string',
            'gallery_images' => 'nullable|array',
            'gallery_images.*' => 'string',
            'emoji' => 'nullable|string',
            'tint' => 'nullable|string',
            'tag' => 'nullable|string',
            'rating' => 'nullable|numeric',
            'reviews_count' => 'nullable|integer',
            'local' => 'nullable|boolean',
            'nutrition_facts' => 'nullable|array',
            'ingredients' => 'nullable|string',
            'is_organic' => 'nullable|boolean',
            'is_gluten_free' => 'nullable|boolean',
            'is_perishable' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,out_of_stock'
        ]);

        if (empty($validated['subcategory_id']) && ! empty($validated['sub_category_id'])) {
            $validated['subcategory_id'] = $validated['sub_category_id'];
        }

        if (empty($validated['sku'])) {
            $validated['sku'] = 'SKU-' . strtoupper(Str::random(8));
        }

        if (empty($validated['slug'])) {
            $baseSlug = Str::slug($validated['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        $product = Product::create($validated);
        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product->load('subcategory.category')
        ], 201);
    }

    /**
     * Display a specific product.
     */
    public function show(Product $product)
    {
        return response()->json($product->load('subcategory.category'), 200);
    }

    /**
     * Update a product (Admin only).
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'subcategory_id' => 'sometimes|nullable|exists:subcategories,id',
            'sub_category_id' => 'nullable|integer',
            'category_id' => 'nullable|exists:categories,id',
            'sku' => 'sometimes|string|max:50|unique:products,sku,' . $product->id,
            'upc_barcode' => 'nullable|string|max:20',
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|unique:products,slug,' . $product->id,
            'brand' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'unit_size' => 'nullable|string|max:50',
            'size' => 'nullable|string|max:50',
            'short_size' => 'nullable|string|max:50',
            'price' => 'sometimes|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'save_pct' => 'nullable|integer',
            'stock' => 'sometimes|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'image' => 'nullable|string',
            'gallery_images' => 'nullable|array',
            'gallery_images.*' => 'string',
            'emoji' => 'nullable|string',
            'tint' => 'nullable|string',
            'tag' => 'nullable|string',
            'rating' => 'nullable|numeric',
            'reviews_count' => 'nullable|integer',
            'local' => 'nullable|boolean',
            'nutrition_facts' => 'nullable|array',
            'ingredients' => 'nullable|string',
            'is_organic' => 'nullable|boolean',
            'is_gluten_free' => 'nullable|boolean',
            'is_perishable' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,out_of_stock'
        ]);

        if (empty($validated['subcategory_id']) && ! empty($validated['sub_category_id'])) {
            $validated['subcategory_id'] = $validated['sub_category_id'];
        }

        $product->update($validated);
        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product->load('subcategory.category')
        ], 200);
    }

    /**
     * Remove a product (Admin only).
     */
    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully'], 200);
    }
}
