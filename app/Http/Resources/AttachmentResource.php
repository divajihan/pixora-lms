<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An image as the browser sees it: only policy-checked controller URLs,
 * never the disk or storage path.
 *
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'url' => route('attachments.show', $this->resource),
            'thumbnail_url' => route('attachments.thumbnail', $this->resource),
            'download_url' => route('attachments.download', $this->resource),
        ];
    }
}
