{{-- Daily cash closing: the cash ledger of the day, other methods alongside, and opening → closing. --}}
@php
    $cd = $extra['cash_day'];
    $amt = fn ($v) => number_format((float) $v, 2);
@endphp

<div class="row g-4">
    <div class="col-lg-6">
        <h6 class="cd-title">Cash Ledger Details</h6>
        <table class="table table-sm table-bordered cd-table mb-0">
            <thead><tr><th>Type</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
                @foreach ($cd['cash'] as $l)
                    <tr class="{{ $l['amount'] > 0 ? '' : 'cd-zero' }}">
                        <td>{{ $l['label'] }}</td>
                        <td class="num {{ $l['amount'] > 0 ? ($l['sign'] > 0 ? 'cd-in' : 'cd-out') : '' }}">{{ $amt($l['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td>Balance (today)</td><td class="num {{ $cd['today'] < 0 ? 'cd-out' : 'cd-in' }}">{{ $amt($cd['today']) }}</td></tr>
            </tfoot>
        </table>
    </div>

    <div class="col-lg-6">
        <h6 class="cd-title">Other Methods Ledger Details</h6>
        @if ($cd['others'])
            <table class="table table-sm table-bordered cd-table mb-0">
                <tbody>
                    @foreach ($cd['others'] as $method => $lines)
                        <tr class="cd-method"><td colspan="2">{{ $method }}</td></tr>
                        @foreach ($lines as $l)
                            <tr class="{{ $l['amount'] > 0 ? '' : 'cd-zero' }}">
                                <td>{{ $l['label'] }}</td>
                                <td class="num {{ $l['amount'] > 0 ? ($l['sign'] > 0 ? 'cd-in' : 'cd-out') : '' }}">{{ $amt($l['amount']) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
            <p class="small text-muted mt-2 mb-0">For reference only — bank and other methods do not change the cash closing.</p>
        @else
            <p class="text-muted small">This branch has no bank or other payment method ledgers.</p>
        @endif
    </div>
</div>

<form id="cashDayForm" class="cd-summary">
    <input type="hidden" name="closing_date" value="{{ $cd['date'] }}">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="cdOpening">Opening Balance</label>
            <input type="text" id="cdOpening" class="form-control num" value="{{ $amt($cd['opening']) }}" readonly>
            <small class="text-muted">From {{ $cd['open_note'] }}</small>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="cdToday">Total Amount (today)</label>
            <input type="text" id="cdToday" class="form-control num" value="{{ $amt($cd['today']) }}" readonly>
            <small class="text-muted">Cash in − cash out on the day</small>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="cdClosing">Closing Amount</label>
            <input type="text" id="cdClosing" class="form-control num cd-closing" value="{{ $amt($cd['closing']) }}" readonly>
            <small class="text-muted">Becomes the next day's opening</small>
        </div>
        <div class="col-12">
            <label class="form-label" for="cdRemarks">Remarks</label>
            <input type="text" id="cdRemarks" name="remarks" class="form-control" maxlength="255" placeholder="Optional" value="{{ $cd['saved']['remarks'] ?? '' }}">
        </div>
    </div>

    @if (abs($cd['closing'] - $cd['books_closing']) > 0.004)
        <div class="alert alert-warning mt-3 mb-0">
            Cash in the books at the end of this day is {{ $amt($cd['books_closing']) }}, which differs from this closing by
            {{ $amt($cd['closing'] - $cd['books_closing']) }} — usually because entries were changed after an earlier day was saved. Saving the earlier day again brings them in line.
        </div>
    @endif
    @if ($cd['changed'])
        <div class="alert alert-warning mt-3 mb-0">Entries for this day changed after it was saved (saved closing {{ $amt($cd['saved']['closing']) }}). Save again to update it.</div>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
        @if ($cd['saved'])
            <button type="button" id="cashDayClear" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Clear</button>
            <span class="small text-muted">Saved {{ $cd['saved']['at'] }}@if ($cd['saved']['by']) by {{ $cd['saved']['by'] }}@endif</span>
        @else
            <span class="small text-muted">Not saved yet.</span>
        @endif
        <button type="submit" class="btn btn-primary ms-auto"><i class="bi bi-floppy"></i> {{ $cd['saved'] ? 'Save Again' : 'Save Today Report' }}</button>
    </div>
</form>

@if ($notes)
    <ul class="br-notes">@foreach ($notes as $n)<li>{{ $n }}</li>@endforeach</ul>
@endif
