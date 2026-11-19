{{--
    Certificate PDF template (Dompdf, A4 landscape 297 x 210 mm).
    The ONLY file to edit to change the design. Rules for Dompdf:
    - inline <style> only (no Tailwind/Vite), no flexbox/grid: use tables and fixed positions;
    - font "DejaVu Sans" (bundled with Dompdf; covers Indonesian characters);
    - always print values with {{ }} (escaped). Never {!! !!} for certificate data.
    Variables: $settings (institution_name, title, statement, signer_name, signer_title),
    $recipientName, $nameSize (pt), $courseTitle, $batchName (nullable), $issuedDate,
    $number, $code, $verificationUrl, $qrDataUri.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $settings['title'] }} {{ $number }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #1f2937; }
        .page { position: relative; width: 297mm; height: 209mm; }
        .frame { position: absolute; top: 10mm; left: 10mm; right: 10mm; bottom: 10mm; border: 2.5pt solid #1e3a8a; }
        .inner { position: absolute; top: 13mm; left: 13mm; right: 13mm; bottom: 13mm; border: 0.75pt solid #93c5fd; }
        .content { position: absolute; top: 26mm; left: 30mm; right: 30mm; text-align: center; }
        .institution { font-size: 12pt; letter-spacing: 3pt; text-transform: uppercase; color: #1e3a8a; font-weight: bold; }
        .title { font-size: 30pt; font-weight: bold; color: #111827; margin-top: 7mm; }
        .given { font-size: 11pt; color: #4b5563; margin-top: 8mm; }
        .name { font-weight: bold; color: #1e3a8a; margin-top: 4mm; line-height: 1.2; word-wrap: break-word; }
        .rule { width: 120mm; margin: 4mm auto 0; border-top: 0.75pt solid #9ca3af; }
        .statement { font-size: 11pt; color: #4b5563; margin-top: 5mm; }
        .course { font-size: 17pt; font-weight: bold; margin-top: 2mm; line-height: 1.25; word-wrap: break-word; }
        .batch { font-size: 10.5pt; color: #4b5563; margin-top: 1.5mm; }
        .footer { position: absolute; left: 30mm; right: 30mm; bottom: 24mm; }
        .footer table { width: 100%; border-collapse: collapse; }
        .footer td { vertical-align: bottom; font-size: 9pt; color: #374151; }
        .label { color: #6b7280; font-size: 8pt; text-transform: uppercase; letter-spacing: 1pt; }
        .signer-name { font-size: 11pt; font-weight: bold; border-top: 0.75pt solid #374151; padding-top: 1.5mm; }
        .url { font-size: 7.5pt; color: #1e3a8a; word-wrap: break-word; }
    </style>
</head>
<body>
<div class="page">
    <div class="frame"></div>
    <div class="inner"></div>

    <div class="content">
        <div class="institution">{{ $settings['institution_name'] }}</div>
        <div class="title">{{ $settings['title'] }}</div>
        <div class="given">Diberikan kepada</div>
        <div class="name" style="font-size: {{ $nameSize }}pt;">{{ $recipientName }}</div>
        <div class="rule"></div>
        <div class="statement">{{ $settings['statement'] }}</div>
        <div class="course">{{ $courseTitle }}</div>
        @if ($batchName)
            <div class="batch">{{ $batchName }}</div>
        @endif
    </div>

    <div class="footer">
        <table>
            <tr>
                <td style="width: 38%;">
                    <div class="label">Tanggal terbit</div>
                    <div style="font-size: 10.5pt; margin-bottom: 3mm;">{{ $issuedDate }}</div>
                    <div class="label">Nomor sertifikat</div>
                    <div style="font-size: 10.5pt;">{{ $number }}</div>
                </td>
                <td style="width: 24%; text-align: center;">
                    <img src="{{ $qrDataUri }}" alt="QR verifikasi" style="width: 24mm; height: 24mm;">
                    <div class="label" style="margin-top: 1mm;">Kode: {{ $code }}</div>
                    <div class="url">{{ $verificationUrl }}</div>
                </td>
                <td style="width: 38%; text-align: center;">
                    <div style="height: 14mm;"></div>
                    <div class="signer-name">{{ $settings['signer_name'] }}</div>
                    <div>{{ $settings['signer_title'] }}</div>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
