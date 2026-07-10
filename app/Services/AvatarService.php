<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Normalizes uploaded profile photos: EXIF orientation is corrected (phone
 * cameras store rotation as metadata GD ignores), the image is center-cropped
 * to a square and resized, and the result is re-encoded as JPEG so any
 * accepted format renders identically everywhere an avatar appears.
 */
class AvatarService
{
    public function storeSquare(UploadedFile $file, int $size = 512, string $directory = 'user-photos'): string
    {
        $image = imagecreatefromstring(file_get_contents($file->getRealPath()));

        if ($image === false) {
            abort(422, 'The uploaded file could not be read as an image.');
        }

        $image = $this->applyExifOrientation($image, $file);

        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $x = intdiv($width - $side, 2);
        $y = intdiv($height - $side, 2);

        $square = imagecreatetruecolor($size, $size);
        // Transparent PNG/WebP areas become white rather than black in the JPEG.
        $white = imagecolorallocate($square, 255, 255, 255);
        imagefill($square, 0, 0, $white);
        imagecopyresampled($square, $image, 0, 0, $x, $y, $size, $size, $side, $side);

        ob_start();
        imagejpeg($square, null, 85);
        $data = ob_get_clean();

        imagedestroy($image);
        imagedestroy($square);

        $path = $directory.'/'.Str::random(40).'.jpg';
        Storage::disk('public')->put($path, $data);

        return $path;
    }

    protected function applyExifOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/tiff'], true)) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = $exif['Orientation'] ?? 1;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
