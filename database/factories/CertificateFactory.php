<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: an active certificate of a new enrollment, PDF not generated yet.
 * Real certificates are created by ClaimCertificate / IssueCertificateManually.
 *
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'active_enrollment_id' => fn (array $attributes) => $attributes['enrollment_id'],
            'user_id' => fn (array $attributes) => self::enrollment($attributes)?->user_id,
            'course_id' => fn (array $attributes) => self::enrollment($attributes)?->course_id,
            'batch_id' => fn (array $attributes) => self::enrollment($attributes)?->batch_id,
            'certificate_number' => 'PXR-CERT-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(900000, 999999), 6, '0', STR_PAD_LEFT),
            'verification_code' => fake()->unique()->regexify('[A-HJ-NP-Z2-9]{12}'),
            'recipient_name' => fake()->name(),
            'course_title' => fake()->sentence(3),
            'batch_name' => null,
            'issued_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function enrollment(array $attributes): ?Enrollment
    {
        return Enrollment::whereKey($attributes['enrollment_id'])->first();
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'active_enrollment_id' => null,
            'revoked_at' => now(),
            'revoke_reason' => 'Nama salah ketik.',
        ]);
    }
}
