<?php

namespace App\Http\Resources;

use App\Models\SubmissionComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A feedback comment (load author and attachments).
 *
 * @mixin SubmissionComment
 */
class SubmissionCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => $this->author->name ?? 'Pengguna',
            'role' => $this->author_role->value,
            'role_label' => $this->author_role->label(),
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'is_mine' => $request->user()?->id === $this->user_id,
            'images' => AttachmentResource::collection($this->attachments)->resolve(),
        ];
    }
}
