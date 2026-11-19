<?php

namespace App\Support;

use Closure;

/**
 * Name printed on a certificate. Trimmed, inner whitespace collapsed, at most
 * MAX characters and at least one letter (not empty / not only symbols).
 * HTML is never interpreted: the PDF view and the pages escape it.
 */
class RecipientName
{
    public const MAX = 120;

    public static function normalise(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Validation rules for the "recipient_name" field.
     *
     * @return list<mixed>
     */
    public static function rules(): array
    {
        return [
            'required',
            'string',
            'max:'.self::MAX,
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || preg_match('/\p{L}/u', $value) !== 1) {
                    $fail('Nama penerima harus berisi huruf, tidak boleh hanya simbol atau angka.');
                }
            },
        ];
    }
}
