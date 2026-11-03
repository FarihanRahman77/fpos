@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} Sale Return {{ $saleReturn->return_no }}
@endsection

@section('content')
    <div class="container-fluid px-3 px-lg-4 py-4">
        <div class="card return-view">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-0">Sale Return {{ $saleReturn->return_no }}</h5>
                    <small class="text-muted">{{ \Carbon\Carbon::parse($saleReturn->datetime)->format('d M Y, h:i A') }}</small>
                </div>
                <div class="d-flex gap-2 no-print">
                    <a href="{{ route('sales.returns.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Return List
                    </a>
                    <a href="{{ route('sales.return.invoice.pdf', $saleReturn->id) }}" target="_blank" class="btn btn-outline-secondary">
                        <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                    </a>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <table class="table table-bordered mb-0">
                            <tr><th class="w-40">Return No</th><td>{{ $saleReturn->return_no }}</td></tr>
                            <tr><th>Sale Invoice</th><td>{{ $sale->invoice_no ?? '-' }}</td></tr>
                            <tr><th>Sale Type</th><td>{{ $saleReturn->sale_type ?? '-' }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered mb-0">
                            <tr><th class="w-40">Customer</th><td>{{ $customer->name ?? 'Walk-In Customer' }}</td></tr>
                            <tr><th>Phone</th><td>{{ $customer->phone ?? '-' }}</td></tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="badge {{ $saleReturn->status === 'Active' ? 'bg-success' : 'bg-secondary' }}">{{ $saleReturn->status }}</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th style="width: 5%">#</th>
                                <th>Product</th>
                                <th>Variant</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Sale Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $key => $item)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $item->product_name ?? '-' }}</td>
                                    <td>{{ $item->variant_name ?? '-' }}</td>
                                    <td class="text-end">{{ rtrim(rtrim(number_format((float) $item->qty, 2), '0'), '.') }}</td>
                                    <td class="text-end">{{ number_format((float) $item->sale_price, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">No return items found.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">Subtotal</th>
                                <th class="text-end">{{ number_format((float) $saleReturn->subtotal, 2) }}</th>
                            </tr>
                            @if ((float) ($saleReturn->vat_amount ?? 0) > 0)
                                <tr>
                                    <th colspan="5" class="text-end fw-normal">VAT{{ ($sale->vat_inclusive ?? 'No') === 'Yes' ? ' (included in prices)' : ' (included in the refund)' }}</th>
                                    <th class="text-end fw-normal">{{ number_format((float) $saleReturn->vat_amount, 2) }}</th>
                                </tr>
                            @endif
                            <tr>
                                <th colspan="5" class="text-end">Grand Total</th>
                                <th class="text-end">{{ number_format((float) $saleReturn->grand_total, 2) }}</th>
                            </tr>
                            <tr>
                                <th colspan="5" class="text-end fw-normal">Refund</th>
                                <th class="text-end fw-normal">{{ ($saleReturn->refund_mode ?? 'Credit') === 'Cash' ? 'Cash ' . number_format((float) $saleReturn->refund_amount, 2) : 'Customer credit' }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if (!empty($saleReturn->remarks))
                    <div class="mt-3">
                        <div class="form-label fw-medium">Remarks</div>
                        <div class="return-remarks">{{ $saleReturn->remarks }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .return-view .w-40 { width: 40%; }

        .return-remarks {
            padding: var(--space-md);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface-soft);
            white-space: pre-line;
        }

        @media print {
            body * { visibility: hidden; }
            .return-view, .return-view * { visibility: visible; }
            .return-view { position: absolute; inset: 0 0 auto 0; border: 0 !important; box-shadow: none !important; }
            .no-print { display: none !important; }
        }
    </style>
@endpush
