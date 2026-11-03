@extends('pdf.layout')
@php
    $m = fn ($v) => number_format((float) $v, 2);
    // the header is printed once per slip (each slip has its own number and status)
    $doc = ['title' => count($slips) === 1 ? 'Payslip' : 'Payslips', 'header' => false];
@endphp

@section('styles')
    <style>
        .slip { page-break-after: always; }
        .slip.last { page-break-after: auto; }
        .ps-att { margin: 0 0 10px 0; font-size: 8.2px; color: {{ $pdfT['muted'] }}; }
        .ps-att b { color: {{ $pdfT['ink'] }}; }
        .pdf-table.ps-grid th.gap, .pdf-table.ps-grid tbody tr td.gap { width: 4%; background: {{ $pdfT['paper'] }}; border: 0; }
        .pdf-table.ps-grid .sub { margin-top: 1px; }
        .ps-pay { margin-top: 3px; }
        .ps-pay-none { font-size: 8px; color: {{ $pdfT['muted'] }}; margin-top: 2px; }
        .ps-foot { margin-top: 14px; text-align: center; font-size: 7.2px; color: {{ $pdfT['faint'] }}; }
        .pdf-sign { margin-top: 30px; }
    </style>
@endsection

@section('content')
    @foreach ($slips as $slip)
        @php
            $it = $slip['item'];
            $status = $slip['due'] <= 0 && $slip['net'] > 0
                ? ['Paid', 'success']
                : ($slip['paid'] > 0 ? ['Partly paid', 'warning'] : ['Unpaid', 'danger']);
            $rows = max(count($slip['earnings']), count($slip['deductions']));
            $payTo = match ($it->salary_payment_mode) {
                'Bank' => trim(($it->bank_name ?: 'Bank') . ($it->bank_account_no ? ' · A/C ' . $it->bank_account_no : '')),
                'Mobile Banking' => trim(($it->mobile_banking_provider ?: 'Mobile banking') . ($it->mobile_banking_no ? ' · ' . $it->mobile_banking_no : '')),
                default => $it->salary_payment_mode ?: 'Cash',
            };
            $att = $slip['attendance'];
            $paidFrom = collect($slip['payments'])->pluck('account_name')->filter()->unique()->values();
            $grade = $it->grade_step ? 'Grade / Step: ' . $it->grade_step : null;
            // the account(s) the salary was actually paid from, else where it will be paid
            $payParty = $paidFrom->isNotEmpty()
                ? ['label' => 'Paid from', 'name' => $paidFrom->implode(', '), 'lines' => ['To: ' . $payTo, $grade]]
                : ['label' => 'Pay to', 'name' => $it->salary_payment_mode ?: 'Cash',
                   'lines' => [$payTo !== ($it->salary_payment_mode ?: 'Cash') ? $payTo : null, 'Not paid yet', $grade]];
            $cur = $pdfCo['currency'];
        @endphp
        <div class="slip {{ $loop->last ? 'last' : '' }}">
            @include('pdf.partials.header', ['doc' => [
                'title'  => 'Payslip',
                'number' => $slip['month']->format('F Y') . ' · ' . $it->run_no,
                'status' => $status,
            ]])

            @include('pdf.partials.party', ['parties' => [
                ['label' => 'Employee', 'name' => $it->name, 'lines' => ['ID: ' . $it->employee_code, $it->designation, $it->department]],
                $payParty,
                ['label' => 'Details', 'kv' => [
                    'Pay period' => $slip['month']->format('01 M Y') . ' – ' . $slip['month']->copy()->endOfMonth()->format('d M Y'),
                    'Salary run' => $it->run_no,
                    'Processed'  => \Carbon\Carbon::parse($it->run_date)->format('d-m-Y'),
                    'Joined'     => $it->joining_date ? \Carbon\Carbon::parse($it->joining_date)->format('d-m-Y') : null,
                ]],
            ]])

            @if ($att)
                <div class="ps-att">
                    Attendance: <b>{{ $att['attended'] }}</b> day(s) present
                    @if ($att['late']) (<b>{{ $att['late'] }}</b> late)@endif
                    @if ($att['half_day']) · <b>{{ $att['half_day'] }}</b> half day(s)@endif
                    · <b>{{ $att['absent'] }}</b> absent · <b>{{ $att['leave'] }}</b> on leave
                    @if ($att['ot_hours'] > 0) · overtime <b>{{ rtrim(rtrim(number_format($att['ot_hours'], 2), '0'), '.') }}</b> h @endif
                </div>
            @endif

            <table class="pdf-table ps-grid">
                <thead>
                    <tr>
                        <th style="width: 30%;">Earnings</th>
                        <th class="num" style="width: 18%;">Amount</th>
                        <th class="gap"></th>
                        <th style="width: 30%;">Deductions</th>
                        <th class="num" style="width: 18%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $rows; $i++)
                        @php $e = $slip['earnings'][$i] ?? null; $d = $slip['deductions'][$i] ?? null; @endphp
                        <tr>
                            <td>@if ($e){{ $e[0] }}@if ($e[2])<div class="sub">{{ $e[2] }}</div>@endif @endif</td>
                            <td class="num">{{ $e ? $m($e[1]) : '' }}</td>
                            <td class="gap"></td>
                            <td>@if ($d){{ $d[0] }}@if ($d[2])<div class="sub">{{ $d[2] }}</div>@endif @endif</td>
                            <td class="num neg">{{ $d ? $m($d[1]) : '' }}</td>
                        </tr>
                    @endfor
                    @if (!$slip['deductions'])
                        <tr><td colspan="2"></td><td class="gap"></td><td class="muted" colspan="2">No deductions</td></tr>
                    @endif
                    <tr class="total">
                        <td>Total earnings</td>
                        <td class="num">{{ $m($slip['total_earn']) }}</td>
                        <td class="gap"></td>
                        <td>Total deductions</td>
                        <td class="num neg">{{ $m($slip['total_ded']) }}</td>
                    </tr>
                </tbody>
            </table>

            <table class="pdf-bottom">
                <tr>
                    <td class="pdf-bottom-left">
                        @include('pdf.partials.words', ['w' => ['text' => $slip['net_words']]])

                        <div class="pdf-label">Payments</div>
                        @if ($slip['payments']->isNotEmpty())
                            <table class="pdf-table compact ps-pay">
                                <thead><tr><th>Date</th><th>Payment #</th><th>Paid from</th><th class="num">Amount</th></tr></thead>
                                <tbody>
                                    @foreach ($slip['payments'] as $p)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($p->payment_date)->format('d-m-Y') }}</td>
                                            <td>{{ $p->payment_no }}</td>
                                            <td>{{ $p->account_name ?: '-' }}</td>
                                            <td class="num">{{ $m($p->amount) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="ps-pay-none">Not paid yet.</div>
                        @endif
                    </td>
                    <td class="pdf-bottom-right">
                        <table class="pdf-totals">
                            <tr><td class="k">Total earnings</td><td class="num">{{ $m($slip['total_earn']) }}</td></tr>
                            <tr><td class="k">Total deductions</td><td class="num">{{ $m($slip['total_ded']) }}</td></tr>
                            <tr class="grand"><td class="k">Net Payable</td><td class="num">{{ $cur ? $cur . ' ' : '' }}{{ $m($slip['net']) }}</td></tr>
                            <tr class="after"><td class="k">Paid</td><td class="num">{{ $m($slip['paid']) }}</td></tr>
                            <tr class="after due {{ $slip['due'] <= 0 ? 'clear' : '' }}"><td class="k">Due</td><td class="num">{{ $m($slip['due']) }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>

            @include('pdf.partials.signatures', ['signatures' => ['Employee signature' => '', 'Authorised signature' => '']])

            <div class="ps-foot">This is a computer-generated payslip. Please keep it for your records.</div>
        </div>
    @endforeach
@endsection
