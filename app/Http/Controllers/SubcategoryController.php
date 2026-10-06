<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    /**
     * Display a listing of subcategories.
     */
    public function index(Request $request)
    {
        $query = Subcategory::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        return response()->json($query->get(), 200);
    }

    /**
     * Display subcategories of a specific category.
     */
    public function getByCategory(Category $category)
    {
        return response()->json($category->subcategories, 200);
    }

    /**
     * Store a newly created subcategory in storage (Admin only).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string'
        ]);

        $subcategory = Subcategory::create($validated);
        return response()->json(['message' => 'Subcategory created', 'data' => $subcategory->load('category')], 201);
    }

    /**
     * Display the specified subcategory.
     */
    public function show(Subcategory $subcategory)
    {
        return response()->json($subcategory->load(['category', 'products']), 200);
    }

    /**
     * Update the specified subcategory in storage (Admin only).
     */
    public function update(Request $request, Subcategory $subcategory)
    {
        $validated = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string'
        ]);

        $subcategory->update($validated);
        return response()->json(['message' => 'Subcategory updated', 'data' => $subcategory->load('category')], 200);
    }

    /**
     * Remove the specified subcategory from storage (Admin only).
     */
    public function destroy(Subcategory $subcategory)
    {
        $subcategory->delete();
        return response()->json(['message' => 'Subcategory deleted'], 200);
    }
}
