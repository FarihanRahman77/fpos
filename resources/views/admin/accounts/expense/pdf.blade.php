<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Expense {{ $expense->expense_no }}</title>
    <style>
        @page { margin: 28px 32px 56px 32px; }

        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        table { width: 100%; border-collapse: collapse; }

        .muted { color: #64748b; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }

        .company-name { font-size: 18px; font-weight: bold; color: #0f172a; }
        .doc-title { font-size: 20px; font-weight: bold; color: #b91c1c; letter-spacing: 1px; }
        .rule { border-top: 3px solid #1e293b; margin: 10px 0 14px 0; }

        .box { border: 1px solid #e2e8f0; background: #f8fafc; padding: 9px 11px; }
        .box-title { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: .6px; margin-bottom: 5px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta td.k { width: 38%; color: #64748b; }

        .items { margin-top: 16px; }
        .items th { background: #1e293b; color: #ffffff; padding: 7px 8px; font-size: 9.5px; text-transform: uppercase; text-align: left; }
        .items td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .items tr.alt td { background: #f8fafc; }

        .total-table { margin-top: 10px; }
        .total-table td { padding: 6px 8px; }
        .grand { background: #1e293b; color: #ffffff; font-size: 13px; font-weight: bold; }

        .words { margin-top: 10px; padding: 8px 10px; border: 1px dashed #cbd5e1; }
        .note { margin-top: 12px; }

        .sign { margin-top: 56px; }
        .sign td { width: 33%; text-align: center; padding: 0 14px; }
        .sign .line { border-top: 1px solid #64748b; padding-top: 4px; font-size: 10px; color: #475569; }

        .footer { position: fixed; bottom: -34px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>

    {{-- Header --}}
    <table>
        <tr>
            <td style="vertical-align: top; width: 58%;">
                <div class="company-name">{{ $company['name'] }}</div>
                @if ($company['address']) <div class="muted">{{ $company['address'] }}</div> @endif
                @if ($company['phone'])   <div class="muted">Phone: {{ $company['phone'] }}</div> @endif
                @if ($company['email'])   <div class="muted">Email: {{ $company['email'] }}</div> @endif
            </td>
            <td style="vertical-align: top;" class="right">
                <div class="doc-title">EXPENSE INVOICE</div>
                <div style="margin-top: 4px;">No: <span class="bold">{{ $expense->expense_no }}</span></div>
                <div>Date: <span class="bold">{{ \Carbon\Carbon::parse($expense->expense_date)->format('d M Y') }}</span></div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    {{-- Info boxes --}}
    <table>
        <tr>
            <td style="width: 50%; padding-right: 6px; vertical-align: top;">
                <div class="box">
                    <div class="box-title">Expense Made By</div>
                    <table class="meta">
                        <tr>
                            <td class="k">Employee</td>
                            <td class="bold">{{ $expense->employee_name ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="k">Employee Code</td>
                            <td>{{ $expense->employee_code ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="k">Designation</td>
                            <td>{{ $expense->designation ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td style="width: 50%; padding-left: 6px; vertical-align: top;">
                <div class="box">
                    <div class="box-title">Payment Details</div>
                    <table class="meta">
                        <tr>
                            <td class="k">Paid From</td>
                            <td class="bold">{{ $expense->pa_code }} - {{ $expense->pa_name }}</td>
                        </tr>
                        <tr>
                            <td class="k">Account Group</td>
                            <td>{{ $expense->pg_code }} - {{ $expense->pg_name }}</td>
                        </tr>
                        <tr>
                            <td class="k">Reference No</td>
                            <td>{{ $expense->reference_no ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 30%;">Expense Account</th>
                <th style="width: 20%;">Group</th>
                <th>Description</th>
                <th style="width: 16%;" class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($details as $i => $line)
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td>
                        <span class="bold">{{ $line->account_name }}</span><br>
                        <span class="muted" style="font-size: 9px;">{{ $line->account_code }}</span>
                    </td>
                    <td>{{ $line->group_name }}</td>
                    <td>{{ $line->description ?: '-' }}</td>
                    <td class="right">{{ number_format((float) $line->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Total --}}
    <table class="total-table">
        <tr>
            <td style="width: 58%;"></td>
            <td class="grand" style="width: 26%;">TOTAL</td>
            <td class="grand right">{{ number_format($total, 2) }}</td>
        </tr>
    </table>

    @if ($words)
        <div class="words"><span class="muted">In words:</span> <span class="bold">{{ $words }}</span></div>
    @endif

    @if ($expense->remarks)
        <div class="note">
            <div class="box-title">Note</div>
            <div>{{ $expense->remarks }}</div>
        </div>
    @endif

    {{-- Signatures --}}
    <table class="sign">
        <tr>
            <td><div class="line">Prepared By</div></td>
            <td><div class="line">Approved By</div></td>
            <td><div class="line">Received By</div></td>
        </tr>
    </table>

    <div class="footer">
        {{ $expense->expense_no }} &nbsp;|&nbsp; Generated on {{ $printedAt }}
    </div>

</body>
</html>
