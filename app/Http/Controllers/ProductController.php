<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariantItem;

class ProductController extends Controller
{
    private function storeProductImage($file): string
    {
        $path =
            $file->store(
                'products',
                'public'
            );

        if (!$path || !is_string($path)) {
            throw new Exception(
                'Gagal upload gambar produk. Cek permission storage/app/public dan symbolic link public/storage.'
            );
        }

        return $path;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $query = Product::with([
            'category',
            'flashSale',
            'images',
            'options.values',
            'variants'
        ])->where('is_active', true);

        if ($request->category) {

            $query->whereHas(
                'category',
                function ($q) use ($request) {

                    $q->where(
                        'slug',
                        $request->category
                    );
                }
            );
        }

        return response()->json(
            $query
                ->orderByDesc('id')
                ->get()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show($slug)
    {
        $product = Product::with([

            'category',
            'reviews',

            'images' => function ($query) {

                $query
                    ->where('is_active', true)
                    ->latest();
            },

            'options.values',

            'variants' => function ($query) {

                $query
                    ->where('is_active', true)
                    ->latest();
            }

        ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json($product);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        try {

            $request->merge([
                'is_active' => filter_var(
                    $request->is_active,
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]);

            if (!$request->hasFile('image')) {
                $request->request->remove('image');
            }

            $validated = $request->validate([

                'category_id' => [
                    'nullable',
                    'exists:categories,id'
                ],

                'name' => [
                    'required',
                    'string',
                    'max:255'
                ],

                'slug' => [
                    'nullable',
                    'string',
                    'max:255',
                    'unique:products,slug'
                ],

                'description' => [
                    'nullable',
                    'string'
                ],

                'price' => [
                    'required',
                    'numeric',
                    'min:0'
                ],

                'discount_price' => [
                    'nullable',
                    'numeric',
                    'min:0'
                ],

                'stock' => [
                    'required',
                    'integer',
                    'min:0'
                ],

                'sold_count' => [
                    'nullable',
                    'integer',
                    'min:0'
                ],

                'is_active' => [
                    'required',
                    'boolean'
                ],

                /*
                |--------------------------------------------------------------------------
                | THUMBNAIL
                |--------------------------------------------------------------------------
                */
                'image' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048'
                ],

                /*
                |--------------------------------------------------------------------------
                | GALLERY
                |--------------------------------------------------------------------------
                */
                'images' => [
                    'nullable',
                    'array',
                    'max:4'
                ],

                'images.*' => [
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048'
                ],

                /*
                |--------------------------------------------------------------------------
                | OPTIONS & VARIANTS
                |--------------------------------------------------------------------------
                */
                'options' => [
                    'nullable',
                    'string'
                ],

                'variants' => [
                    'nullable',
                    'string'
                ],
            ]);

            $validated['sold_count'] =
                $validated['sold_count'] ?? 0;

            unset($validated['image']);

            /*
            |--------------------------------------------------------------------------
            | AUTO SLUG
            |--------------------------------------------------------------------------
            */
            if (empty($validated['slug'])) {

                $baseSlug = Str::slug(
                    $validated['name']
                );

                $slug = $baseSlug;

                $counter = 1;

                while (
                    Product::where(
                        'slug',
                        $slug
                    )->exists()
                ) {

                    $slug =
                        $baseSlug .
                        '-' .
                        $counter;

                    $counter++;
                }

                $validated['slug'] = $slug;
            }

            /*
            |--------------------------------------------------------------------------
            | THUMBNAIL
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('image')) {

                $validated['image'] =
                    $this->storeProductImage(
                        $request->file('image')
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE PRODUCT
            |--------------------------------------------------------------------------
            */
            $product =
                Product::create($validated);

            /*
            |--------------------------------------------------------------------------
            | SAVE OPTIONS
            |--------------------------------------------------------------------------
            */
            $options = [];

            if ($request->filled('options')) {

                $options = json_decode(
                    $request->options,
                    true
                ) ?? [];
            }

            if ($options) {

                foreach ($options as $optionData) {

                    $option =
                        ProductOption::create([

                            'product_id' =>
                            $product->id,

                            'name' =>
                            $optionData['name'],
                        ]);

                    foreach (
                        $optionData['values']
                        as $value
                    ) {

                        ProductOptionValue::create([

                            'product_option_id' =>
                            $option->id,

                            'value' => $value,
                        ]);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE VARIANTS
            |--------------------------------------------------------------------------
            */
            $variants = [];

            if ($request->filled('variants')) {

                $variants = json_decode(
                    $request->variants,
                    true
                ) ?? [];
            }

            if ($variants) {

                foreach ($variants as $variant) {

                    ProductVariantItem::create([

                        'product_id' =>
                        $product->id,

                        'name' =>
                        $variant['name'],

                        'price' =>
                        $variant['price'] ?: 0,

                        'discount_price' =>
                        $variant['discount_price']
                            ?: null,

                        'stock' =>
                        $variant['stock'] ?: 0,

                        'sku' =>
                        $variant['sku'] ?: null,

                        'is_active' => true,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | GALLERY
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('images')) {

                foreach (
                    $request->file('images')
                    as $imageFile
                ) {

                    $path =
                        $this->storeProductImage(
                            $imageFile
                        );

                    ProductImage::create([

                        'product_id' =>
                        $product->id,

                        'image' => $path,

                        'is_active' => true,
                    ]);
                }
            }

            return response()->json([
                'message' =>
                'Produk berhasil ditambahkan',

                'data' => $product,
            ], 201);
        } catch (Exception $e) {

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(
        Request $request,
        $id
    ) {

        try {

            $request->merge([
                'is_active' => filter_var(
                    $request->is_active,
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]);

            if (!$request->hasFile('image')) {
                $request->request->remove('image');
            }

            $product =
                Product::findOrFail($id);

            $validated = $request->validate([

                'category_id' => [
                    'nullable',
                    'exists:categories,id'
                ],

                'name' => [
                    'required',
                    'string',
                    'max:255'
                ],

                'slug' => [
                    'nullable',
                    'string',
                    'max:255',
                    'unique:products,slug,' .
                        $product->id
                ],

                'description' => [
                    'nullable',
                    'string'
                ],

                'price' => [
                    'required',
                    'numeric',
                    'min:0'
                ],

                'discount_price' => [
                    'nullable',
                    'numeric',
                    'min:0'
                ],

                'stock' => [
                    'required',
                    'integer',
                    'min:0'
                ],

                'sold_count' => [
                    'nullable',
                    'integer',
                    'min:0'
                ],

                'is_active' => [
                    'required',
                    'boolean'
                ],

                'image' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048'
                ],

                'images' => [
                    'nullable',
                    'array',
                    'max:4'
                ],

                'images.*' => [
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048'
                ],

                'options' => [
                    'nullable',
                    'string'
                ],

                'variants' => [
                    'nullable',
                    'string'
                ],
            ]);

            $validated['sold_count'] =
                $validated['sold_count'] ?? 0;

            unset($validated['image']);

            /*
            |--------------------------------------------------------------------------
            | AUTO SLUG
            |--------------------------------------------------------------------------
            */
            if (empty($validated['slug'])) {

                $baseSlug = Str::slug(
                    $validated['name']
                );

                $slug = $baseSlug;

                $counter = 1;

                while (
                    Product::where(
                        'slug',
                        $slug
                    )
                    ->where(
                        'id',
                        '!=',
                        $product->id
                    )
                    ->exists()
                ) {

                    $slug =
                        $baseSlug .
                        '-' .
                        $counter;

                    $counter++;
                }

                $validated['slug'] = $slug;
            }

            /*
            |--------------------------------------------------------------------------
            | THUMBNAIL
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('image')) {

                $validated['image'] =
                    $this->storeProductImage(
                        $request->file('image')
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE PRODUCT
            |--------------------------------------------------------------------------
            */
            $product->update($validated);

            /*
            |--------------------------------------------------------------------------
            | HANDLE EXISTING GALLERY
            |--------------------------------------------------------------------------
            */
            $existingImageIds =
                $request->existing_images ?? [];

            ProductImage::where(
                'product_id',
                $product->id
            )
                ->whereNotIn(
                    'id',
                    $existingImageIds
                )
                ->delete();

            /*
            |--------------------------------------------------------------------------
            | ADD NEW GALLERY
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('images')) {

                foreach (
                    $request->file('images')
                    as $imageFile
                ) {

                    $path =
                        $this->storeProductImage(
                            $imageFile
                        );

                    ProductImage::create([

                        'product_id' =>
                        $product->id,

                        'image' => $path,

                        'is_active' => true,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | DELETE OLD OPTIONS
            |--------------------------------------------------------------------------
            */
            ProductOption::where(
                'product_id',
                $product->id
            )->delete();

            /*
            |--------------------------------------------------------------------------
            | DELETE OLD VARIANTS
            |--------------------------------------------------------------------------
            */
            ProductVariantItem::where(
                'product_id',
                $product->id
            )->delete();

            /*
            |--------------------------------------------------------------------------
            | SAVE OPTIONS
            |--------------------------------------------------------------------------
            */
            $options = [];

            if ($request->filled('options')) {

                $options = json_decode(
                    $request->options,
                    true
                ) ?? [];
            }

            if ($options) {

                foreach ($options as $optionData) {

                    $option =
                        ProductOption::create([

                            'product_id' =>
                            $product->id,

                            'name' =>
                            $optionData['name'],
                        ]);

                    foreach (
                        $optionData['values']
                        as $value
                    ) {

                        ProductOptionValue::create([

                            'product_option_id' =>
                            $option->id,

                            'value' => $value,
                        ]);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE VARIANTS
            |--------------------------------------------------------------------------
            */
            $variants = [];

            if ($request->filled('variants')) {

                $variants = json_decode(
                    $request->variants,
                    true
                ) ?? [];
            }

            if ($variants) {

                foreach ($variants as $variant) {

                    ProductVariantItem::create([

                        'product_id' =>
                        $product->id,

                        'name' =>
                        $variant['name'],

                        'price' =>
                        $variant['price'] ?: 0,

                        'discount_price' =>
                        $variant['discount_price']
                            ?: null,

                        'stock' =>
                        $variant['stock'] ?: 0,

                        'sku' =>
                        $variant['sku'] ?: null,

                        'is_active' => true,
                    ]);
                }
            }

            return response()->json([
                'message' =>
                'Produk berhasil diupdate',

                'data' => $product,
            ]);
        } catch (Exception $e) {

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $product =
            Product::findOrFail($id);

        $product->delete();

        return response()->json([
            'message' =>
            'Produk berhasil dihapus',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */
    public function search(Request $request)
    {
        $query =
            $request->query('q');

        if (!$query) {
            return response()->json([]);
        }

        $products = Product::where(
            'is_active',
            true
        )
            ->where(
                function ($q)
                use ($query) {

                    $q->where(
                        'name',
                        'like',
                        '%' . $query . '%'
                    )
                        ->orWhere(
                            'description',
                            'like',
                            '%' . $query . '%'
                        );
                }
            )
            ->limit(6)
            ->get();

        return response()->json($products);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW BY ID
    |--------------------------------------------------------------------------
    */
    public function showById($id)
    {
        $product = Product::with([

            'category',

            'images' => function ($query) {

                $query
                    ->where(
                        'is_active',
                        true
                    )
                    ->latest();
            },

            'options.values',

            'variants' => function ($query) {

                $query
                    ->where(
                        'is_active',
                        true
                    )
                    ->latest();
            }

        ])->findOrFail($id);

        return response()->json($product);
    }
}
