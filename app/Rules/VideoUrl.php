<?php

namespace App\Rules;

use App\Services\Video\VideoProvider;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts only URLs/ids the configured VideoProvider (YouTube) can parse.
 */
class VideoUrl implements ValidationRule
{
    public function __construct(private readonly VideoProvider $provider) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $this->provider->parseReference($value) === null) {
            $fail('URL video harus berupa tautan YouTube yang valid (youtube.com/watch, youtu.be, embed, atau shorts).');
        }
    }
}
