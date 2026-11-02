@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Payroll Setup
@endsection

@push('styles')
    <style>
        /* Tabs (.setup-tabs) come from components.css. */
        .setup-hint { font-size: var(--text-caption); color: var(--color-text-muted); }
        .step-gross { font-size: var(--text-heading-sm); font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); }
        .structure-card { display: flex; align-items: center; gap: var(--space-md); padding: var(--space-lg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface); cursor: pointer; transition: border-color .15s ease, background-color .15s ease; margin: 0; }
        .structure-card:hover { border-color: var(--color-accent); }
        .structure-card i { width: var(--control-height); height: var(--control-height); flex: 0 0 auto; border-radius: var(--radius-md); display: inline-flex; align-items: center; justify-content: center; background: var(--color-surface-soft); color: var(--color-text-muted); font-size: var(--text-body-lg); }
        .btn-check:checked + .structure-card { border-color: var(--color-accent); background: var(--color-accent-soft); }
        .btn-check:checked + .structure-card i { background: var(--color-button); color: var(--color-button-text); }
        .btn-check:focus-visible + .structure-card { box-shadow: var(--shadow-focus); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex align-items-center mb-3">
            <div>
                <h4 class="mb-0">Payroll Setup</h4>
                <small class="text-muted">Salary structure, departments, designations and the pay scale (grades &amp; steps) used by employees.</small>
            </div>
            <span class="badge bg-primary-subtle text-primary-emphasis ms-auto me-2 p-2">
                Salary structure: {{ $settings->salary_structure }}
            </span>
            <a href="{{ route('admin.employee.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-users"></i> Employees
            </a>
        </div>

        @php
            $isScale = $settings->salary_structure === 'Scale';
            $tabs = ['departments' => ['Departments', 'fas fa-sitemap'], 'designations' => ['Designations', 'fas fa-id-badge']];
            // Grades and steps are only used on the Scale structure.
            if ($isScale) {
                $tabs += ['grades' => ['Pay Grades', 'fas fa-layer-group'], 'steps' => ['Steps', 'fas fa-stream']];
            }
        @endphp

        <ul class="nav setup-tabs mb-3" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#pane-settings">
                    <span class="tab-icon"><i class="fas fa-sliders-h"></i></span>
                    Payroll Settings
                </button>
            </li>
            @foreach ($tabs as $key => [$label, $icon])
                <li class="nav-item">
                    <button class="nav-link" type="button" data-bs-toggle="tab"
                        data-bs-target="#pane-{{ $key }}" data-type="{{ $key }}">
                        <span class="tab-icon"><i class="{{ $icon }}"></i></span>
                        {{ $label }}
                        <span class="tab-count" id="count-{{ $key }}">0</span>
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">
            {{-- ===== Payroll settings ===== --}}
            <div class="tab-pane fade show active" id="pane-settings">
                <div class="card acc-card">
                    <div class="card-body">
                        <form id="settingsForm" autocomplete="off">
                            <div class="row g-3">
                                <div class="col-12">
                                    <h6 class="mb-1">Salary Structure</h6>
                                    <span class="setup-hint">Applies to every employee. Changing it recalculates all salaries.</span>
                                </div>
                                @foreach ([
                                    'Scale' => ['fas fa-layer-group', 'Pay grade & step (department wise). House rent, compensation and medical are a % of basic; basic rises one step a year.'],
                                    'Contractual' => ['fas fa-file-signature', 'Each employee gets only the contracted salary. No grades, steps, house rent, compensation or medical.'],
                                ] as $structure => [$icon, $hint])
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="salary_structure" value="{{ $structure }}"
                                            id="structure_{{ $structure }}" autocomplete="off" @checked($settings->salary_structure === $structure)>
                                        <label class="structure-card w-100 h-100" for="structure_{{ $structure }}">
                                            <i class="{{ $icon }}"></i>
                                            <span>
                                                <strong>{{ $structure }} Salary</strong>
                                                <small class="d-block text-muted">{{ $hint }}</small>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach

                                <div class="col-12 scale-only"><h6 class="mb-0 mt-2">Allowances (% of basic salary)</h6></div>
                                @foreach (['house_rent_percent' => 'House Rent', 'compensation_percent' => 'Compensation', 'medical_percent' => 'Medical'] as $field => $label)
                                    <div class="col-md-4 scale-only">
                                        <label class="form-label" for="{{ $field }}">{{ $label }} <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" name="{{ $field }}" id="{{ $field }}" class="form-control text-end"
                                                value="{{ (float) $settings->$field }}">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                @endforeach

                                <div class="col-12"><h6 class="mb-0 mt-2">Provident Fund &amp; Bonus</h6></div>
                                <div class="col-md-4">
                                    <label class="form-label" for="pf_enabled">Deduct PF</label>
                                    <select name="pf_enabled" id="pf_enabled" class="form-select">
                                        <option value="No" @selected($settings->pf_enabled === 'No')>No</option>
                                        <option value="Yes" @selected($settings->pf_enabled === 'Yes')>Yes</option>
                                    </select>
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-4 pf-only">
                                    <label class="form-label" for="pf_percent">PF Deduction <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" max="100" name="pf_percent" id="pf_percent" class="form-control text-end"
                                            value="{{ (float) $settings->pf_percent }}">
                                        <span class="input-group-text" id="pfBaseLabel">% of basic</span>
                                    </div>
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="bonus_percent">Bonus <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" name="bonus_percent" id="bonus_percent" class="form-control text-end"
                                            value="{{ (float) $settings->bonus_percent }}">
                                        <span class="input-group-text">% of gross</span>
                                    </div>
                                    <small class="text-muted">Only for employees marked bonus eligible.</small>
                                    <div class="text-danger small field-error"></div>
                                </div>

                                <div class="col-12">
                                    <div class="border rounded-3 p-3 bg-light small" id="settingsExample"></div>
                                </div>
                                @if ($isScale && $withoutStep > 0)
                                    <div class="col-12">
                                        <div class="alert alert-warning mb-0 py-2">
                                            {{ $withoutStep }} active employee(s) have no pay step yet, so their salary is 0. Set their grade &amp; step on the Employees page.
                                        </div>
                                    </div>
                                @endif
                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary" id="settingsSaveBtn"><i class="fas fa-save"></i> Save Settings</button>
                                    @if ($isScale)
                                        <button type="button" class="btn btn-outline-primary ms-auto" id="incrementBtn"
                                            title="Moves each active employee one step up once a year after joining / the last increment">
                                            <i class="fas fa-level-up-alt"></i> Run Annual Increment
                                        </button>
                                    @endif
                                </div>
                                <div class="col-12 text-danger small" id="settings_error"></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ===== Departments ===== --}}
            <div class="tab-pane fade" id="pane-departments">
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

            @if ($isScale)
            {{-- ===== Pay grades ===== --}}
            <div class="tab-pane fade" id="pane-grades">
                <div class="card acc-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="setup-hint">A pay grade (scale) holds the salary steps. Its yearly increment % suggests each next step's basic.</span>
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
                                        <th class="text-end">Yearly Increment</th>
                                        <th class="text-center">Steps</th>
                                        <th class="text-end">Basic Range</th>
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
                            <div style="min-width: 220px;">
                                <select id="stepDepartmentFilter" class="form-select">
                                    <option value="">All departments</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <span class="setup-hint">A step holds the basic; allowances follow the payroll settings. Changing it updates every employee on that step.</span>
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
                                        <th>Department</th>
                                        <th>Step</th>
                                        <th class="text-end">Basic</th>
                                        <th class="text-end">House Rent</th>
                                        <th class="text-end">Compensation</th>
                                        <th class="text-end">Medical</th>
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
            @endif
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
                            <div class="col-md-5 f-steps">
                                <label class="form-label" for="pay_grade_id">Pay Grade <span class="text-danger">*</span></label>
                                <select name="pay_grade_id" id="pay_grade_id" class="form-select"></select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-3 f-steps">
                                <label class="form-label" for="step_no">Step No. <span class="text-danger">*</span></label>
                                <input type="number" name="step_no" id="step_no" class="form-control" min="1" max="999" step="1" placeholder="1">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-4 f-steps">
                                <label class="form-label" for="basic_salary">Basic Salary <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="basic_salary" id="basic_salary" class="form-control text-end" placeholder="0.00">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-12 f-steps">
                                <div class="row g-2 small text-muted" id="step_preview"></div>
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

                            <div class="col-md-6 f-grades">
                                <label class="form-label" for="increment_percent">Yearly Increment</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" max="100" name="increment_percent" id="increment_percent" class="form-control text-end" placeholder="0.00">
                                    <span class="input-group-text">% of basic</span>
                                </div>
                                <div class="text-danger small field-error"></div>
                            </div>

                            <div class="col-12 f-designations f-steps">
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
            // Saved payroll settings; the step preview uses them.
            pay: @json($settings),
            types: @json(array_keys($tabs)),
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
                            if (type === 'steps') {
                                d.grade_id = $('#stepGradeFilter').val();
                                d.department_id = $('#stepDepartmentFilter').val();
                            }
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
                $.ajax({
                    url: Setup.url(type, 'options'),
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (!response.status) return;
                        Setup.options[type] = response.data;

                        if (type === 'grades') {
                            const current = $('#stepGradeFilter').val();
                            Setup.fillSelect($('#stepGradeFilter'), response.data, 'All pay grades', current);
                        }
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
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

                Setup.fillSelect($('#department_id'), Setup.options.departments, 'All departments',
                    type === 'steps' ? $('#stepDepartmentFilter').val() : '');
                Setup.fillSelect($('#pay_grade_id'), Setup.options.grades, 'Select pay grade',
                    type === 'steps' ? $('#stepGradeFilter').val() : '');
                Setup.preview();
            },

            add: function(type) {
                Setup.prepare(type, 'Add ' + Setup.labels[type]);
                $('#setupSaveBtn').text('Save');

                // Suggest the next step number for the chosen grade
                if (type === 'steps') Setup.suggestStepNo();

                Setup.modal.show();
            },

            edit: function(type, id) {
                $.ajax({
                    url: Setup.url(type, 'edit', id),
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (!response.status) {
                            toastr.error(response.message);
                            return;
                        }
                        const d = response.data;
                        Setup.prepare(type, 'Edit ' + Setup.labels[type]);
                        $('#setupSaveBtn').text('Update');
                        $('#setup_id').val(d.id);
                        $('#setup_status').val(d.status);
                        $('#setup_name').val(d.name || '');
                        $('#setup_code').val(d.code || '');
                        $('#setup_description').val(d.description || '');

                        if (type === 'designations' || type === 'steps') {
                            if (d.department_id) {
                                Voucher.setValue($('#department_id'), d.department_id, 'Department #' + d.department_id);
                            } else {
                                Voucher.refresh($('#department_id').val(''));
                            }
                        }
                        if (type === 'grades') {
                            $('#increment_percent').val(parseFloat(d.increment_percent) > 0 ? d.increment_percent : '');
                        }
                        if (type === 'steps') {
                            Voucher.setValue($('#pay_grade_id'), d.pay_grade_id, 'Grade #' + d.pay_grade_id);
                            $('#step_no').val(d.step_no);
                            $('#basic_salary').val(d.basic_salary);
                            Setup.preview();
                        }

                        Setup.modal.show();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load.');
                    }
                });
            },

            // Same rule as PayrollSalary::breakdown(), for a basic under the given settings.
            breakdown: function(basic, s) {
                const pct = function(v, p) { return Math.round(v * (parseFloat(p) || 0)) / 100; };
                const scale = s.salary_structure === 'Scale';
                const r = { basic: basic, house_rent: 0, compensation: 0, medical: 0 };
                if (scale) {
                    r.house_rent = pct(basic, s.house_rent_percent);
                    r.compensation = pct(basic, s.compensation_percent);
                    r.medical = pct(basic, s.medical_percent);
                }
                r.gross = r.basic + r.house_rent + r.compensation + r.medical;
                r.pf = s.pf_enabled === 'Yes' ? pct(basic, s.pf_percent) : 0;
                r.bonus = pct(r.gross, s.bonus_percent);
                r.net = r.gross - r.pf;
                return r;
            },

            preview: function() {
                const r = Setup.breakdown(parseFloat($('#basic_salary').val()) || 0, Setup.pay);
                const item = function(label, value, bold) {
                    return '<div class="col"><div>' + label + '</div><div class="text-end ' + (bold ? 'fw-bold text-dark step-gross' : 'text-dark') + '">' + Voucher.money(value) + '</div></div>';
                };
                $('#step_preview').html(
                    item('House Rent (' + (+Setup.pay.house_rent_percent) + '%)', r.house_rent) +
                    item('Compensation (' + (+Setup.pay.compensation_percent) + '%)', r.compensation) +
                    item('Medical (' + (+Setup.pay.medical_percent) + '%)', r.medical) +
                    item('Gross', r.gross, true));
            },

            // Next step no. in the grade + department; the basic grows by the grade's yearly increment.
            suggestStepNo: function() {
                const gradeId = $('#pay_grade_id').val();
                if (Setup.type !== 'steps' || !gradeId || $('#setup_id').val()) return;

                $.ajax({
                    url: "{{ route('admin.employee.steps') }}",
                    type: 'GET',
                    dataType: 'json',
                    data: { grade_id: gradeId, department_id: $('#department_id').val(), all: 1, exact: 1 },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const steps = response.data || [];
                        const last = steps.reduce(function(m, s) { return !m || +s.step_no > +m.step_no ? s : m; }, null);
                        const grade = Setup.options.grades.find(function(g) { return String(g.id) === String(gradeId); });

                        $('#step_no').val(last ? +last.step_no + 1 : 1);
                        if (last) {
                            const inc = parseFloat(grade ? grade.increment_percent : 0) || 0;
                            $('#basic_salary').val((Math.round(parseFloat(last.basic_salary) * (100 + inc)) / 100).toFixed(2));
                        }
                        Setup.preview();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                    }
                });
            },

            saveSettings: function() {
                const $form = $('#settingsForm');
                const $btn = $('#settingsSaveBtn').prop('disabled', true);
                Voucher.clearErrors($form);
                $('#settings_error').text('');

                $.ajax({
                    url: "{{ route('admin.payroll-setup.settings') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $form.serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (!response.status) {
                            toastr.error(response.message);
                            return;
                        }
                        toastr.success(response.message);
                        // The structure decides which tabs exist, so reload.
                        setTimeout(function() { location.reload(); }, 800);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($form, xhr.responseJSON.errors, $('#settings_error'));
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save the settings.');
                        }
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            },

            // Show only what the chosen structure uses, with a worked example.
            applySettingsForm: function() {
                const s = {};
                $('#settingsForm').serializeArray().forEach(function(f) { s[f.name] = f.value; });
                const scale = s.salary_structure === 'Scale';

                $('#settingsForm .scale-only').toggleClass('d-none', !scale);
                $('#settingsForm .pf-only').toggleClass('d-none', s.pf_enabled !== 'Yes');
                $('#pfBaseLabel').text(scale ? '% of basic' : '% of salary');

                const base = 20000;
                const r = Setup.breakdown(base, s);
                const parts = ['<b>Example</b> — ' + (scale ? 'basic' : 'contract salary') + ' ' + Voucher.money(base)];
                if (scale) {
                    parts.push('house rent ' + Voucher.money(r.house_rent), 'compensation ' + Voucher.money(r.compensation), 'medical ' + Voucher.money(r.medical));
                }
                parts.push('<b>gross ' + Voucher.money(r.gross) + '</b>');
                if (r.pf > 0) parts.push('PF −' + Voucher.money(r.pf));
                parts.push('<b>net ' + Voucher.money(r.net) + '</b>');
                if (r.bonus > 0) parts.push('bonus (if eligible) ' + Voucher.money(r.bonus));
                $('#settingsExample').html(parts.join(' · '));
            },

            runIncrement: function() {
                Swal.fire({
                    title: 'Run annual increment?',
                    text: 'Each active employee whose joining date (or last increment) is a year or more ago moves up one step.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, run it',
                    reverseButtons: true
                }).then(function(result) {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: "{{ route('admin.payroll-setup.increment') }}",
                        type: 'POST',
                        dataType: 'json',
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            response.status ? toastr.success(response.message) : toastr.error(response.message);
                            Setup.reloadAll();
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            Voucher.ajaxError(xhr, 'Unable to run the increment.');
                        }
                    });
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
                    dataType: 'json',
                    data: $form.serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (!response.status) {
                            toastr.error(response.message);
                            return;
                        }
                        toastr.success(response.message);
                        Setup.modal.hide();
                        Setup.reloadAll();
                        if (Setup.type === 'departments' || Setup.type === 'grades') {
                            Setup.reloadOptions(Setup.type);
                        }
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
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
                $.ajax({
                    url: Setup.url(type, action, id),
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (response.status) {
                            toastr.success(response.message);
                            Setup.reloadAll();
                            if (type === 'departments' || type === 'grades') Setup.reloadOptions(type);
                            if (onDone) onDone();
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Something went wrong.');
                    }
                });
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
                    confirmButtonColor: 'var(--color-danger)',
                    confirmButtonText: 'Yes, delete',
                    reverseButtons: true
                }).then(function(result) {
                    if (result.isConfirmed) Setup.post(type, 'delete', id);
                });
            }
        };

        $(document).ready(function() {
            Setup.modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('setupModal'));

            Voucher.initSelect2($('#stepGradeFilter, #stepDepartmentFilter'));
            Voucher.initSelect2($('#department_id, #pay_grade_id'), $('#setupModal'));

            // Load every tab once so the counts on the tabs are filled in
            Setup.types.forEach(Setup.initTable);

            $('.setup-tabs [data-bs-toggle="tab"]').on('shown.bs.tab', function() {
                if ($(this).data('type')) Setup.initTable($(this).data('type'));
            });

            $('#stepGradeFilter, #stepDepartmentFilter').on('change', function() {
                Setup.tables.steps.ajax.reload();
            });

            $('#pay_grade_id, #department_id').on('change', Setup.suggestStepNo);
            $('#basic_salary').on('input', Setup.preview);

            $('#settingsForm').on('input change', Setup.applySettingsForm).on('submit', function(e) {
                e.preventDefault();
                Setup.saveSettings();
            });
            $('#incrementBtn').on('click', Setup.runIncrement);
            Setup.applySettingsForm();

            $('#setupForm').on('submit', function(e) {
                e.preventDefault();
                Setup.save();
            });
        });
    </script>
@endpush
