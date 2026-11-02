<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip</title>
    @include('admin.inventory.partials.invoice-style')
    <style>
        .slip { page-break-after: always; }
        .slip.last { page-break-after: auto; }
        .ps-grid { margin-top: 22px; }
        .ps-grid th { font-weight: bold; font-size: 9px; text-align: left; padding: 8px 6px; border-bottom: 1px solid {{ $pc['border_strong'] }}; }
        .ps-grid th.r { text-align: right; }
        .ps-grid td { padding: 6px 6px; font-size: 9px; }
        .ps-grid td.gap, .ps-grid th.gap { width: 4%; border-bottom: none; }
        .ps-grid .sub { font-size: 7.5px; color: {{ $pc['muted'] }}; margin-top: 1px; }
        .ps-grid .sum td { border-top: 1px solid {{ $pc['border'] }}; font-weight: bold; padding-top: 9px; }
        .ps-grid .sum td.gap { border-top: none; }
        .ps-ded { color: {{ $pc['danger'] }}; }
        .ps-att { margin-top: 10px; font-size: 8.5px; color: {{ $pc['secondary'] }}; }
        .ps-att b { color: {{ $pc['ink'] }}; }
        .ps-words { font-size: 9px; color: {{ $pc['ink'] }}; margin-bottom: 14px; }
        .ps-pay th { font-weight: bold; font-size: 8px; text-align: left; padding: 4px 4px; border-bottom: 1px solid {{ $pc['border'] }}; color: {{ $pc['secondary'] }}; }
        .ps-pay th.r { text-align: right; }
        .ps-pay td { font-size: 8.5px; padding: 4px 4px; border-bottom: 1px solid {{ $pc['soft'] }}; }
    </style>
