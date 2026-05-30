<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index()
    {
        return response()->json(
            Announcement::orderBy('sort_order')->latest()->get()
        );
    }

    public function active()
    {
        return response()->json(
            Announcement::where('is_active', true)
                ->orderBy('sort_order')
                ->latest()
                ->get()
        );
    }

    public function store(Request $request)
    {
        $request->merge([
            'is_active' => filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN),
        ]);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:255'],
            'link_text' => ['nullable', 'string', 'max:100'],
            'link_href' => ['nullable', 'string', 'max:255'],
            'bg_color' => ['required', 'string', 'max:100'],
            'text_color' => ['required', 'string', 'max:100'],
            'border_color' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $announcement = Announcement::create($validated);

        return response()->json([
            'message' => 'Announcement berhasil dibuat',
            'data' => $announcement,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);

        $request->merge([
            'is_active' => filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN),
        ]);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:255'],
            'link_text' => ['nullable', 'string', 'max:100'],
            'link_href' => ['nullable', 'string', 'max:255'],
            'bg_color' => ['required', 'string', 'max:100'],
            'text_color' => ['required', 'string', 'max:100'],
            'border_color' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $announcement->update($validated);

        return response()->json([
            'message' => 'Announcement berhasil diupdate',
            'data' => $announcement,
        ]);
    }

    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return response()->json([
            'message' => 'Announcement berhasil dihapus',
        ]);
    }
}
