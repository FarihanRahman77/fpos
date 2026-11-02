@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Due Reminders
@endsection

@push('styles')
    <style>
        .due-preview { font-size: var(--text-caption); color: var(--color-text-secondary); max-width: 420px; white-space: normal; }
        .due-amount { font-feature-settings: var(--font-feature-numeric); font-weight: var(--font-weight-medium); }
        .due-bar { position: sticky; bottom: 0; z-index: 2; background: var(--color-surface); border-top: 1px solid var(--color-border); box-shadow: var(--shadow-1); }
        .due-off { padding: var(--space-md); border-radius: var(--radius-md); background: var(--color-warning-soft); color: var(--color-text); font-size: var(--text-caption); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Due Reminders</h4>
                <small class="text-muted">Customers who owe money. Tick the ones to remind and send an SMS.</small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <a href="{{ route('admin.system.sms.log') }}" class="btn btn-outline-secondary"><i class="fas fa-list"></i> SMS Log</a>
                <a href="{{ route('admin.system.email-sms') }}" class="btn btn-outline-secondary"><i class="fas fa-cog"></i> SMS Settings</a>
            </div>
        </div>

        @if (!$smsOn)
            <div class="due-off mb-3"><i class="fas fa-exclamation-triangle me-1"></i> SMS is turned off. Turn it on and save the BulkSMSBD API key in <a href="{{ route('admin.system.email-sms') }}">Email &amp; SMS</a> before sending.</div>
        @elseif (!$enabled)
            <div class="due-off mb-3"><i class="fas fa-info-circle me-1"></i> "Allow reminders" is off for the due reminder template (Email &amp; SMS). You can still send reminders by hand from this page.</div>
        @endif

        <div class="card acc-card">
            <div class="card-body">
                <div class="mb-2 small text-muted">Template: <span class="text-body">{{ $template }}</span></div>
                <div class="table-responsive">
                    <table id="dueTable" class="table table-hover align-middle w-100 acc-table" data-order="[]">
                        <thead>
                            <tr>
                                <th data-orderable="false" width="4%"><input type="checkbox" class="form-check-input" id="checkAll" aria-label="Select all"></th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th class="text-end">Due</th>
                                <th>Last Reminder</th>
                                <th data-orderable="false">SMS Preview</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="due-bar card-footer d-flex flex-wrap align-items-center gap-2">
                <span id="selInfo" class="small text-muted">Nothing selected</span>
                <button type="button" class="btn btn-primary ms-auto" id="sendBtn" disabled><i class="fas fa-paper-plane"></i> Send SMS</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            const esc = Voucher.esc, money = Voucher.money;
            const selected = new Set();
            let rows = [];

            const table = $('#dueTable').DataTable({
                processing: true, pageLength: 50, autoWidth: false,
                ajax: { url: "{{ route('admin.system.sms.due.list') }}", dataSrc: function(json) { rows = json.data || []; return rows; } },
                columns: [
                    { data: null, render: function(r) {
                        return r.valid ? '<input type="checkbox" class="form-check-input row-check" value="' + r.id + '"' + (selected.has(r.id) ? ' checked' : '') + ' aria-label="Select">'
                            : '<i class="fas fa-ban text-muted" title="No valid mobile number"></i>';
                    } },
                    { data: 'name', render: function(v, t, r) { return esc(v) + '<div class="text-muted small">' + esc(r.code || '') + ' · ' + esc(r.type) + '</div>'; } },
                    { data: 'phone', render: function(v, t, r) { return esc(v || '') + (r.valid ? '' : '<div class="small text-danger">' + (v ? 'Not a BD mobile' : 'No phone') + '</div>'); } },
                    { data: 'due', className: 'text-end', render: function(v, t) { return t === 'display' ? '<span class="due-amount">' + money(v) + '</span>' : v; } },
                    { data: 'last', render: function(v) { return v ? esc(v) : '<span class="text-muted">Never</span>'; } },
                    { data: 'preview', render: function(v) { return '<div class="due-preview">' + esc(v) + '</div>'; } }
                ]
            });

            function info() {
                let total = 0;
                rows.forEach(function(r) { if (selected.has(r.id)) total += parseFloat(r.due) || 0; });
                $('#selInfo').text(selected.size ? selected.size + ' selected · total due ' + money(total) : 'Nothing selected');
                $('#sendBtn').prop('disabled', !selected.size);
            }

            $('#dueTable').on('change', '.row-check', function() {
                const id = parseInt(this.value);
                this.checked ? selected.add(id) : selected.delete(id);
                info();
            });
            $('#checkAll').on('change', function() {
                const on = this.checked;
                // all rows that match the current search, not only the visible page
                table.rows({ search: 'applied' }).data().each(function(r) { if (r.valid) on ? selected.add(r.id) : selected.delete(r.id); });
                $('#dueTable .row-check').prop('checked', on);
                info();
            });

            $('#sendBtn').on('click', function() {
                const ids = Array.from(selected);
                Swal.fire({ title: 'Send ' + ids.length + ' due reminder SMS?', text: 'Each customer gets the preview text shown, with their current due.', icon: 'question', showCancelButton: true, confirmButtonText: 'Send', reverseButtons: true })
                    .then(function(r) {
                        if (!r.isConfirmed) return;
                        const $btn = $('#sendBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Sending…');
                        $.ajax({
                            url: "{{ route('admin.system.sms.due.send') }}",
                            type: 'POST',
                            dataType: 'json',
                            data: { party_ids: ids },
                            timeout: 600000,
                            success: function(response) {
                                // alert(JSON.stringify(response));
                                toastr.success(response.message);
                                if (response.failed && response.failed.length) Swal.fire({ title: response.message, html: response.failed.map(esc).join('<br>'), icon: 'warning' });
                                selected.clear(); $('#checkAll').prop('checked', false); table.ajax.reload(null, false);
                            },
                            error: function(xhr) {
                                // alert(JSON.stringify(xhr));
                                const j = xhr.responseJSON || {};
                                if (j.failed && j.failed.length) Swal.fire({ title: j.message, html: j.failed.map(esc).join('<br>'), icon: 'error' });
                                else Voucher.ajaxError(xhr, 'Unable to send.');
                            },
                            complete: function() { $btn.html('<i class="fas fa-paper-plane"></i> Send SMS'); info(); }
                        });
                    });
            });
        });
    </script>
@endpush
