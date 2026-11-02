@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Activity Log
@endsection

@push('styles')
    <style>
        .log-kv { font-size: var(--text-caption); }
        .log-kv .k { color: var(--color-text-muted); }
        .log-changes td, .log-changes th { padding: var(--space-xs) var(--space-sm); font-size: var(--text-caption); vertical-align: top; }
        .log-changes th { width: 34%; color: var(--color-text-muted); font-weight: var(--font-weight-medium); font-family: var(--font-mono); word-break: break-all; }
        .log-changes td { word-break: break-word; white-space: pre-wrap; }
        .log-muted { color: var(--color-text-muted); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Activity Log</h4>
                <small class="text-muted">Every save, update and delete made by a signed-in user, plus logins and logouts.</small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" id="retentionBtn"><i class="fas fa-broom"></i> Retention &amp; Prune</button>
            </div>
        </div>

        <div class="card acc-card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="f_from">From</label>
                        <input type="date" id="f_from" class="form-control" value="{{ now()->subDays(6)->toDateString() }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="f_to">To</label>
                        <input type="date" id="f_to" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="f_user">User</label>
                        <select id="f_user" class="form-select">
                            <option value="">All users</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="f_module">Module</label>
                        <select id="f_module" class="form-select">
                            <option value="">All modules</option>
                            @foreach ($modules as $m)
                                <option value="{{ $m }}">{{ ucwords(str_replace(['-', '_'], ' ', $m)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label" for="f_method">Method</label>
                        <select id="f_method" class="form-select">
                            <option value="">All</option>
                            <option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="f_q">Search</label>
                        <input type="search" id="f_q" class="form-control" placeholder="Action, URL, value, IP…">
                    </div>
                </div>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="logTable" class="table table-hover align-middle w-100 acc-table">
                        <thead>
                            <tr>
                                <th width="4%">SL.</th>
                                <th>When</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Request</th>
                                <th>IP</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail --}}
    <div class="modal fade" id="logModal" tabindex="-1" aria-labelledby="logTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logTitle">Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="logBody"></div>
            </div>
        </div>
    </div>

    {{-- Retention --}}
    <div class="modal fade" id="retentionModal" tabindex="-1" aria-labelledby="retTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="retentionForm" autocomplete="off" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="retTitle">Retention &amp; Prune</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="activity_retention_days">Keep the log for (days)</label>
                        <input type="number" name="activity_retention_days" id="activity_retention_days" class="form-control" min="1" max="3650" value="{{ $retention }}">
                        <div class="text-danger small field-error"></div>
                        <small class="text-muted d-block mt-2">Older entries are removed when you press Prune, or every day if the scheduler runs the prune task.</small>
                        <div class="text-danger small mt-2" id="ret_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-danger me-auto" id="pruneBtn"><i class="fas fa-trash"></i> Prune Now</button>
                        <button type="submit" class="btn btn-primary" id="retSave">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const esc = Voucher.esc;
        const Activity = {
            table: null,
            params: function() {
                return { from: $('#f_from').val(), to: $('#f_to').val(), user_id: $('#f_user').val(), module: $('#f_module').val(), method: $('#f_method').val(), q: $('#f_q').val() };
            },
            reload: function() { Activity.table.ajax.reload(); },

            value: function(v) {
                if (v === null || v === undefined || v === '') return '<span class="log-muted">—</span>';
                if (typeof v === 'object') return Activity.table_(v);
                return esc(v);
            },
            table_: function(obj) {
                const keys = Object.keys(obj);
                if (!keys.length) return '<span class="log-muted">(empty)</span>';
                return '<table class="table table-sm table-bordered log-changes mb-0"><tbody>' + keys.map(function(k) {
                    return '<tr><th>' + esc(k) + '</th><td>' + Activity.value(obj[k]) + '</td></tr>';
                }).join('') + '</tbody></table>';
            },

            view: function(id) {
                $.ajax({
                    url: "{{ url('admin/system/activity-log') }}/" + id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const a = response.data;
                        const kv = function(k, v) { return '<div class="col-6 col-md-4 log-kv"><div class="k">' + k + '</div><div class="text-break">' + v + '</div></div>'; };
                        $('#logTitle').text(a.action);
                        $('#logBody').html(
                            '<div class="row g-3 mb-3">' +
                                kv('When', esc(a.created_at)) + kv('User', esc(a.user_name || 'System')) + kv('Branch', esc(a.branch_name || '—')) +
                                kv('Request', esc(a.method) + ' ' + esc(a.url || '')) + kv('Route', esc(a.route_name || '—')) +
                                kv('Status', esc(a.status_code || '—')) +
                                kv('Record', a.subject_id ? esc((a.subject_type || '') + ' #' + a.subject_id) : '—') +
                                kv('IP', esc(a.ip || '')) + kv('Browser', '<span class="small">' + esc(a.user_agent || '') + '</span>') +
                            '</div><h6>Submitted data</h6>' +
                            (a.changes ? Activity.table_(a.changes) : '<p class="log-muted">No data was submitted.</p>') +
                            '<p class="small text-muted mt-2 mb-0">Passwords, tokens and API keys are never stored. Long values are shortened.</p>'
                        );
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('logModal')).show();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load the entry.');
                    }
                });
            }
        };

        $(function() {
            Activity.table = $('#logTable').DataTable({
                processing: true, serverSide: true, searching: false, ordering: false, pageLength: 25, autoWidth: false,
                ajax: { url: "{{ route('admin.system.activity.list') }}", data: function(d) { return $.extend(d, Activity.params()); } },
                columnDefs: [{ targets: [0, 6], orderable: false }]
            });
            let timer;
            $('#f_from, #f_to, #f_user, #f_module, #f_method').on('change', Activity.reload);
            $('#f_q').on('input', function() { clearTimeout(timer); timer = setTimeout(Activity.reload, 400); });

            const retModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('retentionModal'));
            $('#retentionBtn').on('click', function() { retModal.show(); });
            $('#retentionForm').on('submit', function(e) {
                e.preventDefault();
                const $f = $(this), $btn = $('#retSave').prop('disabled', true);
                Voucher.clearErrors($f);
                $.ajax({
                    url: "{{ route('admin.system.activity.retention') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $f.serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        retModal.hide();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) Voucher.showErrors($f, xhr.responseJSON.errors, $('#ret_error'));
                        else Voucher.ajaxError(xhr, 'Unable to save.');
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });
            $('#pruneBtn').on('click', function() {
                Swal.fire({
                    title: 'Prune the activity log?', text: 'Entries older than the saved retention (' + $('#activity_retention_days').val() + ' days) are deleted for good. Save first if you changed the number.',
                    icon: 'warning', showCancelButton: true, confirmButtonText: 'Prune', reverseButtons: true
                }).then(function(r) {
                    if (!r.isConfirmed) return;
                    $.ajax({
                        url: "{{ route('admin.system.activity.prune') }}",
                        type: 'POST',
                        dataType: 'json',
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            toastr.success(response.message);
                            retModal.hide();
                            Activity.reload();
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            Voucher.ajaxError(xhr, 'Unable to prune.');
                        }
                    });
                });
            });
        });
    </script>
@endpush
