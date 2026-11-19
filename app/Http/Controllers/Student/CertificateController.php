<?php

namespace App\Http\Controllers\Student;

use App\Actions\Certificates\BuildCertificateCard;
use App\Actions\Certificates\ClaimCertificate;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimCertificateRequest;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Students: claim the certificate of a course and "Sertifikat saya".
 */
class CertificateController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $certificates = Certificate::query()
            ->active()
            ->where('user_id', $user->id)
            ->with('course:id,slug')
            ->latest('issued_at')
            ->get()
            ->map(fn (Certificate $certificate) => [
                ...BuildCertificateCard::certificate($certificate),
                'course_slug' => $certificate->course?->slug,
            ]);

        return Inertia::render('student/certificates/index', ['certificates' => $certificates]);
    }

    public function claim(ClaimCertificateRequest $request, Course $course, ClaimCertificate $claim): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $enrollment = $user->enrollments()->where('course_id', $course->id)->first();
        abort_if($enrollment === null, 404);
        Gate::authorize('claim', [Certificate::class, $enrollment]);

        $certificate = $claim->handle($enrollment, $request->string('recipient_name')->toString());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $certificate->wasRecentlyCreated
                ? "Sertifikat {$certificate->certificate_number} diterbitkan. PDF sedang disiapkan."
                : 'Sertifikat Anda sudah diterbitkan sebelumnya.',
        ]);

        return to_route('learn.course', $course);
    }
}
