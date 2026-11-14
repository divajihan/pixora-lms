<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Database\Factories\AssignmentFactory;
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
 * An image assignment on a lesson, or the course's final project (lesson_id null).
 * Deadline rules live in ComputeAssignmentDeadline.
 *
 * @property int $id
 * @property int $course_id
 * @property int|null $lesson_id
 * @property string $title
 * @property string|null $instructions_html Sanitised (HtmlSanitizer "rich_text").
 * @property int $due_offset_days
 * @property bool $is_final_project
 * @property int|null $final_project_key course_id when final project (unique), maintained on save.
 * @property bool $is_required Stored only (Phase 6).
 * @property int $max_images
 * @property AssignmentStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['course_id', 'lesson_id', 'title', 'instructions_html', 'due_offset_days', 'is_final_project', 'is_required', 'max_images', 'status'])]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'is_final_project' => 'boolean',
            'is_required' => 'boolean',
            'due_offset_days' => 'integer',
            'max_images' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Assignment $assignment): void {
            $assignment->is_final_project = $assignment->lesson_id === null;
            $assignment->final_project_key = $assignment->is_final_project ? $assignment->course_id : null;
        });
    }

    /**
     * @param  Builder<Assignment>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', AssignmentStatus::Published);
    }

    /**
     * Effective image limit: the assignment's own, capped by the config maximum.
     */
    public function imageLimit(): int
    {
        return max(1, min($this->max_images, (int) config('pixora.assignments.max_images', 5)));
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * @return HasMany<AssignmentDeadlineOverride, $this>
     */
    public function deadlineOverrides(): HasMany
    {
        return $this->hasMany(AssignmentDeadlineOverride::class);
    }
}
