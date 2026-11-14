<?php

namespace App\Services\Images;

use GdImage;

/**
 * Re-encodes uploaded images with GD so nothing from the original file
 * survives except pixels:
 *   - EXIF/XMP/text chunks (incl. GPS) are dropped: GD never writes them;
 *   - JPEG EXIF orientation is applied to the pixels first;
 *   - the longest side is limited to max_dimension, plus a thumbnail;
 *   - same format out as in (JPEG/PNG/WebP), quality from config.
 * Decoding is refused above max_pixels (memory) and for formats the server's
 * GD build cannot read (ImageRejected with a user-facing message).
 */
class ImageNormalizer
{
    private const FORMATS = [
        IMAGETYPE_JPEG => ['mime' => 'image/jpeg', 'ext' => 'jpg', 'label' => 'JPEG', 'flag' => IMG_JPG, 'reader' => 'imagecreatefromjpeg'],
        IMAGETYPE_PNG => ['mime' => 'image/png', 'ext' => 'png', 'label' => 'PNG', 'flag' => IMG_PNG, 'reader' => 'imagecreatefrompng'],
        IMAGETYPE_WEBP => ['mime' => 'image/webp', 'ext' => 'webp', 'label' => 'WebP', 'flag' => IMG_WEBP, 'reader' => 'imagecreatefromwebp'],
    ];

    public function normalize(string $path): NormalizedImage
    {
        $info = @getimagesize($path);

        if ($info === false || ! isset(self::FORMATS[$info[2]])) {
            throw new ImageRejected('File gambar rusak atau formatnya tidak didukung (gunakan JPEG, PNG, atau WebP).');
        }

        [$width, $height, $type] = $info;
        $format = self::FORMATS[$type];

        if ($width * $height > (int) config('pixora.assignments.max_pixels', 40_000_000)) {
            throw new ImageRejected('Resolusi gambar terlalu besar. Perkecil gambar lalu unggah lagi.');
        }

        if (! self::supports($type)) {
            throw new ImageRejected("Server belum mendukung gambar {$format['label']}. Gunakan format lain (mis. PNG).");
        }

        $image = @($format['reader'])($path);

        if (! $image instanceof GdImage) {
            throw new ImageRejected('File gambar rusak dan tidak dapat dibaca.');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        if ($type === IMAGETYPE_JPEG) {
            $image = $this->applyExifOrientation($image, $path);
        }

        $image = $this->fit($image, (int) config('pixora.assignments.max_dimension', 3000));
        $thumbnail = $this->fit($image, (int) config('pixora.assignments.thumbnail_dimension', 480), copy: true);

        return new NormalizedImage(
            contents: $this->encode($image, $type),
            mime: $format['mime'],
            extension: $format['ext'],
            width: imagesx($image),
            height: imagesy($image),
            thumbnail: $this->encode($thumbnail, $type),
        );
    }

    /**
     * Whether this PHP/GD build can read and write the format.
     */
    public static function supports(int $imageType): bool
    {
        $format = self::FORMATS[$imageType] ?? null;

        return $format !== null && function_exists($format['reader']) && (imagetypes() & $format['flag']) !== 0;
    }

    private function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /**
     * Downscale so the longest side is at most $max (never upscale).
     */
    private function fit(GdImage $image, int $max, bool $copy = false): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $max / max($width, $height));

        if ($scale >= 1 && ! $copy) {
            return $image;
        }

        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, (int) imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $target;
    }

    private function encode(GdImage $image, int $type): string
    {
        ob_start();

        if ($type === IMAGETYPE_JPEG) {
            imagejpeg($image, null, (int) config('pixora.assignments.jpeg_quality', 85));
        } elseif ($type === IMAGETYPE_WEBP) {
            imagewebp($image, null, (int) config('pixora.assignments.webp_quality', 85));
        } else {
            imagesavealpha($image, true);
            imagepng($image, null, 6);
        }

        return (string) ob_get_clean();
    }
}
