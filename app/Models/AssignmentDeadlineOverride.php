<?php

namespace App\Models;

use Database\Factories\AssignmentDeadlineOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A deadline extension for one batch (batch_id) or one student (enrollment_id).
 * Write through SetDeadlineOverride.
 *
 * @property int $id
 * @property int $assignment_id
 * @property int|null $batch_id
 * @property int|null $enrollment_id
 * @property string $scope_key "enrollment:ID" or "batch:ID" (set on save; unique per assignment).
 * @property Carbon $due_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['assignment_id', 'batch_id', 'enrollment_id', 'due_at', 'created_by'])]
class AssignmentDeadlineOverride extends Model
{
    /** @use HasFactory<AssignmentDeadlineOverrideFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (AssignmentDeadlineOverride $override): void {
            $override->scope_key = self::scopeKey($override->batch_id, $override->enrollment_id);
        });
    }

    /**
     * One scope per row: a student override ignores batch_id.
     */
    public static function scopeKey(?int $batchId, ?int $enrollmentId): string
    {
        return match (true) {
            $enrollmentId !== null => 'enrollment:'.$enrollmentId,
            $batchId !== null => 'batch:'.$batchId,
            default => throw new LogicException('A deadline override needs a batch or an enrollment.'),
        };
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
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
