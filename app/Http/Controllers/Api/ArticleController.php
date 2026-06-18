<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ImageUploadService $imageUploadService
    ) {}

    public function index()
    {
        return response()->json(
            Article::where('is_published', true)
                ->latest('published_at')
                ->get()
        );
    }

    public function adminIndex()
    {
        return response()->json(
            Article::latest()->get()
        );
    }

    public function show($slug)
    {
        return response()->json(
            Article::where('slug', $slug)
                ->where('is_published', true)
                ->firstOrFail()
        );
    }

    public function adminShow($id)
    {
        return response()->json(
            Article::findOrFail($id)
        );
    }

    public function store(Request $request)
    {
        $request->merge([
            'is_published' => filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN),
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:articles,slug'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_published' => ['required', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['content'] = $this->normalizeContentHtml($validated['content']);

        if (empty($validated['slug'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title']);
        } else {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        if (empty($validated['meta_title'])) {
            $validated['meta_title'] = $validated['title'];
        }

        if (empty($validated['meta_description'])) {
            $validated['meta_description'] = $validated['excerpt']
                ?? Str::limit(strip_tags($validated['content']), 150);
        }

        $validated['reading_time'] = max(
            1,
            (int) ceil(str_word_count(strip_tags($validated['content'])) / 200)
        );

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $this->imageUploadService->storeAsWebp(
                $request->file('thumbnail'),
                'articles'
            );
        }

        $validated['published_at'] = $validated['is_published'] ? now() : null;

        $article = Article::create($validated);

        return response()->json($article, 201);
    }

    public function update(Request $request, $id)
    {
        $article = Article::findOrFail($id);

        $request->merge([
            'is_published' => filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN),
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:articles,slug,'.$article->id],
            'excerpt' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_published' => ['required', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['content'] = $this->normalizeContentHtml($validated['content']);

        if (empty($validated['slug'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $article->id);
        } else {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        if (empty($validated['meta_title'])) {
            $validated['meta_title'] = $validated['title'];
        }

        if (empty($validated['meta_description'])) {
            $validated['meta_description'] = $validated['excerpt']
                ?? Str::limit(strip_tags($validated['content']), 150);
        }

        $validated['reading_time'] = max(
            1,
            (int) ceil(str_word_count(strip_tags($validated['content'])) / 200)
        );

        if ($request->hasFile('thumbnail')) {
            if ($article->thumbnail) {
                Storage::disk('public')->delete($article->thumbnail);
            }

            $validated['thumbnail'] = $this->imageUploadService->storeAsWebp(
                $request->file('thumbnail'),
                'articles'
            );
        }

        $validated['published_at'] = $validated['is_published']
            ? ($article->published_at ?? now())
            : null;

        $article->update($validated);

        return response()->json($article);
    }

    public function destroy($id)
    {
        Article::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Article deleted',
        ]);
    }

    public function related($slug)
    {
        $article = Article::where('slug', $slug)->firstOrFail();

        return response()->json(
            Article::where('is_published', true)
                ->where('id', '!=', $article->id)
                ->when($article->category, function ($query) use ($article) {
                    $query->where('category', $article->category);
                })
                ->latest('published_at')
                ->limit(3)
                ->get()
        );
    }

    private function normalizeContentHtml(string $content): string
    {
        $content = trim($content);

        // Fix content lama / content yang tersimpan double-escaped seperti \u003Cul\u003E
        $content = str_replace(
            ['\\u003C', '\\u003E', '\\"', '\\/', '\\n', '\\r'],
            ['<', '>', '"', '/', '', ''],
            $content
        );

        // Kalau masih ada unicode HTML escaped dari JSON, decode juga
        $decoded = json_decode('"'.addcslashes($content, '"\\').'"');

        if (is_string($decoded) && str_contains($decoded, '<')) {
            $content = $decoded;
        }

        return $content;
    }

    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Article::where('slug', $slug)
                ->when($ignoreId, function ($query) use ($ignoreId) {
                    $query->where('id', '!=', $ignoreId);
                })
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
