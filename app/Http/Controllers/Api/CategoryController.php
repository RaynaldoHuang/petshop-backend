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

    /**
     * POST /api/categories
     */
    public function store(Request $request)

    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'is_active' => $request->is_active ?? true,
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
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable'],
        ]);

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(4),
            'is_active' => filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN),
        ]);

        return response()->json($category);
    }

    /**
     * DELETE /api/categories/{id}
     */
    public function destroy($id)
    {
        Category::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Category deleted',
        ]);
    }
}
