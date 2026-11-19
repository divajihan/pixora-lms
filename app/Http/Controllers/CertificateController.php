<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateCertificatePdf;
use App\Models\Certificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way to read a certificate PDF (private disk, CertificatePolicy:
 * owner, staff/admin, instructors of the course). A missing file (job still
 * queued, or lost) re-queues the job and tells the user to try again shortly.
 */
class CertificateController extends Controller
{
    public function download(Certificate $certificate): StreamedResponse|RedirectResponse
    {
        Gate::authorize('download', $certificate);

        $disk = Storage::disk((string) config('pixora.certificates.disk'));

        if ($certificate->pdf_path === null || ! $disk->exists($certificate->pdf_path)) {
            // Queued at issue time; re-queue when the file is lost or the job seems to have failed.
            if ($certificate->pdf_path !== null || $certificate->created_at?->lt(now()->subMinutes(2))) {
                GenerateCertificatePdf::dispatch($certificate);
            }

            Inertia::flash('toast', ['type' => 'info', 'message' => 'PDF sertifikat sedang diproses. Coba lagi dalam beberapa saat.']);

            return back();
        }

        return $disk->download($certificate->pdf_path, "Sertifikat-{$certificate->certificate_number}.pdf", [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
