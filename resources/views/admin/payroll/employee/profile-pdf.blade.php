@extends('pdf.layout')
@php
    $d = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d M Y') : '-';
    $v = fn ($x) => ($x === null || $x === '') ? '-' : $x;
    $m = fn ($x) => number_format((float) $x, 2);
    $doc = [
        'title'     => 'Employee Profile',
        'number'    => 'Employee ID: ' . $employee->employee_code,
        'printedAt' => $printedAt,
    ];
@endphp

@section('styles')
    <style>
        .hero { margin-bottom: 4px; border: 0.6px solid {{ $pdfT['rule'] }}; background: {{ $pdfT['accent_soft'] }}; }
        .hero td { vertical-align: top; padding: 10px; }
        .photo { width: 100px; height: 120px; border: 2px solid {{ $pdfT['paper'] }}; }
        .photo-empty { width: 100px; background: {{ $pdfT['rule'] }}; color: {{ $pdfT['muted'] }}; font-size: 40px; font-weight: bold; text-align: center; line-height: 1; height: 80px; padding-top: 40px; }
        .pdf-h { page-break-after: avoid; }
        .hero-name { font-family: '{{ $pdfT['head_font'] }}', serif; font-size: 16px; font-weight: bold; color: {{ $pdfT['ink'] }}; }
        .hero-role { font-size: 10px; color: {{ $pdfT['accent'] }}; font-weight: bold; margin-top: 2px; }
        .hero-tags { margin: 6px 0; }
        .hero-tags .pdf-badge { margin-right: 3px; }
        .hero-meta { width: auto; }
        .hero-meta td { padding: 2px 12px 2px 0; font-size: 8.4px; }
        .hero-meta td.k { color: {{ $pdfT['muted'] }}; }
        .hero-meta td.v { font-weight: bold; color: {{ $pdfT['ink'] }}; }
        .pdf-kv td.k { width: 18%; }
        .pdf-kv td.v { width: 32%; }
        .pdf-kv td.strong { font-weight: bold; color: {{ $pdfT['ink'] }}; }
        .none { color: {{ $pdfT['muted'] }}; font-style: italic; margin-bottom: 6px; }
        .expired { color: {{ $pdfT['danger'] }}; font-weight: bold; }
        .scan { border: 0.6px solid {{ $pdfT['rule'] }}; padding: 6px; margin-bottom: 10px; page-break-inside: avoid; }
        .scan-title { font-weight: bold; color: {{ $pdfT['ink'] }}; margin-bottom: 4px; }
        .scan img { max-width: 240px; max-height: 160px; border: 0.6px solid {{ $pdfT['rule_strong'] }}; }
        .scan td { vertical-align: top; padding-right: 8px; }
    </style>
@endsection

