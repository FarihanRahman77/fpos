@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@php
    $m = fn ($v) => number_format((float) $v, 2);
    $icons = ['created' => 'fa-plus', 'repriced' => 'fa-tags', 'updated' => 'fa-pen', 'emailed' => 'fa-paper-plane', 'shared' => 'fa-share-alt',
              'downloaded' => 'fa-download', 'status' => 'fa-flag', 'duplicated' => 'fa-copy', 'deleted' => 'fa-trash'];
@endphp

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Quotation Log · {{ $customer->name }}
@endsection

@push('styles')
    <style>
        .cl-avatar { width: 56px; height: 56px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
            background: var(--color-accent-soft); color: var(--color-accent-ink); font-size: var(--text-heading-md); flex: 0 0 auto; }
        .cl-day { font-size: var(--text-micro); text-transform: uppercase; letter-spacing: .04em; color: var(--color-text-muted); margin: var(--space-lg) 0 var(--space-sm); }
        .cl-day:first-child { margin-top: 0; }
        .cl-item { display: flex; gap: var(--space-md); padding: var(--space-sm) 0; border-bottom: 1px solid var(--color-border); }
        .cl-item:last-child { border-bottom: 0; }
        .cl-icon { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
            background: var(--color-surface-soft); color: var(--color-text-secondary); flex: 0 0 auto; font-size: var(--text-caption); }
        .cl-icon.repriced { background: var(--color-warning-soft); color: var(--color-warning); }
        .cl-icon.emailed, .cl-icon.shared { background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .cl-icon.status { background: var(--color-success-soft); color: var(--color-success); }
        .cl-icon.deleted { background: var(--color-danger-soft); color: var(--color-danger); }
        .cl-quote { display: flex; align-items: center; gap: var(--space-md); padding: var(--space-md); border: 1px solid var(--color-border);
            border-radius: var(--radius-md); margin-bottom: var(--space-sm); color: inherit; }
        .cl-quote:hover { border-color: var(--color-accent); background: var(--color-surface-hover); color: inherit; }
        .cl-amount { font-feature-settings: var(--font-feature-numeric); font-weight: var(--font-weight-medium); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
            <span class="cl-avatar">{{ mb_strtoupper(mb_substr($customer->name, 0, 1)) }}</span>
            <div>
                <h4 class="mb-0">{{ $customer->name }}</h4>
                <small class="text-muted">
                    Quotation log{{ $customer->code ? ' · ' . $customer->code : '' }}{{ $customer->phone ? ' · ' . $customer->phone : '' }}{{ $customer->email ? ' · ' . $customer->email : '' }}
                </small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('admin.quotations.index', ['customer_id' => $customer->id]) }}" class="btn btn-outline-secondary"><i class="fas fa-list"></i> List</a>
                <a href="{{ route('admin.quotations.create', ['customer_id' => $customer->id]) }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Quotation</a>
            </div>
        </div>

        <div class="row g-3 mb-3">
            @foreach ([['Quotations', $stats['count'], 'fas fa-file-alt', 'i1'], ['Total Quoted', $m($stats['quoted']), 'fas fa-coins', 'i3'],
                       ['Accepted', $stats['won'] . ' · ' . $m($stats['accepted']), 'fas fa-handshake', 'i4'], ['Re-prices', $stats['repriced'], 'fas fa-tags', 'i2']] as [$label, $value, $icon, $cls])
                <div class="col-6 col-lg-3">
                    <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                        <div class="icon {{ $cls }}"><i class="{{ $icon }}"></i></div>
                        <div><div class="text-muted small">{{ $label }}</div><h5 class="mb-0">{{ $value }}</h5></div>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="row g-3">
            <div class="col-xl-5">
                <div class="card acc-card">
                    <div class="card-body">
                        <h6 class="mb-3">Quotations</h6>
                        @forelse ($quotations as $q)
                            <a href="{{ route('admin.quotations.show', $q->id) }}" class="cl-quote">
                                <div class="flex-fill">
                                    <div class="fw-semibold">{{ $q->quotation_no }}
                                        @if ($q->revision_no > 1)<span class="badge bg-primary-subtle text-primary-emphasis ms-1">Rev {{ $q->revision_no }}</span>@endif
                                    </div>
                                    <div class="text-muted small">{{ \Carbon\Carbon::parse($q->quotation_date)->format('d M Y') }}{{ $q->title ? ' · ' . $q->title : '' }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="cl-amount">{{ $m($q->grand_total) }}</div>
                                    {!! \App\Http\Controllers\Admin\SaleQuotationController::statusBadge($q->quotation_status) !!}
                                </div>
                            </a>
                        @empty
                            <div class="text-muted text-center py-4">No quotations for this customer yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="card acc-card">
                    <div class="card-body">
                        <h6 class="mb-3">Timeline</h6>
                        @forelse ($logs as $day => $entries)
                            <div class="cl-day">{{ \Carbon\Carbon::parse($day)->isToday() ? 'Today' : (\Carbon\Carbon::parse($day)->isYesterday() ? 'Yesterday' : \Carbon\Carbon::parse($day)->format('l, d M Y')) }}</div>
                            @foreach ($entries as $log)
                                <div class="cl-item">
                                    <span class="cl-icon {{ $log->action }}"><i class="fas {{ $icons[$log->action] ?? 'fa-circle' }}"></i></span>
                                    <div class="flex-fill">
                                        <div>
                                            @if ($log->quotation_deleted === 'No')
                                                <a href="{{ route('admin.quotations.show', $log->sale_quotation_id) }}" class="fw-semibold">{{ $log->quotation_no }}</a>
                                            @else
                                                <span class="fw-semibold text-decoration-line-through">{{ $log->quotation_no }}</span>
                                            @endif
                                            · {{ $log->message }}
                                        </div>
                                        <div class="text-muted small">{{ \Carbon\Carbon::parse($log->created_at)->format('h:i A') }}{{ $log->user_name ? ' · ' . $log->user_name : '' }}</div>
                                    </div>
                                </div>
                            @endforeach
                        @empty
                            <div class="text-muted text-center py-4">Nothing logged yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
