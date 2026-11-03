@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Reports Center
@endsection

@push('styles')
    <style>
        .rc-search { max-width: 320px; }
        .rc-group { margin-bottom: var(--space-xl); }
        .rc-group h2 {
            margin: 0 0 var(--space-md);
            font-size: var(--text-caption);
            font-weight: var(--font-weight-medium);
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--color-text-muted);
        }
        .rc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: var(--space-md); }
        .rc-card {
            display: flex;
            gap: var(--space-md);
            padding: var(--space-lg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: var(--color-surface);
            box-shadow: var(--shadow-1);
            color: var(--color-text);
            text-decoration: none;
            min-height: 100%;
        }
        .rc-card:hover { border-color: var(--color-accent); color: var(--color-text); }
        .rc-card:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
        .rc-icon {
            display: inline-grid;
            flex: 0 0 40px;
            width: 40px;
            height: 40px;
            place-items: center;
            border-radius: var(--radius-md);
            background: var(--color-accent-soft);
            color: var(--color-accent-ink);
            font-size: var(--text-body-lg);
        }
        .rc-card b { display: block; font-size: var(--text-body-tabular); font-weight: var(--font-weight-medium); }
        .rc-card small { display: block; margin-top: var(--space-xxs); font-size: var(--text-caption); color: var(--color-text-muted); line-height: 1.45; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
            <div>
                <h4 class="mb-0">Reports Center</h4>
                <small class="text-muted">Every business report in one place. All figures are for {{ \App\Http\Controllers\Admin\BranchController::visibleLabel() }} (the daily cash closing is for {{ $loggedInBranch->name ?? 'your branch' }}); each report exports to PDF and Excel.</small>
            </div>
            <div class="ms-auto">
                <input type="search" id="rcSearch" class="form-control rc-search" placeholder="Find a report…" aria-label="Find a report">
            </div>
        </div>

        @foreach ($groups as $group => $reports)
            <section class="rc-group" data-group>
                <h2>{{ $group }}</h2>
                <div class="rc-grid">
                    @foreach ($reports as $r)
                        <a class="rc-card" href="{{ route('admin.business-reports.show', $r['key']) }}" data-text="{{ strtolower($r['title'] . ' ' . $r['desc']) }}">
                            <span class="rc-icon"><i class="bi {{ $r['icon'] }}" aria-hidden="true"></i></span>
                            <span><b>{{ $r['title'] }}</b><small>{{ $r['desc'] }}</small></span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section class="rc-group" data-group>
            <h2>Also in the system</h2>
            <div class="rc-grid">
                @foreach ([
                    ['admin.reports.stock-ledger', 'bi-box-seam', 'Stock Ledger', 'Every stock movement with running balance.'],
                    ['admin.reports.transaction-ledger', 'bi-journal-text', 'Transaction Ledger', 'Cash, bank and mobile banking report, method by method.'],
                    ['admin.reports.liquid-asset', 'bi-cash-stack', 'Cash & Bank Book', 'Daily opening, in, out and closing per account.'],
                    ['admin.customers.vat', 'bi-percent', 'VAT Report', 'Output VAT minus input VAT = VAT payable.'],
                    ['admin.accounting.profit-loss', 'bi-graph-up', 'Profit & Loss', 'Revenue, cost of goods, expenses and net profit.'],
                    ['admin.reports.salary-sheet', 'bi-people', 'Salary Sheet', 'Monthly salary, payments and dues.'],
                ] as [$route, $icon, $title, $desc])
                    @if (\Illuminate\Support\Facades\Route::has($route))
                        <a class="rc-card" href="{{ route($route) }}" data-text="{{ strtolower($title . ' ' . $desc) }}">
                            <span class="rc-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
                            <span><b>{{ $title }}</b><small>{{ $desc }}</small></span>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        $('#rcSearch').on('input', function () {
            const q = $(this).val().toLowerCase().trim();
            $('.rc-card').each(function () { $(this).toggle(!q || $(this).data('text').includes(q)); });
            $('[data-group]').each(function () { $(this).toggle($(this).find('.rc-card:visible').length > 0); });
        });
    </script>
@endpush
