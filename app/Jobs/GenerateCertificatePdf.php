<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Services\Certificates\CertificatePdfRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Renders the certificate PDF onto the private disk under a random name and
 * stores pdf_path. Retried 3 times with backoff; an older file (regenerate) is
 * removed only after the new one is saved.
 */
class GenerateCertificatePdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Certificate $certificate) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(CertificatePdfRenderer $renderer): void
    {
        $certificate = $this->certificate->fresh();

        if ($certificate === null) {
            return;
        }

        $disk = Storage::disk((string) config('pixora.certificates.disk'));
        $path = 'certificates/'.$certificate->issued_at->format('Y').'/'.Str::random(40).'.pdf';

        if (! $disk->put($path, $renderer->render($certificate))) {
            throw new RuntimeException("Could not store the PDF of certificate {$certificate->id}.");
        }

        $old = $certificate->pdf_path;
        $certificate->forceFill(['pdf_path' => $path, 'pdf_generated_at' => now()])->save();

        if ($old !== null && $old !== $path) {
            $disk->delete($old);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Certificate PDF failed', [
            'certificate_id' => $this->certificate->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
