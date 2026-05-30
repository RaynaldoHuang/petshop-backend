<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroSection;
use Illuminate\Http\Request;

class HeroSectionController extends Controller
{
    public function index()
    {
        return response()->json(
            HeroSection::orderBy('sort_order')->get()
        );
    }

    public function active()
    {
        return response()->json(
            HeroSection::where('is_active', true)
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'link' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('hero', 'public');
        }

        $validated['is_active'] = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $hero = HeroSection::create($validated);

        return response()->json($hero, 201);
    }

    public function update(Request $request, $id)
    {
        $hero = HeroSection::findOrFail($id);

        $validated = $request->validate([
            'main_title' => ['nullable', 'string', 'max:255'],
            'main_description' => ['nullable', 'string'],
            'main_button_text' => ['nullable', 'string', 'max:100'],
            'main_button_link' => ['nullable', 'string', 'max:255'],
            'main_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if ($request->hasFile('main_image')) {
            $validated['main_image'] = $request->file('main_image')->store('hero', 'public');
        }

        $validated['is_active'] = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $hero->update($validated);

        return response()->json($hero);
    }

    public function destroy($id)
    {
        HeroSection::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Hero deleted',
        ]);
    }
}
