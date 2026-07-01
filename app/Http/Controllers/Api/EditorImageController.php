<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

class EditorImageController extends Controller
{
    private const MAX_EDITOR_IMAGE_KB = 5120;

    public function __construct(
        private readonly ImageUploadService $imageUploadService
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_EDITOR_IMAGE_KB],
        ], [
            'image.required' => 'Gambar wajib dipilih.',
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Gambar harus berformat JPG, JPEG, PNG, atau WEBP.',
            'image.max' => 'Ukuran gambar maksimal 5 MB.',
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
