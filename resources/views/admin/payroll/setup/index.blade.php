@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Payroll Setup
@endsection

@push('styles')
    <style>
        .setup-tabs { border-bottom: 0; gap: 8px; }
        .setup-tabs .nav-link {
            border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; color: #475569;
            font-weight: 600; font-size: .9rem; padding: 10px 16px; display: flex; align-items: center; gap: 8px;
        }
        .setup-tabs .nav-link .tab-icon {
            width: 30px; height: 30px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
            background: #f1f5f9; color: #64748b;
        }
        .setup-tabs .nav-link .tab-count { font-size: .72rem; background: #f1f5f9; color: #64748b; border-radius: 999px; padding: 2px 8px; }
        .setup-tabs .nav-link.active { border-color: var(--admin-primary, #916100); color: var(--admin-primary, #916100); box-shadow: 0 4px 14px rgba(145, 97, 0, .12); }
        .setup-tabs .nav-link.active .tab-icon { background: var(--admin-primary, #916100); color: #fff; }
        .setup-tabs .nav-link.active .tab-count { background: rgba(145, 97, 0, .12); color: var(--admin-primary, #916100); }
        .setup-hint { font-size: .8rem; color: #64748b; }
        .step-gross { font-size: 1.15rem; font-weight: 700; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex align-items-center mb-3">
            <div>
                <h4 class="mb-0">Payroll Setup</h4>
                <small class="text-muted">Departments, designations and the pay scale (grades &amp; steps) used by employees.</small>
            </div>
            <a href="{{ route('admin.employee.index') }}" class="btn btn-outline-secondary ms-auto">
                <i class="fas fa-users"></i> Employees
            </a>
        </div>

        <ul class="nav setup-tabs mb-3" role="tablist">
            @foreach ([
                'departments'  => ['Departments', 'fas fa-sitemap'],
                'designations' => ['Designations', 'fas fa-id-badge'],
                'grades'       => ['Pay Grades', 'fas fa-layer-group'],
                'steps'        => ['Steps', 'fas fa-stream'],
            ] as $key => [$label, $icon])
                <li class="nav-item">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }}" type="button" data-bs-toggle="tab"
                        data-bs-target="#pane-{{ $key }}" data-type="{{ $key }}">
                        <span class="tab-icon"><i class="{{ $icon }}"></i></span>
                        {{ $label }}
                        <span class="tab-count" id="count-{{ $key }}">0</span>
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">
            {{-- ===== Departments ===== --}}
            <div class="tab-pane fade show active" id="pane-departments">
                <div class="card acc-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="setup-hint">Departments group designations and employees.</span>
                            <button type="button" class="btn btn-primary ms-auto" onclick="Setup.add('departments')">
                                <i class="fas fa-plus"></i> Add Department
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table id="table-departments" class="table table-hover align-middle w-100 acc-table"
                                data-page-length="25" data-order="[]" data-auto-width="false">
                                <thead>
                                    <tr>
                                        <th data-orderable="false" width="5%">SL.</th>
                                        <th>Department</th>
                                        <th>Code</th>
                                        <th class="text-center">Designations</th>
                                        <th class="text-center">Employees</th>
                                        <th>Status</th>
                                        <th data-orderable="false" width="8%">Action</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Designations ===== --}}
            <div class="tab-pane fade" id="pane-designations">
                <div class="card acc-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="setup-hint">A designation can belong to one department, or to all.</span>
                            <button type="button" class="btn btn-primary ms-auto" onclick="Setup.add('designations')">
                                <i class="fas fa-plus"></i> Add Designation
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table id="table-designations" class="table table-hover align-middle w-100 acc-table"
                                data-page-length="25" data-order="[]" data-auto-width="false">
                                <thead>
                                    <tr>
                                        <th data-orderable="false" width="5%">SL.</th>
                                        <th>Designation</th>
                                        <th>Department</th>
                                        <th class="text-center">Employees</th>
                                        <th>Status</th>
                                        <th data-orderable="false" width="8%">Action</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Pay grades ===== --}}
            <div class="tab-pane fade" id="pane-grades">
                <div class="card acc-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="setup-hint">A pay grade (scale) holds the salary steps. Used by employees on the <b>Scale</b> structure.</span>
                            <button type="button" class="btn btn-primary ms-auto" onclick="Setup.add('grades')">
                                <i class="fas fa-plus"></i> Add Pay Grade
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table id="table-grades" class="table table-hover align-middle w-100 acc-table"
                                data-page-length="25" data-order="[]" data-auto-width="false">
                                <thead>
                                    <tr>
                                        <th data-orderable="false" width="5%">SL.</th>
                                        <th>Pay Grade</th>
                                        <th class="text-center">Steps</th>
                                        <th class="text-end">Gross Range</th>
                                        <th>Status</th>
                                        <th data-orderable="false" width="8%">Action</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Steps ===== --}}
            <div class="tab-pane fade" id="pane-steps">
                <div class="card acc-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                            <div style="min-width: 220px;">
                                <select id="stepGradeFilter" class="form-select">
                                    <option value="">All pay grades</option>
                                    @foreach ($grades as $grade)
                                        <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <span class="setup-hint">Changing a step's salary also updates every Scale employee on that step.</span>
                            <button type="button" class="btn btn-primary ms-auto" onclick="Setup.add('steps')">
                                <i class="fas fa-plus"></i> Add Step
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table id="table-steps" class="table table-hover align-middle w-100 acc-table"
                                data-page-length="50" data-order="[]" data-auto-width="false">
                                <thead>
                                    <tr>
                                        <th data-orderable="false" width="4%">SL.</th>
                                        <th>Pay Grade</th>
                                        <th>Step</th>
                                        <th class="text-end">Basic</th>
                                        <th class="text-end">House Rent</th>
                                        <th class="text-end">Medical</th>
                                        <th class="text-end">Transport</th>
                                        <th class="text-end">Other</th>
                                        <th class="text-end">Gross</th>
                                        <th class="text-center">Employees</th>
                                        <th>Status</th>
                                        <th data-orderable="false" width="6%">Action</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= Modal (one form, fields shown per type) ================= --}}
    <div class="modal fade" id="setupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" id="setupDialog">
            <div class="modal-content">
                <form id="setupForm" autocomplete="off">
                    <input type="hidden" name="id" id="setup_id">

                    <div class="modal-header">
                        <h5 class="modal-title" id="setupTitle">Add</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            {{-- Step only --}}
                            <div class="col-md-8 f-steps">
                                <label class="form-label" for="pay_grade_id">Pay Grade <span class="text-danger">*</span></label>
                                <select name="pay_grade_id" id="pay_grade_id" class="form-select"></select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-4 f-steps">
                                <label class="form-label" for="step_no">Step No. <span class="text-danger">*</span></label>
                                <input type="number" name="step_no" id="step_no" class="form-control" min="1" max="999" step="1" placeholder="1">
                                <div class="text-danger small field-error"></div>
                            </div>
                            @foreach (['basic_salary' => 'Basic Salary', 'house_rent' => 'House Rent', 'medical_allowance' => 'Medical', 'transport_allowance' => 'Transport', 'other_allowance' => 'Other Allowance'] as $field => $label)
                                <div class="col-md-4 f-steps">
                                    <label class="form-label" for="{{ $field }}">{{ $label }} @if ($field === 'basic_salary')<span class="text-danger">*</span>@endif</label>
                                    <input type="number" step="0.01" min="0" name="{{ $field }}" id="{{ $field }}" class="form-control text-end step-part" placeholder="0.00">
                                    <div class="text-danger small field-error"></div>
                                </div>
                            @endforeach
                            <div class="col-md-4 f-steps">
                                <label class="form-label">Gross Salary</label>
                                <div class="form-control text-end step-gross bg-light" id="step_gross">0.00</div>
                            </div>

                            {{-- Name (all except steps) --}}
                            <div class="col-12 f-departments f-designations f-grades" id="nameWrap">
                                <label class="form-label" for="setup_name"><span id="nameLabel">Name</span> <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="setup_name" class="form-control" maxlength="100">
                                <div class="text-danger small field-error"></div>
                            </div>

                            <div class="col-md-6 f-departments">
                                <label class="form-label" for="setup_code">Code</label>
                                <input type="text" name="code" id="setup_code" class="form-control" maxlength="20" placeholder="e.g. SALES">
                                <div class="text-danger small field-error"></div>
                            </div>

                            <div class="col-12 f-designations">
                                <label class="form-label" for="department_id">Department</label>
                                <select name="department_id" id="department_id" class="form-select"></select>
                                <div class="text-danger small field-error"></div>
                            </div>

                            <div class="col-md-6 f-departments f-designations f-grades f-steps">
                                <label class="form-label" for="setup_status">Status</label>
                                <select name="status" id="setup_status" class="form-select">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                                <div class="text-danger small field-error"></div>
                            </div>

                            <div class="col-12 f-departments f-designations f-grades">
                                <label class="form-label" for="setup_description">Description</label>
                                <textarea name="description" id="setup_description" class="form-control" rows="2" maxlength="500"></textarea>
                                <div class="text-danger small field-error"></div>
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
        const Setup = {
            labels: { departments: 'Department', designations: 'Designation', grades: 'Pay Grade', steps: 'Step' },
            options: {
                departments: @json($departments),
                grades: @json($grades)
            },
            tables: {},
            type: 'departments',
            modal: null,

            url: function(type, action, id) {
                return "{{ url('admin/payroll-setup') }}/" + type + '/' + action + (id ? '/' + id : '');
            },

            initTable: function(type) {
                if (Setup.tables[type]) {
                    Setup.tables[type].columns.adjust();
                    return;
                }

                const $table = $('#table-' + type);
                $table.on('xhr.dt', function(e, settings, json) {
                    $('#count-' + type).text(json && json.data ? json.data.length : 0);
                });
                Voucher.keepSerial($table);

                Setup.tables[type] = $table.DataTable({
                    ajax: {
                        url: Setup.url(type, 'list'),
                        data: function(d) {
                            if (type === 'steps') d.grade_id = $('#stepGradeFilter').val();
                        }
                    },
                    processing: true
                });
            },

            reloadAll: function() {
                Object.keys(Setup.tables).forEach(function(type) {
                    Setup.tables[type].ajax.reload(null, false);
                });
            },

            /* ---------- dropdown options ---------- */
            fillSelect: function($select, rows, placeholder, selected) {
                $select.empty().append(new Option(placeholder, ''));
                rows.forEach(function(r) { $select.append(new Option(r.name, r.id)); });
                $select.val(selected ? String(selected) : '');
                Voucher.refresh($select);
            },

            reloadOptions: function(type) {
                $.get(Setup.url(type, 'options'), function(res) {
                    if (!res.status) return;
                    Setup.options[type] = res.data;

                    if (type === 'grades') {
                        const current = $('#stepGradeFilter').val();
                        Setup.fillSelect($('#stepGradeFilter'), res.data, 'All pay grades', current);
                    }
                });
            },

            /* ---------- modal ---------- */
            prepare: function(type, title) {
                Setup.type = type;
                const $form = $('#setupForm');
                $form[0].reset();
                Voucher.clearErrors($form);
                $('#setup_error').text('');
                $('#setup_id').val('');
                $('#setup_status').val('Active');

                // Show only this type's fields; hidden inputs are disabled so they are not posted.
                $form.find('[class*="f-"]').filter(function() {
                    return /\bf-(departments|designations|grades|steps)\b/.test(this.className);
                }).each(function() {
                    const show = $(this).hasClass('f-' + type);
                    $(this).toggleClass('d-none', !show).find('input, select, textarea').prop('disabled', !show);
                });

                $('#setupDialog').toggleClass('modal-lg', type === 'steps');
                $('#nameLabel').text(Setup.labels[type] + ' Name');
                $('#setupTitle').text(title);

                Setup.fillSelect($('#department_id'), Setup.options.departments, 'All departments');
                Setup.fillSelect($('#pay_grade_id'), Setup.options.grades, 'Select pay grade',
                    type === 'steps' ? $('#stepGradeFilter').val() : '');
                Setup.calcGross();
            },

            add: function(type) {
                Setup.prepare(type, 'Add ' + Setup.labels[type]);
                $('#setupSaveBtn').text('Save');

                // Suggest the next step number for the chosen grade
                if (type === 'steps') Setup.suggestStepNo();

                Setup.modal.show();
            },

            edit: function(type, id) {
                $.get(Setup.url(type, 'edit', id), function(res) {
                    if (!res.status) {
                        toastr.error(res.message);
                        return;
                    }
                    const d = res.data;
                    Setup.prepare(type, 'Edit ' + Setup.labels[type]);
                    $('#setupSaveBtn').text('Update');
                    $('#setup_id').val(d.id);
                    $('#setup_status').val(d.status);
                    $('#setup_name').val(d.name || '');
                    $('#setup_code').val(d.code || '');
                    $('#setup_description').val(d.description || '');

                    if (type === 'designations') {
                        Voucher.setValue($('#department_id'), d.department_id, 'Department #' + d.department_id);
                    }
                    if (type === 'steps') {
                        Voucher.setValue($('#pay_grade_id'), d.pay_grade_id, 'Grade #' + d.pay_grade_id);
                        $('#step_no').val(d.step_no);
                        ['basic_salary', 'house_rent', 'medical_allowance', 'transport_allowance', 'other_allowance'].forEach(function(f) {
                            $('#' + f).val(parseFloat(d[f]) > 0 ? d[f] : '');
                        });
                        Setup.calcGross();
                    }

                    Setup.modal.show();
                }).fail(function(xhr) { Voucher.ajaxError(xhr, 'Unable to load.'); });
            },

            calcGross: function() {
                let gross = 0;
                $('.step-part').each(function() { gross += parseFloat(this.value) || 0; });
                $('#step_gross').text(Voucher.money(gross));
            },

            suggestStepNo: function() {
                const gradeId = $('#pay_grade_id').val();
                if (!gradeId || $('#setup_id').val()) return;

                $.get("{{ route('admin.employee.steps') }}", { grade_id: gradeId, all: 1 }, function(res) {
                    const max = (res.data || []).reduce((m, s) => Math.max(m, parseInt(s.step_no) || 0), 0);
                    $('#step_no').val(max + 1);
                });
            },

            save: function() {
                const $form = $('#setupForm');
                const $btn = $('#setupSaveBtn');
                const label = $btn.text();
                $btn.prop('disabled', true).text('Saving...');

                $.ajax({
                    url: Setup.url(Setup.type, 'save'),
                    type: 'POST',
                    data: $form.serialize(),
                    success: function(res) {
                        if (!res.status) {
                            toastr.error(res.message);
                            return;
                        }
                        toastr.success(res.message);
                        Setup.modal.hide();
                        Setup.reloadAll();
                        if (Setup.type === 'departments' || Setup.type === 'grades') {
                            Setup.reloadOptions(Setup.type);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($form, xhr.responseJSON.errors, $('#setup_error'));
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save.');
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(label);
                    }
                });
            },

            post: function(type, action, id, onDone) {
                $.post(Setup.url(type, action, id), function(res) {
                    if (res.status) {
                        toastr.success(res.message);
                        Setup.reloadAll();
                        if (type === 'departments' || type === 'grades') Setup.reloadOptions(type);
                        if (onDone) onDone();
                    } else {
                        toastr.error(res.message);
                    }
                }).fail(function(xhr) { Voucher.ajaxError(xhr, 'Something went wrong.'); });
            },

            toggleStatus: function(type, id) {
                Setup.post(type, 'status', id);
            },

            remove: function(type, id) {
                Swal.fire({
                    title: 'Delete ' + Setup.labels[type].toLowerCase() + '?',
                    text: 'This cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Yes, delete',
                    reverseButtons: true
                }).then(function(result) {
                    if (result.isConfirmed) Setup.post(type, 'delete', id);
                });
            }
        };

        $(document).ready(function() {
            Setup.modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('setupModal'));

            Voucher.initSelect2($('#stepGradeFilter'));
            Voucher.initSelect2($('#department_id, #pay_grade_id'), $('#setupModal'));

            // Load every tab once so the counts on the tabs are filled in
            ['departments', 'designations', 'grades', 'steps'].forEach(Setup.initTable);

            $('.setup-tabs [data-bs-toggle="tab"]').on('shown.bs.tab', function() {
                Setup.initTable($(this).data('type'));
            });

            $('#stepGradeFilter').on('change', function() {
                Setup.tables.steps.ajax.reload();
            });

            $('#pay_grade_id').on('change', Setup.suggestStepNo);
            $('.step-part').on('input', Setup.calcGross);

            $('#setupForm').on('submit', function(e) {
                e.preventDefault();
                Setup.save();
            });
        });
    </script>
@endpush
