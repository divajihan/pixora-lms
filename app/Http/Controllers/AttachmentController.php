<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The ONLY way to read submission/comment images. Files live on a private
 * disk; every request checks AttachmentPolicy (student owner, instructor of
 * the course, staff, admin — others 404).
 */
class AttachmentController extends Controller
{
    public function show(Attachment $attachment): StreamedResponse
    {
        return $this->respond($attachment, $attachment->path, 'inline');
    }

    public function thumbnail(Attachment $attachment): StreamedResponse
    {
        return $this->respond($attachment, $attachment->thumbnail_path ?? $attachment->path, 'inline');
    }

    /**
     * "Unduh asli": the stored (normalised, metadata-free) file as a download.
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        return $this->respond($attachment, $attachment->path, 'attachment');
    }

    private function respond(Attachment $attachment, string $path, string $disposition): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($path), 404);

        $name = $this->downloadName($attachment);

        return $disk->response($path, $name, [
            'Content-Type' => $attachment->mime,
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ], $disposition);
    }

    /**
     * Client name with the stored extension (the file was re-encoded).
     */
    private function downloadName(Attachment $attachment): string
    {
        $extension = pathinfo($attachment->path, PATHINFO_EXTENSION);
        $base = pathinfo($attachment->original_name, PATHINFO_FILENAME) ?: 'gambar';

        return $base.'.'.$extension;
    }
}
