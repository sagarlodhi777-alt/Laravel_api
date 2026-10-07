<?php

namespace App\Http\Controllers;

use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubCategoryController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => SubCategory::with('category')->orderBy('name')->get(),
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'name' => 'required|string|max:255',
                'slug' => 'nullable|string|max:255|unique:subcategories,slug',
                'description' => 'nullable|string',
                'image' => 'nullable|string'
            ]);

            $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

            $subCategory = SubCategory::create($validated);
            return response()->json(['message' => 'SubCategory created', 'data' => $subCategory], 201);
        } catch (\Throwable $e) {
            Log::error('SubCategory creation failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'SubCategory creation failed: ' . $e->getMessage()], 500);
        }
    }

    public function show(SubCategory $subCategory)
    {
        return response()->json(['data' => $subCategory->load('category')], 200);
    }

    public function update(Request $request, SubCategory $subCategory)
    {
        try {
            $validated = $request->validate([
                'category_id' => 'sometimes|exists:categories,id',
                'name' => 'sometimes|string|max:255',
                'slug' => 'sometimes|string|max:255|unique:subcategories,slug,' . $subCategory->id,
                'description' => 'nullable|string',
                'image' => 'nullable|string'
            ]);

            if (isset($validated['name']) && empty($validated['slug'] ?? '')) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            $subCategory->update($validated);

            return response()->json(['message' => 'SubCategory updated', 'data' => $subCategory], 200);
        } catch (\Throwable $e) {
            Log::error('SubCategory update failed', [
                'sub_category_id' => $subCategory->id,
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'SubCategory update failed'], 500);
        }
    }

    public function destroy(SubCategory $subCategory)
    {
        $subCategory->delete();
        return response()->json(['message' => 'SubCategory deleted', 'data' => $subCategory], 200);
    }
}
