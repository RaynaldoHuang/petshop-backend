<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HeroSectionController extends Controller
{
    public function index()
    {
        $heroes = HeroSection::orderBy('sort_order')->get();
        return view('admin.hero.index', compact('heroes'));
    }

    public function create()
    {
        return view('admin.hero.create');
    }

    public function store(Request $request)
    {
        $data = $request->all();

        // upload images
        if ($request->hasFile('main_image')) {
            $data['main_image'] = $request->file('main_image')->store('hero', 'public');
        }

        if ($request->hasFile('top_image')) {
            $data['top_image'] = $request->file('top_image')->store('hero', 'public');
        }

        if ($request->hasFile('bottom_left_image')) {
            $data['bottom_left_image'] = $request->file('bottom_left_image')->store('hero', 'public');
        }

        if ($request->hasFile('bottom_right_image')) {
            $data['bottom_right_image'] = $request->file('bottom_right_image')->store('hero', 'public');
        }

        $data['is_active'] = $request->has('is_active');

        HeroSection::create($data);

        return redirect('/admin/hero-sections')->with('success', 'Hero created');
    }

    public function edit($id)
    {
        $hero = HeroSection::findOrFail($id);
        return view('admin.hero.edit', compact('hero'));
    }

    public function update(Request $request, $id)
    {
        $hero = HeroSection::findOrFail($id);
        $data = $request->all();

        // upload replace image
        if ($request->hasFile('main_image')) {
            $data['main_image'] = $request->file('main_image')->store('hero', 'public');
        }

        if ($request->hasFile('top_image')) {
            $data['top_image'] = $request->file('top_image')->store('hero', 'public');
        }

        if ($request->hasFile('bottom_left_image')) {
            $data['bottom_left_image'] = $request->file('bottom_left_image')->store('hero', 'public');
        }

        if ($request->hasFile('bottom_right_image')) {
            $data['bottom_right_image'] = $request->file('bottom_right_image')->store('hero', 'public');
        }

        $data['is_active'] = $request->has('is_active');

        $hero->update($data);

        return redirect('/admin/hero-sections')->with('success', 'Hero updated');
    }

    public function destroy($id)
    {
        HeroSection::destroy($id);
        return redirect('/admin/hero-sections')->with('success', 'Hero deleted');
    }
}
