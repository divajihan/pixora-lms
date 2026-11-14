<?php

namespace App\Rules;

use Closure;
use finfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * An uploaded image checked by its CONTENT (finfo), not its extension or the
 * browser-sent type: JPEG, PNG or WebP only (SVG, PDF, renamed files are
 * refused). Size limit from config pixora.assignments.max_image_kb.
 */
class AllowedImage implements ValidationRule
{
    public const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Gambar gagal diunggah. Coba lagi atau gunakan file yang lebih kecil.');

            return;
        }

        $maxKb = (int) config('pixora.assignments.max_image_kb', 10240);

        if ($value->getSize() > $maxKb * 1024) {
            $fail('Ukuran setiap gambar maksimal '.self::humanSize($maxKb).'.');

            return;
        }

        $path = $value->getRealPath();
        $mime = $path !== false ? (new finfo(FILEINFO_MIME_TYPE))->file($path) : false;

        if (! in_array($mime, self::MIMES, true)) {
            $fail('File harus berupa gambar JPEG, PNG, atau WebP.');
        }
    }

    public static function humanSize(int $kb): string
    {
        return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1, ',', ''), '0'), ',').' MB' : $kb.' KB';
    }
}
