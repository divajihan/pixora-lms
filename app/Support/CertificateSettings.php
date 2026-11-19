<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Certificate texts printed on the PDF (institution, title, statement, signer).
 * Stored in the settings table as "certificate.<field>"; config
 * pixora.certificates.defaults fills anything not saved yet. Cached until saved.
 */
class CertificateSettings
{
    public const FIELDS = ['institution_name', 'title', 'statement', 'signer_name', 'signer_title'];

    private const CACHE_KEY = 'settings:certificate';

    /**
     * @return array{institution_name: string, title: string, statement: string, signer_name: string, signer_title: string}
     */
    public function all(): array
    {
        /** @var array<string, string> $stored */
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
            ->whereIn('key', array_map(fn (string $field) => "certificate.{$field}", self::FIELDS))
            ->get()
            ->mapWithKeys(fn (Setting $setting) => [substr($setting->key, strlen('certificate.')) => (string) $setting->value])
            ->all());

        /** @var array<string, string> $defaults */
        $defaults = config('pixora.certificates.defaults');
        $values = [];

        foreach (self::FIELDS as $field) {
            $value = $stored[$field] ?? '';
            $values[$field] = $value !== '' ? $value : (string) ($defaults[$field] ?? '');
        }

        /** @var array{institution_name: string, title: string, statement: string, signer_name: string, signer_title: string} $values */
        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    public function save(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach (self::FIELDS as $field) {
                if (array_key_exists($field, $values)) {
                    Setting::updateOrCreate(['key' => "certificate.{$field}"], ['value' => trim($values[$field])]);
                }
            }
        });

        Cache::forget(self::CACHE_KEY);
    }
}