@section('content')
    {{-- Hero --}}
    <table class="hero">
        <tr>
            <td style="width: 106px;">
                @if ($photo)
                    <img src="{{ $photo }}" class="photo" alt="">
                @else
                    <div class="photo-empty">{{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}</div>
                @endif
            </td>
            <td>
                <div class="hero-name">{{ $employee->name }}</div>
                <div class="hero-role">{{ $employee->designation }}@if ($employee->department) &nbsp;·&nbsp; {{ $employee->department }}@endif</div>
                <div class="hero-tags">
                    <span class="pdf-badge" style="color: {{ $employee->status === 'Active' ? $pdfT['success'] : $pdfT['muted'] }}; border-color: {{ $employee->status === 'Active' ? $pdfT['success'] : $pdfT['muted'] }};">{{ $employee->status }}</span>
                    @if ($employee->employment_type)<span class="pdf-badge" style="color: {{ $pdfT['muted'] }};">{{ $employee->employment_type }}</span>@endif
                    @if ($employee->blood_group)<span class="pdf-badge" style="color: {{ $pdfT['danger'] }}; border-color: {{ $pdfT['danger'] }};">Blood {{ $employee->blood_group }}</span>@endif
                </div>
                <table class="hero-meta">
                    <tr>
                        <td class="k">Mobile</td><td class="v">{{ $v($employee->phone) }}</td>
                        <td class="k">Email</td><td class="v">{{ $v($employee->email) }}</td>
                    </tr>
                    <tr>
                        <td class="k">Joined</td><td class="v">{{ $d($employee->joining_date) }}</td>
                        <td class="k">Reports To</td><td class="v">{{ $reportingTo ? $reportingTo->name . ' (' . $reportingTo->employee_code . ')' : '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Personal --}}
    <div class="pdf-h">Personal Information</div>
    <table class="pdf-kv">
        <tr><td class="k">Father's Name</td><td class="v">{{ $v($employee->father_name) }}</td><td class="k">Mother's Name</td><td class="v">{{ $v($employee->mother_name) }}</td></tr>
        <tr><td class="k">Spouse's Name</td><td class="v">{{ $v($employee->spouse_name) }}</td><td class="k">Date of Birth</td><td class="v">{{ $d($employee->date_of_birth) }}</td></tr>
        <tr><td class="k">Gender</td><td class="v">{{ $v($employee->gender) }}</td><td class="k">Marital Status</td><td class="v">{{ $v($employee->marital_status) }}</td></tr>
        <tr><td class="k">Religion</td><td class="v">{{ $v($employee->religion) }}</td><td class="k">Nationality</td><td class="v">{{ $v($employee->nationality) }}</td></tr>
        <tr><td class="k">NID No</td><td class="v">{{ $v($employee->nid_no) }}</td><td class="k">TIN No</td><td class="v">{{ $v($employee->tin_no) }}</td></tr>
    </table>

    {{-- Contact --}}
    <div class="pdf-h">Contact</div>
    <table class="pdf-kv">
        <tr><td class="k">Alternate Mobile</td><td class="v">{{ $v($employee->alt_phone) }}</td><td class="k">Emergency Contact</td>
            <td class="v">{{ $v(trim(implode(' ', array_filter([$employee->emergency_contact_name, $employee->emergency_contact_relation ? '(' . $employee->emergency_contact_relation . ')' : null, $employee->emergency_contact_phone])))) }}</td></tr>
        <tr><td class="k">Present Address</td><td class="v">{{ $v($employee->present_address) }}</td><td class="k">Permanent Address</td><td class="v">{{ $v($employee->permanent_address) }}</td></tr>
    </table>

    {{-- Job --}}
    <div class="pdf-h">Employment</div>
    <table class="pdf-kv">
        <tr><td class="k">Designation</td><td class="v">{{ $v($employee->designation) }}</td><td class="k">Department</td><td class="v">{{ $v($employee->department) }}</td></tr>
        <tr><td class="k">Employment Type</td><td class="v">{{ $v($employee->employment_type) }}</td><td class="k">Work Shift</td><td class="v">{{ $v($employee->work_shift) }}</td></tr>
        <tr><td class="k">Joining Date</td><td class="v">{{ $d($employee->joining_date) }}</td><td class="k">Confirmation Date</td><td class="v">{{ $d($employee->confirmation_date) }}</td></tr>
        <tr><td class="k">Resign Date</td><td class="v">{{ $d($employee->resign_date) }}</td><td class="k">ID Card Valid Until</td><td class="v">{{ $d($employee->card_valid_until) }}</td></tr>
    </table>

    {{-- Salary --}}
    <div class="pdf-h">Salary &amp; Payment</div>
    <table class="pdf-kv">
        @if ($isScale)
            <tr><td class="k">Basic</td><td class="v">{{ $m($employee->basic_salary) }}</td><td class="k">House Rent</td><td class="v">{{ $m($employee->house_rent) }}</td></tr>
            <tr><td class="k">Compensation</td><td class="v">{{ $m($employee->compensation) }}</td><td class="k">Medical</td><td class="v">{{ $m($employee->medical_allowance) }}</td></tr>
        @else
            <tr><td class="k">Contract Salary</td><td class="v">{{ $m($employee->contract_salary) }}</td><td class="k"></td><td class="v"></td></tr>
        @endif
        <tr><td class="k strong">Gross Salary</td><td class="v strong">{{ $m($employee->gross_salary) }}</td><td class="k">PF Deduction</td><td class="v">{{ $m($employee->pf_amount) }}</td></tr>
        <tr><td class="k strong">Net Payable</td><td class="v strong">{{ $m($employee->net_salary) }}</td><td class="k">Bonus</td><td class="v">{{ $employee->bonus_eligible === 'Yes' ? $m($employee->bonus_amount) : 'Not eligible' }}</td></tr>
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
    <div class="pdf-h">Education</div>
    @if ($educations->isEmpty())
        <div class="none">None recorded.</div>
    @else
        <table class="pdf-table">
            <thead><tr><th>Degree / Exam</th><th>Institute</th><th>Board / University</th><th>Year</th><th>Result</th></tr></thead>
            <tbody>
                @foreach ($educations as $e)
                    <tr><td class="b">{{ $e->degree }}</td><td>{{ $v($e->institute) }}</td><td>{{ $v($e->board_university) }}</td><td>{{ $v($e->passing_year) }}</td><td>{{ $v($e->result) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Experience --}}
    <div class="pdf-h">Work Experience</div>
    @if ($experiences->isEmpty())
        <div class="none">None recorded.</div>
    @else
        <table class="pdf-table">
            <thead><tr><th>Company</th><th>Designation</th><th>Period</th><th>Responsibilities</th></tr></thead>
            <tbody>
                @foreach ($experiences as $x)
                    <tr>
                        <td class="b">{{ $x->company_name }}</td>
                        <td>{{ $v($x->designation) }}</td>
                        <td>{{ $d($x->from_date) }} - {{ $x->to_date ? $d($x->to_date) : 'Present' }}</td>
                        <td>{{ $v($x->responsibilities) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Documents ($dc, not $doc: the view's variables reach the layout, which reads $doc) --}}
    <div class="pdf-h">Documents</div>
    @if ($documents->isEmpty())
        <div class="none">None recorded.</div>
    @else
        <table class="pdf-table">
            <thead><tr><th>Document</th><th>Number</th><th>Issue Date</th><th>Expiry Date</th><th>Issued By</th><th>Attachment</th></tr></thead>
            <tbody>
                @foreach ($documents as $dc)
                    @php $expired = $dc->expiry_date && \Carbon\Carbon::parse($dc->expiry_date)->lt(now()->startOfDay()); @endphp
                    <tr>
                        <td class="b">{{ $dc->document_type }}</td>
                        <td>{{ $v($dc->document_no) }}</td>
                        <td>{{ $d($dc->issue_date) }}</td>
                        <td class="{{ $expired ? 'expired' : '' }}">{{ $d($dc->expiry_date) }}{{ $expired ? ' (expired)' : '' }}</td>
                        <td>{{ $v($dc->issued_by) }}</td>
                        <td>{{ ($dc->file_front || $dc->file_back) ? (($dc->front_image || $dc->back_image) ? 'Photo below' : 'PDF on file') : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @php $scans = $documents->filter(fn ($dc) => $dc->front_image || $dc->back_image); @endphp
        @if ($scans->isNotEmpty())
            <div class="pdf-h">Document Photographs</div>
            @foreach ($scans as $dc)
                <div class="scan">
                    <div class="scan-title">{{ $dc->document_type }}@if ($dc->document_no) <span class="muted">- {{ $dc->document_no }}</span>@endif</div>
                    <table>
                        <tr>
                            @if ($dc->front_image)
                                <td><div class="pdf-label">Front</div><img src="{{ $dc->front_image }}" alt=""></td>
                            @endif
                            @if ($dc->back_image)
                                <td><div class="pdf-label">Back</div><img src="{{ $dc->back_image }}" alt=""></td>
                            @endif
                        </tr>
                    </table>
                </div>
            @endforeach
        @endif
    @endif

    @if ($employee->description)
        <div class="pdf-h">Notes</div>
        <div>{{ $employee->description }}</div>
    @endif

    {{-- Signatures: the employee's own signature over the first line, the company's over the authorising one --}}
    <table class="pdf-sign">
        <tr>
            @foreach (['Employee signature' => $signature, 'HR / Admin' => null, 'Authorised signature' => $pdfCo['signature']] as $label => $img)
                <td style="width: 33%;" class="{{ $loop->first ? '' : 'gap' }}">
                    <div class="pdf-sign-space">@if ($img)<img src="{{ $img }}" class="pdf-sign-img" alt="">@endif</div>
                    <div class="pdf-sign-line">{{ $label }}</div>
                </td>
            @endforeach
        </tr>
    </table>
@endsection
