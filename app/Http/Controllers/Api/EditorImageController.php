<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

class EditorImageController extends Controller
{
    public function __construct(
        private readonly ImageUploadService $imageUploadService
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $path = $this->imageUploadService->storeAsWebp(
            $request->file('image'),
            'articles/editor'
        );

        return response()->json([
            'path' => $path,
            'url' => asset('storage/'.$path),
        ]);
    }
}
