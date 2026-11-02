{{-- Shared dompdf styles for every invoice (sale, purchase, sale order, sale return).
     Colors come from Settings > Theme Colors through $pc (dompdf cannot read CSS variables). --}}
<style>
    @page { margin: 34px 40px 60px 40px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; line-height: 1.5; color: {{ $pc['ink'] }}; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .r { text-align: right; }
    .c { text-align: center; }
    .b { font-weight: bold; }
    .muted { color: {{ $pc['muted'] }}; }

    /* Header: logo + company left, big document title right */
    .inv-title { font-size: 26px; font-weight: bold; color: {{ $pc['secondary'] }}; text-align: right; letter-spacing: .5px; line-height: 1; }
    .inv-no { text-align: right; font-size: 10px; color: {{ $pc['muted'] }}; margin-top: 6px; }
    .inv-state { display: inline-block; margin-top: 6px; padding: 2px 10px; border: 1px solid; border-radius: 9px; font-size: 7.5px; font-weight: bold; letter-spacing: .5px; }
    .inv-logo { max-height: 46px; max-width: 170px; }
    .inv-logo-text { font-size: 22px; font-weight: bold; color: {{ $pc['ink'] }}; line-height: 1.1; }
    .inv-company { margin-top: 14px; }
    .inv-company .name { font-size: 11px; font-weight: bold; }
    .inv-company .line { color: {{ $pc['secondary'] }}; font-size: 9px; }
    .inv-contact td { padding: 0 0 1px 0; font-size: 9px; color: {{ $pc['secondary'] }}; }
    .inv-contact .k { font-weight: bold; color: {{ $pc['ink'] }}; width: 52px; }

    /* Grey band: bill to / ship to / details */
    .inv-band { margin-top: 24px; background: {{ $pc['soft'] }}; }
    .inv-band td.col { padding: 16px 14px; }
    .inv-band .lbl { font-weight: bold; font-size: 9px; margin-bottom: 3px; }
    .inv-band .line { font-size: 9px; color: {{ $pc['secondary'] }}; }
    .inv-band .name { font-size: 9.5px; color: {{ $pc['ink'] }}; }
    .inv-kv td { padding: 0 0 1px 0; font-size: 9px; }
    .inv-kv .k { font-weight: bold; white-space: nowrap; padding-right: 6px; width: 40%; }

    /* Items: bold header with a rule, airy rows, no grid */
    .inv-items { margin-top: 22px; }
    .inv-items th { font-weight: bold; font-size: 9px; text-align: left; padding: 8px 6px; border-bottom: 1px solid {{ $pc['border_strong'] }}; }
    .inv-items th.r { text-align: right; }
    .inv-items td { padding: 8px 6px; font-size: 9px; }
    .inv-items tbody tr:first-child td { padding-top: 12px; }
    .inv-items .pname { color: {{ $pc['ink'] }}; }
    .inv-items .sub { font-size: 8px; color: {{ $pc['muted'] }}; margin-top: 1px; }
    .inv-items .end td { border-bottom: 1px solid {{ $pc['border'] }}; padding: 0; height: 10px; }

    /* Bottom: message left, totals right */
    .inv-bottom { margin-top: 18px; page-break-inside: avoid; }
    .inv-msg .lbl { font-weight: bold; font-size: 9px; margin-bottom: 6px; }
    .inv-msg .txt { font-size: 9px; color: {{ $pc['secondary'] }}; margin-bottom: 8px; }
    .inv-totals td { padding: 4px 0; font-size: 9px; }
    .inv-totals .k { font-weight: bold; }
    .inv-totals .total td { border-top: 2px solid {{ $pc['ink'] }}; padding-top: 12px; font-size: 15px; font-weight: bold; }
    .inv-totals .after td { padding-top: 8px; }
    .inv-totals .due td { font-weight: bold; color: {{ $pc['danger'] }}; }
    .inv-totals .due.clear td { color: {{ $pc['success'] }}; }

    .inv-sign { margin-top: 44px; page-break-inside: avoid; }
    .inv-sign .line { border-top: 1px solid {{ $pc['border_strong'] }}; padding-top: 5px; font-size: 8.5px; color: {{ $pc['muted'] }}; text-align: center; }
    .inv-note { margin-top: 22px; font-size: 8px; color: {{ $pc['muted'] }}; text-align: center; }
    .inv-footer { position: fixed; left: 0; right: 0; bottom: -40px; border-top: 1px solid {{ $pc['border'] }}; padding-top: 6px; font-size: 8px; color: {{ $pc['muted'] }}; }
    .pn:before { content: counter(page); }
</style>
