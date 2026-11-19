<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Certificates\IssueCertificateManually;
use App\Actions\Certificates\ListAdminCertificates;
use App\Actions\Certificates\RevokeCertificate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IssueCertificateRequest;
use App\Http\Requests\Admin\RevokeCertificateRequest;
use App\Http\Resources\Admin\AdminCertificateResource;
use App\Jobs\GenerateCertificatePdf;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Certificate list (staff + admin: certificates.view) and admin actions
 * (certificates.manage): revoke, manual issue, regenerate PDF.
 */
class CertificateController extends Controller
{
    public function index(Request $request, ListAdminCertificates $list): Response
    {
        Gate::authorize('viewAny', Certificate::class);

        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'status' => in_array($request->query('status'), ['aktif', 'dicabut'], true) ? (string) $request->query('status') : null,
            'course' => $request->integer('kursus') ?: null,
            'batch' => $request->integer('batch') ?: null,
        ];

        $certificates = $list->handle($filters)
            ->through(fn (Certificate $certificate) => AdminCertificateResource::make($certificate)->resolve());

        return Inertia::render('admin/certificates/index', [
            'certificates' => $certificates,
            'filters' => [
                'q' => $filters['q'],
                'status' => $filters['status'],
                'kursus' => $filters['course'],
                'batch' => $filters['batch'],
            ],
            'courses' => Course::query()->orderBy('title')->get(['id', 'title'])
                ->map(fn (Course $course) => ['id' => $course->id, 'title' => $course->title]),
            'batches' => $filters['course'] === null ? [] : Batch::query()
                ->where('course_id', $filters['course'])
                ->orderByDesc('starts_on')
                ->get(['id', 'name'])
                ->map(fn (Batch $batch) => ['id' => $batch->id, 'name' => $batch->name]),
            'can' => [
                'manage' => Gate::allows('manage', Certificate::class),
                'settings' => $request->user()?->can('settings.manage') ?? false,
            ],
        ]);
    }

    public function store(IssueCertificateRequest $request, IssueCertificateManually $issue): RedirectResponse
    {
        $student = User::where('email', $request->string('email')->toString())->first();
        $enrollment = $student === null ? null : Enrollment::query()
            ->where('user_id', $student->id)
            ->where('course_id', $request->integer('course_id'))
            ->first();

        if ($enrollment === null) {
            throw ValidationException::withMessages(['email' => 'Siswa dengan email ini tidak terdaftar di kursus tersebut.']);
        }

        /** @var User $admin */
        $admin = $request->user();
        $certificate = $issue->handle($enrollment, $request->string('recipient_name')->toString(), $request->string('reason')->toString(), $admin);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sertifikat {$certificate->certificate_number} diterbitkan."]);

        return back();
    }

    public function revoke(RevokeCertificateRequest $request, Certificate $certificate, RevokeCertificate $revoke): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $revoke->handle($certificate, $request->string('reason')->toString(), $admin);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sertifikat {$certificate->certificate_number} dicabut."]);

        return back();
    }

    public function regenerate(Certificate $certificate): RedirectResponse
    {
        Gate::authorize('manage', Certificate::class);

        GenerateCertificatePdf::dispatch($certificate);

        Inertia::flash('toast', ['type' => 'success', 'message' => "PDF {$certificate->certificate_number} sedang dibuat ulang."]);

        return back();
    }
}
