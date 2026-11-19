<?php

namespace App\Http\Controllers\Public;

use App\Actions\Certificates\CreateCertificate;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public certificate verification (/verifikasi, /verifikasi/{code}).
 * Shows only what is printed on the certificate (snapshot name, course, batch,
 * date, number) and its status — never email, phone or account data.
 * Unknown code = a neutral "tidak ditemukan" page (404). Throttled; noindex.
 */
class CertificateVerificationController extends Controller
{
    public function form(Request $request): Response|RedirectResponse
    {
        if ($request->filled('kode')) {
            $input = (string) $request->query('kode');
            $code = CreateCertificate::normaliseCode($input)
                ?? substr((string) preg_replace('/[^A-Za-z0-9\-]/', '', $input), 0, 40);

            if ($code !== '') {
                return to_route('certificates.verify', ['code' => $code]);
            }
        }

        return $this->render($request, 'public/certificates/verify', [
            'code' => null,
            'result' => null,
        ]);
    }

    public function show(Request $request, string $code): Response
    {
        $normalised = CreateCertificate::normaliseCode($code);
        $certificate = $normalised === null ? null : Certificate::where('verification_code', $normalised)->first();

        $response = $this->render($request, 'public/certificates/verify', [
            'code' => $normalised ?? $code,
            'result' => $certificate === null ? ['status' => 'not_found'] : [
                'status' => $certificate->isRevoked() ? 'revoked' : 'valid',
                'number' => $certificate->certificate_number,
                'recipient_name' => $certificate->recipient_name,
                'course_title' => $certificate->course_title,
                'batch_name' => $certificate->batch_name,
                'issued_at' => $certificate->issued_at->toIso8601String(),
                'revoked_at' => $certificate->revoked_at?->toIso8601String(),
            ],
        ]);

        return $certificate === null ? $response->setStatusCode(404) : $response;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(Request $request, string $component, array $props): Response
    {
        $response = Inertia::render($component, [
            'meta' => [
                'title' => 'Verifikasi Sertifikat',
                'description' => 'Periksa keaslian sertifikat '.config('app.name').' dengan kode verifikasi.',
                'noindex' => true,
            ],
            ...$props,
        ])->toResponse($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
