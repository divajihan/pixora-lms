<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A stored (normalised) image on the private disk. Never expose disk/path:
 * the frontend only gets AttachmentController URLs.
 *
 * @property int $id
 * @property string $attachable_type Morph alias: submission | submission_comment.
 * @property int $attachable_id
 * @property string $disk
 * @property string $path
 * @property string|null $thumbnail_path
 * @property string $original_name Client file name only (never a path).
 * @property string $mime
 * @property int $size
 * @property int $width
 * @property int $height
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['disk', 'path', 'thumbnail_path', 'original_name', 'mime', 'size', 'width', 'height', 'uploaded_by'])]
#[Hidden(['disk', 'path', 'thumbnail_path'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The submission this file belongs to (directly, or through a comment —
     * including soft-deleted comments, whose files stay reachable to staff).
     */
    public function owningSubmission(): ?Submission
    {
        return match ($this->attachable_type) {
            'submission' => Submission::find($this->attachable_id),
            'submission_comment' => SubmissionComment::withTrashed()->find($this->attachable_id)?->submission,
            default => null,
        };
    }

    /**
     * Paths to remove from the disk when this row goes away.
     *
     * @return list<string>
     */
    public function storedPaths(): array
    {
        return array_values(array_filter([$this->path, $this->thumbnail_path]));
    }
}
