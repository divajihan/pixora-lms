<?php

namespace App\Models;

use App\Enums\LessonStatus;
use App\Enums\LessonType;
use App\Observers\LessonObserver;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property int $module_id
 * @property string $title
 * @property string $slug
 * @property LessonType $type
 * @property string|null $video_provider
 * @property string|null $video_ref Provider video id. Never send to students before the lesson is unlocked.
 * @property int|null $duration_seconds
 * @property string|null $summary_html Sanitised HTML.
 * @property int $drip_day_offset
 * @property int $position
 * @property LessonStatus $status
 * @property bool $is_preview
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'course_id', 'module_id', 'title', 'slug', 'type', 'video_provider', 'video_ref',
    'duration_seconds', 'summary_html', 'drip_day_offset', 'position', 'status', 'is_preview',
])]
// Defence in depth: video_ref is never serialised implicitly (e.g. model passed straight to Inertia).
#[Hidden(['video_ref'])]
#[ObservedBy(LessonObserver::class)]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LessonType::class,
            'status' => LessonStatus::class,
            'duration_seconds' => 'integer',
            'drip_day_offset' => 'integer',
            'position' => 'integer',
            'is_preview' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Lesson>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', LessonStatus::Published);
    }

    /**
     * @param  Builder<Lesson>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return HasMany<LessonUnlockOverride, $this>
     */
    public function unlockOverrides(): HasMany
    {
        return $this->hasMany(LessonUnlockOverride::class);
    }

    /**
     * Human day label for the drip schedule: offset 0 = "Hari ke-1".
     */
    public function dayNumber(): int
    {
        return $this->drip_day_offset + 1;
    }

    /**
     * @return HasOne<LiveSession, $this>
     */
    public function liveSession(): HasOne
    {
        return $this->hasOne(LiveSession::class);
    }

    /**
     * @return HasOne<Assignment, $this>
     */
    public function assignment(): HasOne
    {
        return $this->hasOne(Assignment::class);
    }
}
