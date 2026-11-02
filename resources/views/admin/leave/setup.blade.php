@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Leave Setup
@endsection

@push('styles')
    <style>
        /* Tabs (.setup-tabs) come from components.css. */
        .setup-hint { font-size: var(--text-caption); color: var(--color-text-muted); }
        .rules-table th, .rules-table td { min-width: 0 !important; padding: var(--space-sm) !important; vertical-align: middle; }
        .rules-table input { width: 88px; margin: 0 auto; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex align-items-center mb-3">
            <div>
                <h4 class="mb-0">Leave Setup</h4>
                <small class="text-muted">Leave types, leave groups and how many days each group gets per year.</small>
            </div>
            <a href="{{ route('admin.leave.index') }}" class="btn btn-outline-secondary ms-auto">
                <i class="fas fa-plane-departure"></i> Leave Applications
            </a>
        </div>

        <ul class="nav setup-tabs mb-3" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#pane-rules">
                    <span class="tab-icon"><i class="fas fa-table"></i></span> Yearly Leave Rules
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#pane-groups" data-type="groups">
                    <span class="tab-icon"><i class="fas fa-users"></i></span> Leave Groups
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#pane-types" data-type="types">
                    <span class="tab-icon"><i class="fas fa-tags"></i></span> Leave Types
                </button>
            </li>
        </ul>

        <div class="tab-content">
            {{-- ===== Yearly rules ===== --}}
            <div class="tab-pane fade show active" id="pane-rules">
                <div class="card acc-card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <div class="input-group" style="width: 180px;">
                                <span class="input-group-text">Year</span>
                                <input type="number" id="rulesYear" class="form-control" value="{{ $year }}" min="2000" max="2100">
                            </div>
                            <span class="setup-hint">Days of each leave type per year. Every employee of a group gets the group's days; empty = not allowed.</span>
                            <button type="button" class="btn btn-outline-primary ms-auto" id="copyRulesBtn"><i class="fas fa-copy"></i> Copy from last year</button>
                            <button type="button" class="btn btn-primary" id="saveRulesBtn"><i class="fas fa-save"></i> Save Rules</button>
                        </div>
                        <form id="rulesForm"><div class="table-responsive" id="rulesBody"></div></form>
                    </div>
                </div>
            </div>

            @foreach (['groups' => ['Leave Groups', 'Assign a group to each employee on the Employees page (Job tab).', ['Group', 'Days this year', 'Employees']],
                       'types'  => ['Leave Types', 'Unpaid types need no balance; their days are deducted on the salary sheet.', ['Leave Type', 'Code', 'Pay', 'Applications']]] as $key => [$title, $hint, $cols])
                <div class="tab-pane fade" id="pane-{{ $key }}">
                    <div class="card acc-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <span class="setup-hint">{{ $hint }}</span>
                                <button type="button" class="btn btn-primary ms-auto" onclick="LeaveSetup.add('{{ $key }}')">
                                    <i class="fas fa-plus"></i> Add {{ \Illuminate\Support\Str::singular($title) }}
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table id="table-{{ $key }}" class="table table-hover align-middle w-100 acc-table" data-page-length="25" data-order="[]" data-auto-width="false">
                                    <thead>
                                        <tr>
                                            <th data-orderable="false" width="5%">SL.</th>
                                            @foreach ($cols as $c)<th>{{ $c }}</th>@endforeach
                                            <th>Status</th>
                                            <th data-orderable="false" width="8%">Action</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Type / group modal --}}
    <div class="modal fade" id="setupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="setupForm" autocomplete="off">
                    <input type="hidden" name="id" id="setup_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="setupTitle">Add</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="setup_name">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="setup_name" class="form-control" maxlength="100">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6 f-types">
                                <label class="form-label" for="setup_code">Code</label>
                                <input type="text" name="code" id="setup_code" class="form-control" maxlength="20" placeholder="e.g. CL">
                            </div>
                            <div class="col-md-6 f-types">
                                <label class="form-label" for="is_paid">Pay</label>
                                <select name="is_paid" id="is_paid" class="form-select">
                                    <option value="Yes">Paid (uses balance)</option>
                                    <option value="No">Unpaid (deducted from salary)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="setup_status">Status</label>
                                <select name="status" id="setup_status" class="form-select">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="setup_description">Description</label>
                                <textarea name="description" id="setup_description" class="form-control" rows="2" maxlength="500"></textarea>
                            </div>
                        </div>
                        <div class="text-danger small mt-2" id="setup_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="setupSaveBtn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const LeaveSetup = {
            labels: { types: 'Leave Type', groups: 'Leave Group' },
            tables: {},
            type: 'groups',
            modal: null,

            url: function(type, action, id) {
                return "{{ url('admin/leave/setup') }}/" + type + '/' + action + (id ? '/' + id : '');
            },

            initTable: function(type) {
                if (LeaveSetup.tables[type]) {
                    LeaveSetup.tables[type].columns.adjust();
                    return;
                }
                Voucher.keepSerial($('#table-' + type));
                LeaveSetup.tables[type] = $('#table-' + type).DataTable({ ajax: LeaveSetup.url(type, 'list'), processing: true });
            },

            /* ---------- yearly rules ---------- */
            loadRules: function() {
                $.ajax({
                    url: "{{ route('admin.leave.setup.rules') }}",
                    type: 'GET',
                    dataType: 'json',
                    data: { year: $('#rulesYear').val() },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (!response.groups.length || !response.types.length) {
                            $('#rulesBody').html('<div class="text-center text-muted py-4">Add at least one leave group and one leave type first.</div>');
                            return;
                        }
                        let html = '<table class="table table-bordered rules-table mb-0"><thead><tr><th>Group</th>' +
                            response.types.map(function(t) {
                                return '<th class="text-center">' + Voucher.esc(t.name) + (t.is_paid === 'No' ? '<div class="small text-danger fw-normal">unpaid</div>' : '') + '</th>';
                            }).join('') + '<th class="text-center">Total</th></tr></thead><tbody>';

                        response.groups.forEach(function(g) {
                            html += '<tr data-group="' + g.id + '"><td class="fw-semibold">' + Voucher.esc(g.name) + '</td>';
                            response.types.forEach(function(t) {
                                const v = response.values[g.id + '_' + t.id];
                                html += '<td><input type="number" step="0.5" min="0" max="366" class="form-control form-control-sm text-end rule-day"' +
                                    ' name="days[' + g.id + '][' + t.id + ']" value="' + (v === undefined ? '' : v) + '"></td>';
                            });
                            html += '<td class="text-end fw-bold row-total">0</td></tr>';
                        });
                        $('#rulesBody').html(html + '</tbody></table>');
                        LeaveSetup.totals();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                    }
                });
            },

            totals: function() {
                $('#rulesBody tr[data-group]').each(function() {
                    let sum = 0;
                    $(this).find('.rule-day').each(function() { sum += parseFloat(this.value) || 0; });
                    $(this).find('.row-total').text(sum);
                });
            },

            /* ---------- types / groups ---------- */
            prepare: function(type, title) {
                LeaveSetup.type = type;
                const $form = $('#setupForm');
                $form[0].reset();
                Voucher.clearErrors($form);
                $('#setup_error').text('');
                $('#setup_id').val('');
                $('.f-types').toggleClass('d-none', type !== 'types').find('input, select').prop('disabled', type !== 'types');
                $('#setupTitle').text(title);
            },

            add: function(type) {
                LeaveSetup.prepare(type, 'Add ' + LeaveSetup.labels[type]);
                LeaveSetup.modal.show();
            },

            edit: function(type, id) {
                $.ajax({
                    url: LeaveSetup.url(type, 'edit', id),
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const d = response.data;
                        LeaveSetup.prepare(type, 'Edit ' + LeaveSetup.labels[type]);
                        $('#setup_id').val(d.id);
                        $('#setup_name').val(d.name);
                        $('#setup_code').val(d.code || '');
                        $('#is_paid').val(d.is_paid || 'Yes');
                        $('#setup_status').val(d.status);
                        $('#setup_description').val(d.description || '');
                        LeaveSetup.modal.show();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load.');
                    }
                });
            },

            post: function(type, action, id) {
                $.ajax({
                    url: LeaveSetup.url(type, action, id),
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        response.status ? toastr.success(response.message) : toastr.error(response.message);
                        LeaveSetup.reload();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Something went wrong.');
                    }
                });
            },

            reload: function() {
                Object.keys(LeaveSetup.tables).forEach(function(t) { LeaveSetup.tables[t].ajax.reload(null, false); });
                LeaveSetup.loadRules();
            },

            toggleStatus: function(type, id) { LeaveSetup.post(type, 'status', id); },

            remove: function(type, id) {
                Swal.fire({ title: 'Delete ' + LeaveSetup.labels[type].toLowerCase() + '?', text: 'Its yearly rules are removed too.', icon: 'warning',
                    showCancelButton: true, confirmButtonColor: 'var(--color-danger)', confirmButtonText: 'Yes, delete', reverseButtons: true })
                    .then(function(r) { if (r.isConfirmed) LeaveSetup.post(type, 'delete', id); });
            }
        };

        $(document).ready(function() {
            LeaveSetup.modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('setupModal'));

            $('.setup-tabs [data-bs-toggle="tab"]').on('shown.bs.tab', function() {
                if ($(this).data('type')) LeaveSetup.initTable($(this).data('type'));
            });

            $('#rulesYear').on('change', LeaveSetup.loadRules);
            $('#rulesBody').on('input', '.rule-day', LeaveSetup.totals);

            $('#saveRulesBtn').on('click', function() {
                const $btn = $(this).prop('disabled', true);
                $.ajax({
                    url: "{{ route('admin.leave.setup.rules.save') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $('#rulesForm').serialize() + '&year=' + $('#rulesYear').val(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        LeaveSetup.reload();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to save the rules.');
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });

            $('#copyRulesBtn').on('click', function() {
                const year = parseInt($('#rulesYear').val());
                $.ajax({
                    url: "{{ route('admin.leave.setup.rules.copy') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: { from_year: year - 1, year: year },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        LeaveSetup.loadRules();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to copy the rules.');
                    }
                });
            });

            $('#setupForm').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#setupSaveBtn').prop('disabled', true);
                $.ajax({
                    url: LeaveSetup.url(LeaveSetup.type, 'save'),
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        LeaveSetup.modal.hide();
                        LeaveSetup.reload();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($('#setupForm'), xhr.responseJSON.errors, $('#setup_error'));
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save.');
                        }
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });

            LeaveSetup.loadRules();
        });
    </script>
@endpush
