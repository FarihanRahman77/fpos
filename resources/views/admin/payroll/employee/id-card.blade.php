<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ID Card - {{ $employee->name }}</title>
    <style>
        /* CR80 card: 54 x 85.6 mm, portrait. Page 1 = front, page 2 = back. */
        @page { margin: 0; }
        * { margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1e293b; }
        table { border-collapse: collapse; }

        .card { position: relative; width: 54mm; height: 85.6mm; overflow: hidden; }
        .page-break { page-break-after: always; }

        .band { background: #1e3a8a; color: #fff; text-align: center; }
        .band-top { height: 17mm; padding-top: 2.2mm; }
        .band-top img { max-height: 7mm; max-width: 30mm; }
        .company { font-size: 6.6pt; font-weight: bold; letter-spacing: .2pt; margin-top: 0.8mm; padding: 0 2mm; }
        .tagline { font-size: 4.6pt; opacity: .85; padding: 0 2mm; }
        .accent { height: 1.2mm; background: #f59e0b; }

        .photo-wrap { text-align: center; margin-top: -1mm; }
        .photo { width: 21mm; height: 25mm; border: 0.6mm solid #1e3a8a; border-radius: 1.5mm; }
        .photo-empty { display: inline-block; width: 21mm; height: 25mm; border: 0.6mm solid #1e3a8a; border-radius: 1.5mm;
                       background: #e2e8f0; font-size: 18pt; font-weight: bold; color: #64748b; line-height: 25mm; text-align: center; }

        .name { text-align: center; font-size: 8.4pt; font-weight: bold; margin-top: 1.4mm; padding: 0 2mm; color: #0f172a; }
        .designation { text-align: center; font-size: 6pt; color: #1d4ed8; font-weight: bold; text-transform: uppercase; letter-spacing: .3pt; padding: 0 2mm; }

        .info { width: 44mm; margin: 1.8mm auto 0 auto; font-size: 5.6pt; }
        .info td { padding: 0.35mm 0; vertical-align: top; }
        .info td.k { width: 13mm; color: #64748b; }
        .info td.c { width: 2mm; color: #64748b; }
        .info td.v { font-weight: bold; }

        /* dompdf draws line-height text low, so bands use padding instead */
        .band-bottom { position: absolute; bottom: 0; left: 0; width: 54mm; height: 4.4mm; padding-top: 1.4mm; line-height: 1; font-size: 5.4pt; font-weight: bold; letter-spacing: 1pt; }

        /* back */
        .back-head { height: 6mm; padding-top: 2.6mm; line-height: 1; font-size: 7pt; font-weight: bold; letter-spacing: .6pt; }
        .back-body { padding: 2.2mm 3mm 0 3mm; font-size: 5.4pt; line-height: 1.35; }
        .back-body p { margin-bottom: 1.4mm; text-align: center; }
        .back-info { width: 100%; font-size: 5.4pt; margin-bottom: 1.6mm; }
        .back-info td { padding: 0.4mm 0; vertical-align: top; }
        .back-info td.k { width: 15mm; color: #64748b; }
        .back-info td.v { font-weight: bold; }
        .barcode { text-align: center; margin: 1mm 0 0.5mm 0; }
        .barcode img { width: 38mm; height: 7mm; }
        .barcode-text { text-align: center; font-size: 5.4pt; letter-spacing: 1pt; }
        .sign { text-align: center; margin-top: 2mm; }
        .sign img { max-height: 6mm; max-width: 24mm; }
        .sign-space { height: 6mm; }
        .sign-line { width: 26mm; margin: 0 auto; border-top: 0.25mm solid #475569; font-size: 5pt; color: #475569; padding-top: 0.4mm; }
        .return { font-size: 5pt; color: #475569; text-align: center; }
    </style>
</head>
<body>

    {{-- ===================== FRONT ===================== --}}
    <div class="card page-break">
        <div class="band band-top">
            @if ($company['logo'])
                <img src="{{ $company['logo'] }}" alt="">
            @endif
            <div class="company">{{ $company['name'] }}</div>
            @if ($company['tagline'])
                <div class="tagline">{{ $company['tagline'] }}</div>
            @endif
        </div>
        <div class="accent"></div>

        <div class="photo-wrap" style="margin-top: 2.2mm;">
            @if ($photo)
                <img src="{{ $photo }}" class="photo" alt="">
            @else
                <span class="photo-empty">{{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}</span>
            @endif
        </div>

        <div class="name">{{ $employee->name }}</div>
        <div class="designation">{{ $employee->designation }}</div>

        <table class="info">
            <tr><td class="k">ID No</td><td class="c">:</td><td class="v">{{ $employee->employee_code }}</td></tr>
            @if ($employee->department)
                <tr><td class="k">Dept.</td><td class="c">:</td><td class="v">{{ $employee->department }}</td></tr>
            @endif
            @if ($employee->blood_group)
                <tr><td class="k">Blood</td><td class="c">:</td><td class="v" style="color:#dc2626;">{{ $employee->blood_group }}</td></tr>
            @endif
            @if ($employee->phone)
                <tr><td class="k">Mobile</td><td class="c">:</td><td class="v">{{ $employee->phone }}</td></tr>
            @endif
        </table>

        <div class="band band-bottom">EMPLOYEE ID CARD</div>
    </div>

    {{-- ===================== BACK ===================== --}}
    <div class="card">
        <div class="band back-head">{{ $company['short'] ?: 'IDENTITY CARD' }}</div>
        <div class="accent"></div>

        <div class="back-body">
            <table class="back-info">
                @if ($employee->joining_date)
                    <tr><td class="k">Joined</td><td class="v">{{ \Carbon\Carbon::parse($employee->joining_date)->format('d M Y') }}</td></tr>
                @endif
                <tr><td class="k">Valid Till</td><td class="v">{{ $validTill->format('d M Y') }}</td></tr>
                @if ($employee->nid_no)
                    <tr><td class="k">NID</td><td class="v">{{ $employee->nid_no }}</td></tr>
                @endif
                @if ($employee->emergency_contact_phone)
                    <tr>
                        <td class="k">Emergency</td>
                        <td class="v">{{ $employee->emergency_contact_phone }}@if ($employee->emergency_contact_relation) ({{ $employee->emergency_contact_relation }})@endif</td>
                    </tr>
                @endif
            </table>

            <p>This card is the property of <strong>{{ $company['name'] }}</strong> and must be shown on request. It is not transferable.</p>

            <div class="barcode"><img src="{{ $barcode }}" alt=""></div>
            <div class="barcode-text">{{ $employee->employee_code }}</div>

            <table style="width: 100%; margin-top: 1.6mm;">
                <tr>
                    <td class="sign" style="width: 50%; vertical-align: bottom;">
                        @if ($signature)
                            <img src="{{ $signature }}" alt="">
                        @else
                            <div class="sign-space"></div>
                        @endif
                        <div class="sign-line" style="width: 21mm;">Card Holder</div>
                    </td>
                    <td class="sign" style="width: 50%; vertical-align: bottom;">
                        <div class="sign-space"></div>
                        <div class="sign-line" style="width: 21mm;">Authorised</div>
                    </td>
                </tr>
            </table>

            <div class="return" style="margin-top: 2mm;">
                If found, please return to:<br>
                @if ($company['address']){{ $company['address'] }}<br>@endif
                @if ($company['phone'])Phone: {{ $company['phone'] }}@endif
            </div>
        </div>

        <div class="band band-bottom">{{ $company['website'] ?: $company['email'] }}</div>
    </div>

</body>
</html>
