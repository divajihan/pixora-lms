<?php

namespace App\Actions\Certificates;

use App\Models\Certificate;
use Carbon\CarbonImmutable;

/**
 * Result of CheckCertificateEligibility for one enrollment.
 *
 * - blocker: a reason no requirement can fix (certificates disabled, access
 *   revoked/expired, ...); null when none.
 * - requirements: what the student has to do, each with met + an Indonesian
 *   label ("Selesaikan semua lesson (3 dari 5)").
 * - certificate: the active certificate when already claimed/issued.
 */
final readonly class CertificateEligibility
{
    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_CLAIMABLE = 'claimable';

    public const STATUS_NOT_ELIGIBLE = 'not_eligible';

    /**
     * @param  list<array{key: string, label: string, met: bool}>  $requirements
     */
    public function __construct(
        public bool $enabled,
        public ?string $blocker,
        public array $requirements,
        public int $lessonsCompleted,
        public int $lessonsTotal,
        public int $assignmentsSubmitted,
        public int $assignmentsTotal,
        public ?CarbonImmutable $opensAt,
        public ?Certificate $certificate,
    ) {}

    public function requirementsMet(): bool
    {
        foreach ($this->requirements as $requirement) {
            if (! $requirement['met']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Can claim now (no blocker, all requirements met, not claimed yet).
     */
    public function eligible(): bool
    {
        return $this->certificate === null && $this->blocker === null && $this->requirementsMet();
    }

    public function status(): string
    {
        return match (true) {
            $this->certificate !== null => self::STATUS_CLAIMED,
            $this->eligible() => self::STATUS_CLAIMABLE,
            default => self::STATUS_NOT_ELIGIBLE,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_CLAIMED => 'Sudah diklaim',
            self::STATUS_CLAIMABLE => 'Bisa diklaim',
            default => 'Belum memenuhi syarat',
        };
    }

    /**
     * Why a claim is refused (Indonesian), or null when eligible.
     */
    public function reason(): ?string
    {
        if ($this->certificate !== null) {
            return 'Sertifikat untuk kursus ini sudah diterbitkan.';
        }

        if ($this->blocker !== null) {
            return $this->blocker;
        }

        foreach ($this->requirements as $requirement) {
            if (! $requirement['met']) {
                return 'Syarat belum terpenuhi: '.$requirement['label'].'.';
            }
        }

        return null;
    }

    /**
     * Props for the student's certificate card (no certificate data).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'status' => $this->status(),
            'eligible' => $this->eligible(),
            'blocker' => $this->blocker,
            'reason' => $this->reason(),
            'requirements' => $this->requirements,
            'opens_at' => $this->opensAt?->toIso8601String(),
        ];
    }
}
