<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Support\CertificateSettings;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders a certificate to PDF bytes from the single Blade template
 * resources/views/certificates/default.blade.php (A4 landscape, Dompdf).
 * Change the design by editing that view only.
 */
class CertificatePdfRenderer
{
    public const VIEW = 'certificates.default';

    public function __construct(private readonly CertificateSettings $settings) {}

    public function render(Certificate $certificate): string
    {
        return Pdf::loadView(self::VIEW, $this->data($certificate))
            ->setPaper('a4', 'landscape')
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'isFontSubsettingEnabled' => true])
            ->output();
    }

    /**
     * View data. Every value is plain text: the view escapes it ({{ }}).
     *
     * @return array<string, mixed>
     */
    public function data(Certificate $certificate): array
    {
        $url = $certificate->verificationUrl();
        $name = $certificate->recipient_name;
        $length = mb_strlen($name);

        return [
            'settings' => $this->settings->all(),
            'recipientName' => $name,
            // Long names shrink instead of breaking the layout.
            'nameSize' => match (true) {
                $length <= 28 => 34,
                $length <= 45 => 28,
                $length <= 70 => 22,
                default => 17,
            },
            'courseTitle' => $certificate->course_title,
            'batchName' => $certificate->batch_name,
            'issuedDate' => $certificate->issued_at->translatedFormat('j F Y'),
            'number' => $certificate->certificate_number,
            'code' => $certificate->verification_code,
            'verificationUrl' => $url,
            'qrDataUri' => 'data:image/svg+xml;base64,'.base64_encode($this->qrSvg($url)),
        ];
    }

    private function qrSvg(string $url): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }
}
