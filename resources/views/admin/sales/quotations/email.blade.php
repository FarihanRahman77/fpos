<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Quotation {{ $quotation->quotation_no }}</title></head>
<body style="margin:0; padding:24px; background:{{ $pc['soft'] }}; font-family: Arial, Helvetica, sans-serif; color:{{ $pc['ink'] }};">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; margin:0 auto; background:{{ $pc['paper'] }}; border:1px solid {{ $pc['border'] }}; border-radius:12px; overflow:hidden;">
        <tr>
            <td style="background:{{ $pc['brand'] }}; color:{{ $pc['on_brand'] }}; padding:20px 28px; font-size:18px; font-weight:bold;">
                {{ $company }}
                <div style="font-size:12px; font-weight:normal; opacity:.85; margin-top:2px;">Quotation {{ $quotation->quotation_no }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px; font-size:14px; line-height:1.6; white-space:pre-line;">{{ $body }}</td>
        </tr>
        <tr>
            <td style="padding:0 28px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $pc['soft'] }}; border-radius:8px;">
                    <tr>
                        <td style="padding:14px 16px; font-size:13px; color:{{ $pc['muted'] }};">Quotation total</td>
                        <td style="padding:14px 16px; font-size:18px; font-weight:bold; text-align:right;">{{ number_format((float) $quotation->grand_total, 2) }}</td>
                    </tr>
                    @if ($quotation->valid_until)
                        <tr>
                            <td colspan="2" style="padding:0 16px 14px; font-size:12px; color:{{ $pc['muted'] }};">Valid until {{ \Carbon\Carbon::parse($quotation->valid_until)->format('d M Y') }} · full details in the attached PDF</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
