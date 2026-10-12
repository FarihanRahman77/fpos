<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Employee Profile - {{ $employee->name }}</title>
    @php
        $d = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d M Y') : '-';
        $v = fn ($x) => ($x === null || $x === '') ? '-' : $x;
        $m = fn ($x) => number_format((float) $x, 2);
    @endphp
    <style>
        @page { margin: 26px 30px 50px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1e293b; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        .bold { font-weight: bold; }

        .company-name { font-size: 16px; font-weight: bold; color: #0f172a; }
        .doc-title { font-size: 17px; font-weight: bold; color: #1e3a8a; letter-spacing: 1px; }
        .rule { border-top: 3px solid #1e3a8a; margin: 8px 0 12px 0; }

        .hero { background: #f1f5f9; border: 1px solid #e2e8f0; }
        .hero td { vertical-align: top; padding: 10px; }
        .photo { width: 105px; height: 125px; border: 3px solid #fff; }
        .photo-empty { width: 105px; height: 125px; background: #cbd5e1; color: #475569; font-size: 40px; font-weight: bold; text-align: center; line-height: 125px; }
        .hero-name { font-size: 17px; font-weight: bold; color: #0f172a; }
        .hero-role { font-size: 11px; color: #1d4ed8; font-weight: bold; margin-top: 2px; }
        .pill { display: inline-block; padding: 2px 7px; border-radius: 8px; font-size: 8.5px; font-weight: bold; }
        .pill-on { background: #dcfce7; color: #166534; }
        .pill-off { background: #e2e8f0; color: #475569; }
        .hero-meta td { padding: 2px 10px 2px 0; vertical-align: top; }

        h3 { font-size: 10.5px; text-transform: uppercase; letter-spacing: .6px; color: #1e3a8a; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px; margin: 14px 0 6px 0; }

        .kv td { padding: 3px 6px 3px 0; vertical-align: top; }
        .kv td.k { width: 22%; color: #64748b; }
        .kv td.v { width: 28%; }

        .grid th { background: #1e293b; color: #fff; padding: 5px 6px; font-size: 8.5px; text-transform: uppercase; text-align: left; }
        .grid td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .grid tr.alt td { background: #f8fafc; }
        .expired { color: #dc2626; font-weight: bold; }

        .scan { border: 1px solid #e2e8f0; padding: 6px; margin-bottom: 10px; page-break-inside: avoid; }
        .scan-title { font-weight: bold; margin-bottom: 4px; }
        .scan img { max-width: 240px; max-height: 160px; border: 1px solid #cbd5e1; }
        .scan td { vertical-align: top; padding-right: 8px; }

        .sign { margin-top: 40px; page-break-inside: avoid; }
        .sign td { width: 33%; text-align: center; padding: 0 12px; vertical-align: bottom; }
        .sign img { max-height: 40px; max-width: 140px; }
        .sign .line { border-top: 1px solid #64748b; padding-top: 3px; font-size: 9px; color: #475569; }

        .footer { position: fixed; bottom: -32px; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; }
    </style>
</head>
<body>

    {{-- Header --}}
    <table>
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <table>
                    <tr>
                        @if ($company['logo'])
                            <td style="width: 60px; vertical-align: middle;"><img src="{{ $company['logo'] }}" style="max-height: 46px; max-width: 56px;" alt=""></td>
                        @endif
                        <td style="vertical-align: middle;">
                            <div class="company-name">{{ $company['name'] }}</div>
                            @if ($company['address'])<div class="muted">{{ $company['address'] }}</div>@endif
                            <div class="muted">
                                @if ($company['phone'])Phone: {{ $company['phone'] }}@endif
                                @if ($company['email']) &nbsp;|&nbsp; {{ $company['email'] }}@endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="vertical-align: top;" class="right">
                <div class="doc-title">EMPLOYEE PROFILE</div>
                <div style="margin-top: 3px;">Employee ID: <span class="bold">{{ $employee->employee_code }}</span></div>
                <div class="muted">Printed: {{ $printedAt }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    {{-- Hero --}}
    <table class="hero">
        <tr>
            <td style="width: 118px;">
                @if ($photo)
                    <img src="{{ $photo }}" class="photo" alt="">
                @else
                    <div class="photo-empty">{{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}</div>
                @endif
            </td>
            <td>
                <div class="hero-name">{{ $employee->name }}</div>
                <div class="hero-role">{{ $employee->designation }}@if ($employee->department) &nbsp;·&nbsp; {{ $employee->department }}@endif</div>
                <div style="margin: 6px 0;">
                    <span class="pill {{ $employee->status === 'Active' ? 'pill-on' : 'pill-off' }}">{{ $employee->status }}</span>
                    @if ($employee->employment_type)<span class="pill pill-off">{{ $employee->employment_type }}</span>@endif
                    @if ($employee->blood_group)<span class="pill" style="background:#fee2e2;color:#b91c1c;">Blood {{ $employee->blood_group }}</span>@endif
                </div>
                <table class="hero-meta">
                    <tr>
                        <td class="muted">Mobile</td><td class="bold">{{ $v($employee->phone) }}</td>
                        <td class="muted">Email</td><td class="bold">{{ $v($employee->email) }}</td>
                    </tr>
                    <tr>
                        <td class="muted">Joined</td><td class="bold">{{ $d($employee->joining_date) }}</td>
                        <td class="muted">Reports To</td><td class="bold">{{ $reportingTo ? $reportingTo->name . ' (' . $reportingTo->employee_code . ')' : '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Personal --}}
    <h3>Personal Information</h3>
    <table class="kv">
        <tr><td class="k">Father's Name</td><td class="v">{{ $v($employee->father_name) }}</td><td class="k">Mother's Name</td><td class="v">{{ $v($employee->mother_name) }}</td></tr>
        <tr><td class="k">Spouse's Name</td><td class="v">{{ $v($employee->spouse_name) }}</td><td class="k">Date of Birth</td><td class="v">{{ $d($employee->date_of_birth) }}</td></tr>
        <tr><td class="k">Gender</td><td class="v">{{ $v($employee->gender) }}</td><td class="k">Marital Status</td><td class="v">{{ $v($employee->marital_status) }}</td></tr>
        <tr><td class="k">Religion</td><td class="v">{{ $v($employee->religion) }}</td><td class="k">Nationality</td><td class="v">{{ $v($employee->nationality) }}</td></tr>
        <tr><td class="k">NID No</td><td class="v">{{ $v($employee->nid_no) }}</td><td class="k">TIN No</td><td class="v">{{ $v($employee->tin_no) }}</td></tr>
    </table>

    {{-- Contact --}}
    <h3>Contact</h3>
    <table class="kv">
        <tr><td class="k">Alternate Mobile</td><td class="v">{{ $v($employee->alt_phone) }}</td><td class="k">Emergency Contact</td>
            <td class="v">{{ $v(trim(implode(' ', array_filter([$employee->emergency_contact_name, $employee->emergency_contact_relation ? '(' . $employee->emergency_contact_relation . ')' : null, $employee->emergency_contact_phone])))) }}</td></tr>
        <tr><td class="k">Present Address</td><td class="v">{{ $v($employee->present_address) }}</td><td class="k">Permanent Address</td><td class="v">{{ $v($employee->permanent_address) }}</td></tr>
    </table>

    {{-- Job --}}
    <h3>Employment</h3>
    <table class="kv">
        <tr><td class="k">Designation</td><td class="v">{{ $v($employee->designation) }}</td><td class="k">Department</td><td class="v">{{ $v($employee->department) }}</td></tr>
        <tr><td class="k">Employment Type</td><td class="v">{{ $v($employee->employment_type) }}</td><td class="k">Work Shift</td><td class="v">{{ $v($employee->work_shift) }}</td></tr>
        <tr><td class="k">Joining Date</td><td class="v">{{ $d($employee->joining_date) }}</td><td class="k">Confirmation Date</td><td class="v">{{ $d($employee->confirmation_date) }}</td></tr>
        <tr><td class="k">Resign Date</td><td class="v">{{ $d($employee->resign_date) }}</td><td class="k">ID Card Valid Until</td><td class="v">{{ $d($employee->card_valid_until) }}</td></tr>
    </table>

    {{-- Salary --}}
    <h3>Salary &amp; Payment</h3>
    <table class="kv">
        <tr><td class="k">Basic</td><td class="v">{{ $m($employee->basic_salary) }}</td><td class="k">House Rent</td><td class="v">{{ $m($employee->house_rent) }}</td></tr>
        <tr><td class="k">Medical</td><td class="v">{{ $m($employee->medical_allowance) }}</td><td class="k">Transport</td><td class="v">{{ $m($employee->transport_allowance) }}</td></tr>
        <tr><td class="k">Other Allowance</td><td class="v">{{ $m($employee->other_allowance) }}</td><td class="k bold" style="color:#0f172a;">Gross Salary</td><td class="v bold">{{ $m($employee->gross_salary) }}</td></tr>
        <tr><td class="k">Payment Mode</td><td class="v">{{ $v($employee->salary_payment_mode) }}</td>
            @if ($employee->salary_payment_mode === 'Mobile Banking')
                <td class="k">Mobile Banking</td><td class="v">{{ $v(trim($employee->mobile_banking_provider . ' ' . $employee->mobile_banking_no)) }}</td>
            @else
                <td class="k">Bank</td><td class="v">{{ $v(implode(', ', array_filter([$employee->bank_name, $employee->bank_branch]))) }}</td>
            @endif
        </tr>
        @if ($employee->bank_account_no)
            <tr><td class="k">Account Name</td><td class="v">{{ $v($employee->bank_account_name) }}</td><td class="k">Account No</td><td class="v">{{ $employee->bank_account_no }}</td></tr>
        @endif
    </table>

    {{-- Education --}}
    <h3>Education</h3>
    @if ($educations->isEmpty())
        <div class="muted">None recorded.</div>
    @else
        <table class="grid">
            <thead><tr><th>Degree / Exam</th><th>Institute</th><th>Board / University</th><th>Year</th><th>Result</th></tr></thead>
            <tbody>
                @foreach ($educations as $i => $e)
                    <tr class="{{ $i % 2 ? 'alt' : '' }}"><td class="bold">{{ $e->degree }}</td><td>{{ $v($e->institute) }}</td><td>{{ $v($e->board_university) }}</td><td>{{ $v($e->passing_year) }}</td><td>{{ $v($e->result) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Experience --}}
    <h3>Work Experience</h3>
    @if ($experiences->isEmpty())
        <div class="muted">None recorded.</div>
    @else
        <table class="grid">
            <thead><tr><th>Company</th><th>Designation</th><th>Period</th><th>Responsibilities</th></tr></thead>
            <tbody>
                @foreach ($experiences as $i => $x)
                    <tr class="{{ $i % 2 ? 'alt' : '' }}">
                        <td class="bold">{{ $x->company_name }}</td>
                        <td>{{ $v($x->designation) }}</td>
                        <td>{{ $d($x->from_date) }} - {{ $x->to_date ? $d($x->to_date) : 'Present' }}</td>
                        <td>{{ $v($x->responsibilities) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Documents --}}
    <h3>Documents</h3>
    @if ($documents->isEmpty())
        <div class="muted">None recorded.</div>
    @else
        <table class="grid">
            <thead><tr><th>Document</th><th>Number</th><th>Issue Date</th><th>Expiry Date</th><th>Issued By</th><th>Attachment</th></tr></thead>
            <tbody>
                @foreach ($documents as $i => $doc)
                    @php $expired = $doc->expiry_date && \Carbon\Carbon::parse($doc->expiry_date)->lt(now()->startOfDay()); @endphp
                    <tr class="{{ $i % 2 ? 'alt' : '' }}">
                        <td class="bold">{{ $doc->document_type }}</td>
                        <td>{{ $v($doc->document_no) }}</td>
                        <td>{{ $d($doc->issue_date) }}</td>
                        <td class="{{ $expired ? 'expired' : '' }}">{{ $d($doc->expiry_date) }}{{ $expired ? ' (expired)' : '' }}</td>
                        <td>{{ $v($doc->issued_by) }}</td>
                        <td>{{ ($doc->file_front || $doc->file_back) ? (($doc->front_image || $doc->back_image) ? 'Photo below' : 'PDF on file') : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @php $scans = $documents->filter(fn ($doc) => $doc->front_image || $doc->back_image); @endphp
        @if ($scans->isNotEmpty())
            <h3>Document Photographs</h3>
            @foreach ($scans as $doc)
                <div class="scan">
                    <div class="scan-title">{{ $doc->document_type }}@if ($doc->document_no) <span class="muted">- {{ $doc->document_no }}</span>@endif</div>
                    <table>
                        <tr>
                            @if ($doc->front_image)
                                <td><div class="muted" style="font-size: 8px;">Front</div><img src="{{ $doc->front_image }}" alt=""></td>
                            @endif
                            @if ($doc->back_image)
                                <td><div class="muted" style="font-size: 8px;">Back</div><img src="{{ $doc->back_image }}" alt=""></td>
                            @endif
                        </tr>
                    </table>
                </div>
            @endforeach
        @endif
    @endif

    @if ($employee->description)
        <h3>Notes</h3>
        <div>{{ $employee->description }}</div>
    @endif

    {{-- Signatures --}}
    <table class="sign">
        <tr>
            <td>
                @if ($signature)<img src="{{ $signature }}" alt=""><br>@endif
                <div class="line">Employee Signature</div>
            </td>
            <td><div class="line">HR / Admin</div></td>
            <td><div class="line">Authorised Signature</div></td>
        </tr>
    </table>

    <div class="footer">
        {{ $company['name'] }} &nbsp;|&nbsp; Employee Profile &nbsp;|&nbsp; {{ $employee->name }} ({{ $employee->employee_code }})
    </div>

</body>
</html>
