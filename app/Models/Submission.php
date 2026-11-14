<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A student's images for one assignment (one per enrollment, no resubmission).
 *
 * @property int $id
 * @property int $assignment_id
 * @property int $enrollment_id
 * @property string|null $body
 * @property SubmissionStatus $status
 * @property Carbon $submitted_at
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property Carbon|null $student_seen_feedback_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $comments_count
 * @property string|null $comments_max_created_at
 */
#[Fillable(['assignment_id', 'enrollment_id', 'body', 'status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'student_seen_feedback_at'])]
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'student_seen_feedback_at' => 'datetime',
        ];
    }

    /**
     * True when there is a comment the student has not seen yet.
     * Needs withMax('comments', 'created_at') (or loads it).
     */
    public function hasUnseenFeedback(): bool
    {
        $latest = $this->comments_max_created_at ?? $this->comments()->max('created_at');

        if ($latest === null) {
            return false;
        }

        return $this->student_seen_feedback_at === null || Carbon::parse($latest)->gt($this->student_seen_feedback_at);
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('id');
    }

    /**
     * @return HasMany<SubmissionComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(SubmissionComment::class)->orderBy('created_at')->orderBy('id');
    }
}
