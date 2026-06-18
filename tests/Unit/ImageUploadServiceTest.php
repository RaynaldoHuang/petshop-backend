<?php

namespace Tests\Unit;

use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadServiceTest extends TestCase
{
    public function test_it_stores_uploaded_images_as_resized_webp(): void
    {
        Storage::fake('public');

        $service = new ImageUploadService;
        $file = UploadedFile::fake()->image('product.jpg', 3000, 2000);

        $path = $service->storeAsWebp($file, 'products', maxWidth: 1200, maxHeight: 1200);

        Storage::disk('public')->assertExists($path);

        $this->assertStringStartsWith('products/', $path);
        $this->assertStringEndsWith('.webp', $path);

        $image = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame(IMAGETYPE_WEBP, $image[2]);
        $this->assertSame(1200, $image[0]);
        $this->assertSame(800, $image[1]);
    }
}
