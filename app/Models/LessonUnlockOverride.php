<?php

namespace App\Models;

use Database\Factories\LessonUnlockOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Custom unlock date for a lesson in a batch (enrollment_id null) or for one
 * enrollment. Write through SetLessonUnlockOverride (never re-locks).
 *
 * @property int $id
 * @property int $lesson_id
 * @property int $batch_id
 * @property int|null $enrollment_id
 * @property Carbon $unlock_at
 * @property int|null $created_by
 * @property int $scope_key enrollment_id ?? 0 (set on save; part of the unique key).
 */
#[Fillable(['lesson_id', 'batch_id', 'enrollment_id', 'unlock_at', 'created_by'])]
class LessonUnlockOverride extends Model
{
    /** @use HasFactory<LessonUnlockOverrideFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['unlock_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Keeps the NULL-safe unique key (lesson_id, batch_id, scope_key) in sync.
        static::saving(function (LessonUnlockOverride $override): void {
            $override->scope_key = $override->enrollment_id ?? 0;
        });
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
