<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CertificateSettingsRequest;
use App\Support\CertificateSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Certificate texts (settings.manage = admin). Applies to PDFs generated
 * afterwards; existing PDFs change only when regenerated.
 */
class CertificateSettingsController extends Controller
{
    public function edit(CertificateSettings $settings): Response
    {
        return Inertia::render('admin/certificates/settings', ['settings' => $settings->all()]);
    }

    public function update(CertificateSettingsRequest $request, CertificateSettings $settings): RedirectResponse
    {
        /** @var array<string, string> $values */
        $values = $request->validated();
        $settings->save($values);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengaturan sertifikat disimpan.']);

        return back();
    }
}
