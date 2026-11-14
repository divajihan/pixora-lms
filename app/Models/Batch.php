<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Enums\EnrollmentStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A run of a drip course ("Batch 44", 15–27 Nov). Day 1 = starts_on.
 *
 * @property int $id
 * @property int $course_id
 * @property string $name
 * @property CarbonInterface $starts_on
 * @property CarbonInterface $ends_on
 * @property CarbonInterface|null $registration_closes_on
 * @property int|null $capacity Null = unlimited.
 * @property bool $is_published
 * @property int|null $seats_taken_count From withSeatsTaken().
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['course_id', 'name', 'starts_on', 'ends_on', 'registration_closes_on', 'capacity', 'is_published'])]
class Batch extends Model
{
    /** @use HasFactory<BatchFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Enrollment statuses that occupy a seat.
     */
    public const SEAT_STATUSES = [EnrollmentStatus::Active, EnrollmentStatus::Completed];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'registration_closes_on' => 'date',
            'capacity' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Batch>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Adds seats_taken_count (active + completed enrollments).
     *
     * @param  Builder<Batch>  $query
     */
    #[Scope]
    protected function withSeatsTaken(Builder $query): void
    {
        $query->withCount(['enrollments as seats_taken_count' => fn (Builder $query) => $query->whereIn('status', self::SEAT_STATUSES)]);
    }

    public function status(?CarbonInterface $now = null): BatchStatus
    {
        $today = ($now ?? now())->copy()->startOfDay();

        return match (true) {
            $today->lt($this->starts_on) => BatchStatus::Upcoming,
            $today->lte($this->ends_on) => BatchStatus::Running,
            default => BatchStatus::Finished,
        };
    }

    public function hasStarted(?CarbonInterface $now = null): bool
    {
        return $this->status($now) !== BatchStatus::Upcoming;
    }

    public function seatsTaken(): int
    {
        return $this->seats_taken_count ?? $this->enrollments()->whereIn('status', self::SEAT_STATUSES)->count();
    }

    public function seatsLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->seatsTaken());
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->seatsTaken() >= $this->capacity;
    }

    /**
     * Registration is open while published, not past ends_on (and the optional
     * registration close date) and not full. Dates are inclusive.
     */
    public function isOpenForRegistration(?CarbonInterface $now = null): bool
    {
        $today = ($now ?? now())->copy()->startOfDay();

        return $this->is_published
            && ! $this->trashed()
            && $today->lte($this->ends_on)
            && ($this->registration_closes_on === null || $today->lte($this->registration_closes_on))
            && ! $this->isFull();
    }

    /**
     * Reason registration is closed (Indonesian), or null when open.
     */
    public function registrationBlocker(?CarbonInterface $now = null): ?string
    {
        $today = ($now ?? now())->copy()->startOfDay();

        return match (true) {
            ! $this->is_published || $this->trashed() => 'Batch ini tidak tersedia.',
            $today->gt($this->ends_on) => 'Batch ini sudah selesai.',
            $this->registration_closes_on !== null && $today->gt($this->registration_closes_on) => 'Pendaftaran batch ini sudah ditutup.',
            $this->isFull() => 'Kuota batch ini sudah penuh.',
            default => null,
        };
    }

    /**
     * Moment day N (0-based offset) opens: starts_on + offset days at the
     * configured unlock time, in the app timezone.
     */
    public function dayStart(int $offset): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', (string) config('pixora.learning.unlock_time', '00:00')) + [1 => 0]);

        return CarbonImmutable::parse($this->starts_on->format('Y-m-d'), config('app.timezone'))
            ->addDays($offset)
            ->setTime($hour, $minute);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<LessonUnlockOverride, $this>
     */
    public function unlockOverrides(): HasMany
    {
        return $this->hasMany(LessonUnlockOverride::class);
    }
}
