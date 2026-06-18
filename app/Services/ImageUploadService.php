<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    public function storeAsWebp(
        UploadedFile $file,
        string $directory,
        string $disk = 'public',
        int $quality = 84,
        int $maxWidth = 1920,
        int $maxHeight = 1920
    ): string {
        if (! function_exists('imagewebp')) {
            throw new Exception('Extension GD WebP belum aktif di server.');
        }

        $source = $this->createImageResource($file);
        $source = $this->applyOrientation($source, $file);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        [$targetWidth, $targetHeight] = $this->targetSize(
            $sourceWidth,
            $sourceHeight,
            $maxWidth,
            $maxHeight
        );

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);

        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        $temporaryPath = tempnam(sys_get_temp_dir(), 'webp_');

        try {
            if (! imagewebp($target, $temporaryPath, $quality)) {
                throw new Exception('Gagal mengubah gambar ke WebP.');
            }

            $path = trim($directory, '/').'/'.Str::uuid().'.webp';

            Storage::disk($disk)->put($path, file_get_contents($temporaryPath));

            return $path;
        } finally {
            imagedestroy($source);
            imagedestroy($target);

            if (is_string($temporaryPath) && file_exists($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function createImageResource(UploadedFile $file): \GdImage
    {
        $image = match ($file->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
            'image/png' => imagecreatefrompng($file->getRealPath()),
            'image/webp' => imagecreatefromwebp($file->getRealPath()),
            default => throw new Exception('Format gambar tidak didukung.'),
        };

        if (! $image instanceof \GdImage) {
            throw new Exception('Gagal membaca file gambar.');
        }

        return $image;
    }

    private function applyOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (
            $file->getMimeType() !== 'image/jpeg'
            || ! function_exists('exif_read_data')
        ) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = $exif['Orientation'] ?? null;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if (! $rotated instanceof \GdImage) {
            return $image;
        }

        if ($rotated !== $image) {
            imagedestroy($image);
        }

        return $rotated;
    }

    private function targetSize(
        int $width,
        int $height,
        int $maxWidth,
        int $maxHeight
    ): array {
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }
}
