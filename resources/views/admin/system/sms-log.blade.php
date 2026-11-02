@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - SMS Log
@endsection

@push('styles')
    <style>
        .sms-text { font-size: var(--text-caption); max-width: 420px; white-space: normal; }
        .sms-result { font-size: var(--text-caption); }
        .sms-result.ok { color: var(--color-success); }
        .sms-result.bad { color: var(--color-danger); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">SMS Log</h4>
                <small class="text-muted">Every SMS attempt, sent or failed, with the gateway's answer.</small>
            </div>
            <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
                <span class="sms-result" id="balanceResult"></span>
                <button type="button" class="btn btn-outline-secondary" id="balanceBtn"><i class="fas fa-wallet"></i> Check Balance</button>
                <a href="{{ route('admin.system.sms.due') }}" class="btn btn-outline-secondary"><i class="fas fa-bell"></i> Due Reminders</a>
            </div>
        </div>

        <div class="card acc-card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="f_from">From</label>
                        <input type="date" id="f_from" class="form-control" value="{{ now()->subDays(29)->toDateString() }}">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="f_to">To</label>
                        <input type="date" id="f_to" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="f_status">Status</label>
                        <select id="f_status" class="form-select">
                            <option value="">All</option><option>Sent</option><option>Failed</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="f_type">Type</label>
                        <select id="f_type" class="form-select">
                            <option value="">All types</option>
                            @foreach ($types as $t)
                                <option>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="smsTable" class="table table-hover align-middle w-100 acc-table">
                        <thead>
                            <tr>
                                <th width="4%">SL.</th>
                                <th>When</th>
                                <th>To</th>
                                <th>Message</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>By</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            const table = $('#smsTable').DataTable({
                processing: true, serverSide: true, ordering: false, pageLength: 25, autoWidth: false,
                ajax: {
                    url: "{{ route('admin.system.sms.log.list') }}",
                    data: function(d) { return $.extend(d, { from: $('#f_from').val(), to: $('#f_to').val(), status: $('#f_status').val(), type: $('#f_type').val() }); }
                }
            });
            $('#f_from, #f_to, #f_status, #f_type').on('change', function() { table.ajax.reload(); });

            $('#balanceBtn').on('click', function() {
                const $btn = $(this).prop('disabled', true), $r = $('#balanceResult');
                $r.removeClass('ok bad').text('Checking…');
                $.ajax({
                    url: "{{ route('admin.system.sms.balance') }}",
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        $r.addClass('ok').text(response.message);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        $r.addClass('bad').text((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to check the balance.');
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });
        });
    </script>
@endpush
