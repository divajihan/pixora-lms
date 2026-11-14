<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * Real image bytes for upload tests (GD-generated), with optional metadata
 * that must NOT survive normalisation.
 */
class TestImages
{
    public const GPS_MARKER = 'GPSLatitude-PIXORA-SECRET';

    public static function png(int $width = 40, int $height = 30, bool $withMetadata = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 120, 200));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();

        return $withMetadata ? self::injectPngChunks($bytes) : $bytes;
    }

    /**
     * JPEG with an EXIF APP1 segment carrying a fake GPS marker. Needs GD JPEG
     * support (CI); callers skip otherwise.
     */
    public static function jpegWithExif(int $width = 40, int $height = 30): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 80, 40));
        ob_start();
        imagejpeg($image);
        $bytes = (string) ob_get_clean();

        $payload = "Exif\0\0".'MM'."\0\x2A\0\0\0\x08\0\0".self::GPS_MARKER;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($bytes, 0, 2).$segment.substr($bytes, 2); // right after SOI
    }

    public static function upload(string $bytes, string $name = 'karya.png', string $mime = 'image/png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pxr');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $mime, null, true);
    }

    public static function pngUpload(string $name = 'karya.png', int $width = 40, int $height = 30, bool $withMetadata = false): UploadedFile
    {
        return self::upload(self::png($width, $height, $withMetadata), $name);
    }

    public static function gdSupportsJpeg(): bool
    {
        return function_exists('imagecreatefromjpeg') && (imagetypes() & IMG_JPG) !== 0;
    }

    /**
     * Adds tEXt and eXIf chunks (GPS marker) after IHDR.
     */
    private static function injectPngChunks(string $png): string
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $ihdrEnd = 8 + 4 + 4 + 13 + 4;

        return substr($png, 0, $ihdrEnd)
            .$chunk('tEXt', 'Comment'."\0".self::GPS_MARKER)
            .$chunk('eXIf', 'MM'."\0\x2A\0\0\0\x08".self::GPS_MARKER)
            .substr($png, $ihdrEnd);
    }
}