</head>
<body>
    @php $m = fn ($v) => number_format((float) $v, 2); @endphp

    <div class="inv-footer">
        <table><tr>
            <td>This is a computer-generated payslip. Please keep it for your records.</td>
            <td class="r">{{ $generalSetting['company_name'] ?? config('app.name') }}</td>
        </tr></table>
    </div>

    @foreach ($slips as $slip)
        @php
            $it = $slip['item'];
            $state = $slip['due'] <= 0 && $slip['net'] > 0
                ? ['PAID', $pc['success'], $pc['success_soft']]
                : ($slip['paid'] > 0 ? ['PARTLY PAID', $pc['warning'], $pc['warning_soft']] : ['UNPAID', $pc['danger'], $pc['danger_soft']]);
            $rows = max(count($slip['earnings']), count($slip['deductions']));
            $payTo = match ($it->salary_payment_mode) {
                'Bank' => trim(($it->bank_name ?: 'Bank') . ($it->bank_account_no ? ' · A/C ' . $it->bank_account_no : '')),
                'Mobile Banking' => trim(($it->mobile_banking_provider ?: 'Mobile banking') . ($it->mobile_banking_no ? ' · ' . $it->mobile_banking_no : '')),
                default => $it->salary_payment_mode ?: 'Cash',
            };
            $att = $slip['attendance'];
        @endphp
        <div class="slip {{ $loop->last ? 'last' : '' }}">
            @include('admin.inventory.partials.invoice-header', [
                'title'  => 'Payslip',
                'number' => $slip['month']->format('F Y') . ' · ' . $it->run_no,
                'state'  => $state,
            ])

            <table class="inv-band">
                <tr>
                    <td class="col" style="width: 36%;">
                        <div class="lbl">Employee</div>
                        <div class="name">{{ $it->name }}</div>
                        <div class="line">ID: {{ $it->employee_code }}</div>
                        @if ($it->designation)<div class="line">{{ $it->designation }}</div>@endif
                        @if ($it->department)<div class="line">{{ $it->department }}</div>@endif
                    </td>
                    <td class="col" style="width: 26%;">
                        <div class="lbl">Paid to</div>
                        <div class="name">{{ $it->salary_payment_mode ?: 'Cash' }}</div>
                        @if ($payTo !== ($it->salary_payment_mode ?: 'Cash'))<div class="line">{{ $payTo }}</div>@endif
                        @if ($it->grade_step)
                            <div class="lbl" style="margin-top: 8px;">Grade / Step</div>
                            <div class="line">{{ $it->grade_step }}</div>
                        @endif
                    </td>
                    <td class="col" style="width: 38%;">
                        <div class="lbl">Details</div>
                        <table class="inv-kv">
                            <tr><td class="k">Pay period</td><td>{{ $slip['month']->format('01 M Y') }} – {{ $slip['month']->copy()->endOfMonth()->format('d M Y') }}</td></tr>
                            <tr><td class="k">Salary run</td><td>{{ $it->run_no }}</td></tr>
                            <tr><td class="k">Processed</td><td>{{ \Carbon\Carbon::parse($it->run_date)->format('d-m-Y') }}</td></tr>
                            @if ($it->joining_date)<tr><td class="k">Joined</td><td>{{ \Carbon\Carbon::parse($it->joining_date)->format('d-m-Y') }}</td></tr>@endif
                        </table>
                    </td>
                </tr>
            </table>

            @if ($att)
                <div class="ps-att">
                    Attendance: <b>{{ $att['attended'] }}</b> day(s) present
                    @if ($att['late']) (<b>{{ $att['late'] }}</b> late)@endif
                    @if ($att['half_day']) · <b>{{ $att['half_day'] }}</b> half day(s)@endif
                    · <b>{{ $att['absent'] }}</b> absent · <b>{{ $att['leave'] }}</b> on leave
                    @if ($att['ot_hours'] > 0) · overtime <b>{{ rtrim(rtrim(number_format($att['ot_hours'], 2), '0'), '.') }}</b> h @endif
                </div>
            @endif

            <table class="ps-grid">
                <thead>
                    <tr>
                        <th style="width: 30%;">Earnings</th>
                        <th class="r" style="width: 18%;">Amount</th>
                        <th class="gap"></th>
                        <th style="width: 30%;">Deductions</th>
                        <th class="r" style="width: 18%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $rows; $i++)
                        @php $e = $slip['earnings'][$i] ?? null; $d = $slip['deductions'][$i] ?? null; @endphp
                        <tr>
                            <td>@if ($e){{ $e[0] }}@if ($e[2])<div class="sub">{{ $e[2] }}</div>@endif @endif</td>
                            <td class="r">{{ $e ? $m($e[1]) : '' }}</td>
                            <td class="gap"></td>
                            <td>@if ($d){{ $d[0] }}@if ($d[2])<div class="sub">{{ $d[2] }}</div>@endif @endif</td>
                            <td class="r ps-ded">{{ $d ? $m($d[1]) : '' }}</td>
                        </tr>
                    @endfor
                    @if (!$slip['deductions'])
                        <tr><td colspan="3"></td><td class="muted" colspan="2">No deductions</td></tr>
                    @endif
                    <tr class="sum">
                        <td>Total earnings</td>
                        <td class="r">{{ $m($slip['total_earn']) }}</td>
                        <td class="gap"></td>
                        <td>Total deductions</td>
                        <td class="r ps-ded">{{ $m($slip['total_ded']) }}</td>
                    </tr>
                </tbody>
            </table>

            <table class="inv-bottom">
                <tr>
                    <td style="width: 55%; padding-right: 30px;" class="inv-msg">
                        <div class="lbl">Net pay in words</div>
                        <div class="ps-words">Taka {{ $slip['net_words'] }} only</div>

                        <div class="lbl">Payments</div>
                        @if ($slip['payments']->isNotEmpty())
                            <table class="ps-pay">
                                <thead><tr><th>Date</th><th>Payment #</th><th>Paid from</th><th class="r">Amount</th></tr></thead>
                                <tbody>
                                    @foreach ($slip['payments'] as $p)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($p->payment_date)->format('d-m-Y') }}</td>
                                            <td>{{ $p->payment_no }}</td>
                                            <td>{{ $p->account_name ?: '-' }}</td>
                                            <td class="r">{{ $m($p->amount) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="txt">Not paid yet.</div>
                        @endif
                    </td>
                    <td style="width: 45%;">
                        <table class="inv-totals">
                            <tr><td class="k">Total earnings</td><td class="r">{{ $m($slip['total_earn']) }}</td></tr>
                            <tr><td class="k">Total deductions</td><td class="r">{{ $m($slip['total_ded']) }}</td></tr>
                            <tr class="total"><td>Net Payable</td><td class="r">{{ $m($slip['net']) }}</td></tr>
                            <tr class="after"><td class="k">Paid</td><td class="r">{{ $m($slip['paid']) }}</td></tr>
                            <tr class="due {{ $slip['due'] <= 0 ? 'clear' : '' }}"><td>Due</td><td class="r">{{ $m($slip['due']) }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table class="inv-sign">
                <tr>
                    <td style="width: 35%;"><div class="line">Authorised Signature</div></td>
                    <td style="width: 30%;"></td>
                    <td style="width: 35%;"><div class="line">Employee Signature</div></td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
