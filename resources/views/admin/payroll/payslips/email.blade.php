<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Payslip {{ $slip['month']->format('F Y') }}</title></head>
<body style="margin:0; padding:24px; background:{{ $pc['soft'] }}; font-family: Arial, Helvetica, sans-serif; color:{{ $pc['ink'] }};">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; margin:0 auto; background:{{ $pc['paper'] }}; border:1px solid {{ $pc['border'] }}; border-radius:12px; overflow:hidden;">
        <tr>
            <td style="background:{{ $pc['brand'] }}; color:{{ $pc['on_brand'] }}; padding:20px 28px; font-size:18px; font-weight:bold;">
                {{ $company }}
                <div style="font-size:12px; font-weight:normal; opacity:.85; margin-top:2px;">Payslip · {{ $slip['month']->format('F Y') }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px; font-size:14px; line-height:1.6;">
                Dear {{ $slip['item']->name }},<br><br>
                Please find attached your payslip for {{ $slip['month']->format('F Y') }}.<br><br>
                Regards,<br>{{ $company }}
            </td>
        </tr>
        <tr>
            <td style="padding:0 28px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $pc['soft'] }}; border-radius:8px;">
                    <tr>
                        <td style="padding:14px 16px; font-size:13px; color:{{ $pc['muted'] }};">Net payable</td>
                        <td style="padding:14px 16px; font-size:18px; font-weight:bold; text-align:right;">{{ number_format($slip['net'], 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0 16px 14px; font-size:12px; color:{{ $pc['muted'] }};">Paid {{ number_format($slip['paid'], 2) }}</td>
                        <td style="padding:0 16px 14px; font-size:12px; text-align:right; color:{{ $slip['due'] > 0 ? $pc['danger'] : $pc['success'] }};">Due {{ number_format($slip['due'], 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
