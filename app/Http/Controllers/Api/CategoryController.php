<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * GET /api/categories
     */
    public function index()
    {
        return response()->json(
            Category::where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    }

    public function adminIndex()
    {
        return response()->json(
            Category::withCount('products')
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * POST /api/categories
     */
    public function store(Request $request)

    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'is_active' => ['nullable'],
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'is_active' => filter_var(
                $request->input('is_active', true),
                FILTER_VALIDATE_BOOLEAN
            ),
        ]);

        return response()->json($category);
    }

    /**
     * GET /api/categories/{id}
     */
    public function show($id)
    {
        $category = Category::findOrFail($id);

        return response()->json($category);
    }

    /**
     * POST /api/categories/{id}
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name,' . $category->id],
            'is_active' => ['nullable'],
        ]);

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'is_active' => filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN),
        ]);

        return response()->json($category);
    }

    /**
     * DELETE /api/categories/{id}
     */
    public function destroy($id)
    {
        $category = Category::withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            return response()->json([
                'message' => 'Kategori masih digunakan oleh produk dan tidak dapat dihapus.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }
}
