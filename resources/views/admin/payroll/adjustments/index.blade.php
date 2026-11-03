@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Bonus & Adjustments
@endsection

@push('styles')
    <style>
        .adj-month { max-width: 180px; }
        .adj-table td, .adj-table th { min-width: 0; padding: .45rem 10px; font-size: var(--text-caption); vertical-align: middle; }
        .adj-pill {
            display: inline-block;
            padding: 0 var(--space-sm);
            border-radius: var(--radius-pill);
            font-size: var(--text-micro);
            font-weight: var(--font-weight-medium);
        }
        .adj-pill.add { background: var(--color-success-soft); color: var(--color-success); }
        .adj-pill.ded { background: var(--color-danger-soft); color: var(--color-danger); }
        .adj-pill.lock { background: var(--color-surface-soft); color: var(--color-text-muted); }
        .adj-hint { font-size: var(--text-caption); color: var(--color-text-muted); }
        .adj-totals { display: flex; flex-wrap: wrap; gap: var(--space-lg); font-size: var(--text-caption); }
        .adj-totals b { font-feature-settings: var(--font-feature-numeric); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Bonus &amp; Adjustments</h4>
                <small class="text-muted">Everything here is added to or taken from the salary sheet of the chosen month. A month already processed for an employee is frozen.</small>
            </div>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <label class="form-label mb-0 small text-muted" for="month">Month</label>
                <input type="month" id="month" class="form-control adj-month" value="{{ now()->format('Y-m') }}">
                <a href="{{ route('admin.reports.salary-sheet') }}" class="btn btn-outline-secondary"><i class="fas fa-table"></i> Salary Sheet</a>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabBonus" type="button" role="tab">Monthly Bonuses</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAdj" type="button" role="tab">Additions &amp; Deductions</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTypes" type="button" role="tab">Bonus Types</button></li>
        </ul>

        <div class="tab-content">
            {{-- Monthly bonuses --}}
            <div class="tab-pane fade show active" id="tabBonus" role="tabpanel">
                <div class="row g-3">
                    <div class="col-xl-4">
                        <div class="card acc-card">
                            <div class="card-body">
                                <h6 class="mb-3" id="bonusFormTitle">Grant a bonus</h6>
                                <form id="bonusForm" autocomplete="off" novalidate>
                                    <input type="hidden" name="id" id="bonus_id">
                                    <div class="mb-3">
                                        <label class="form-label" for="bonus_type_id">Bonus Type <span class="text-danger">*</span></label>
                                        <select name="bonus_type_id" id="bonus_type_id" class="form-select">
                                            <option value="">Select bonus</option>
                                            @foreach ($bonusTypes->where('status', 'Active') as $t)
                                                <option value="{{ $t->id }}" data-calc="{{ $t->calc_type }}" data-value="{{ (float) $t->value }}" data-basis="{{ $t->basis }}">{{ $t->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="adj-hint mt-1" id="typeRule"></div>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="scope">Give To <span class="text-danger">*</span></label>
                                        <select name="scope" id="scope" class="form-select">
                                            <option value="All">All employees</option>
                                            <option value="Department">A department</option>
                                            @if ($isScale)
                                                <option value="Grade">A pay grade</option>
                                                <option value="Step">A pay step</option>
                                            @endif
                                            <option value="Employee">One employee</option>
                                        </select>
                                    </div>
                                    <div class="mb-3 scope-box d-none" data-scope="Department">
                                        <label class="form-label" for="scope_department">Department <span class="text-danger">*</span></label>
                                        <select id="scope_department" class="form-select scope-input">
                                            <option value="">Select department</option>
                                            @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3 scope-box d-none" data-scope="Grade">
                                        <label class="form-label" for="scope_grade">Pay Grade <span class="text-danger">*</span></label>
                                        <select id="scope_grade" class="form-select scope-input">
                                            <option value="">Select grade</option>
                                            @foreach ($grades as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3 scope-box d-none" data-scope="Step">
                                        <label class="form-label" for="scope_step">Pay Step <span class="text-danger">*</span></label>
                                        <select id="scope_step" class="form-select scope-input">
                                            <option value="">Select step</option>
                                            @foreach ($steps as $s)<option value="{{ $s->id }}">{{ $s->grade }} / Step {{ $s->step_no }}{{ $s->department ? ' · ' . $s->department : '' }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3 scope-box d-none" data-scope="Employee">
                                        <label class="form-label" for="scope_employee">Employee <span class="text-danger">*</span></label>
                                        <select id="scope_employee" class="form-select scope-input">
                                            <option value="">Select employee</option>
                                            @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->name }} ({{ $e->employee_code }})</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="override">
                                        <label class="form-check-label" for="override">Use a different rate for this grant</label>
                                    </div>
                                    <div class="row g-2 mb-3 d-none" id="overrideBox">
                                        <div class="col-6">
                                            <select name="calc_type" id="calc_type" class="form-select" aria-label="Calculation" disabled>
                                                <option value="Percentage">% of basis</option>
                                                <option value="Amount">Fixed amount</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" name="value" id="value" class="form-control" min="0" step="0.01" placeholder="Value" aria-label="Value" disabled>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="bonus_remarks">Remarks</label>
                                        <input type="text" name="remarks" id="bonus_remarks" class="form-control" maxlength="255" placeholder="e.g. Eid-ul-Fitr">
                                    </div>
                                    <div class="text-danger small mb-2" id="bonus_error"></div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-fill" id="bonusSubmit">Grant Bonus</button>
                                        <button type="button" class="btn btn-outline-secondary d-none" id="bonusCancel">Cancel edit</button>
                                    </div>
                                </form>
                                <p class="adj-hint mt-3 mb-0">If one bonus reaches an employee twice (e.g. All + their department), the more specific grant wins. Types marked “eligible only” skip employees who are not bonus eligible, unless granted to that employee directly.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <div class="card acc-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <h6 class="mb-0">Bonuses in <span class="month-label"></span></h6>
                                    <span class="ms-auto adj-totals" id="bonusTotals"></span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered adj-table mb-0">
                                        <thead><tr><th>Bonus</th><th>Given To</th><th>Rate</th><th class="text-center">Employees</th><th class="text-end">Amount</th><th>Remarks</th><th style="width: 90px;"></th></tr></thead>
                                        <tbody id="bonusRows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Additions & deductions --}}
            <div class="tab-pane fade" id="tabAdj" role="tabpanel">
                <div class="row g-3">
                    <div class="col-xl-4">
                        <div class="card acc-card">
                            <div class="card-body">
                                <h6 class="mb-3" id="adjFormTitle">Add an entry</h6>
                                <form id="adjForm" autocomplete="off" novalidate>
                                    <input type="hidden" name="id" id="adj_id">
                                    <div class="mb-3">
                                        <label class="form-label" for="adj_employee">Employee <span class="text-danger">*</span></label>
                                        <select name="employee_id" id="adj_employee" class="form-select">
                                            <option value="">Select employee</option>
                                            @foreach ($employees as $e)
                                                <option value="{{ $e->id }}" data-basic="{{ (float) $e->basic_salary }}" data-gross="{{ (float) $e->gross_salary }}">{{ $e->name }} ({{ $e->employee_code }}){{ $e->department ? ' · ' . $e->department : '' }}</option>
                                            @endforeach
                                        </select>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="btn-group w-100" role="group" aria-label="Type">
                                            <input type="radio" class="btn-check" name="adj_type" id="typeAdd" value="Addition" checked>
                                            <label class="btn btn-outline-success" for="typeAdd"><i class="fas fa-plus"></i> Addition</label>
                                            <input type="radio" class="btn-check" name="adj_type" id="typeDed" value="Deduction">
                                            <label class="btn btn-outline-danger" for="typeDed"><i class="fas fa-minus"></i> Deduction</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="head">Head <span class="text-danger">*</span></label>
                                        <input type="text" name="head" id="head" class="form-control" list="headList" maxlength="60" placeholder="Pick or type">
                                        <datalist id="headList"></datalist>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="mb-3 d-none" id="daysBox">
                                        <label class="form-label" for="days">Absent Days <span class="text-danger">*</span></label>
                                        <input type="number" name="days" id="days" class="form-control" min="0.5" max="31" step="0.5">
                                        <div class="adj-hint mt-1" id="daysHint"></div>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="amount">Amount <span class="text-danger amount-star">*</span></label>
                                        <input type="number" name="amount" id="amount" class="form-control" min="0" step="0.01">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="adj_remarks">Remarks</label>
                                        <input type="text" name="remarks" id="adj_remarks" class="form-control" maxlength="255">
                                    </div>
                                    <div class="text-danger small mb-2" id="adj_error"></div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-fill" id="adjSubmit">Save</button>
                                        <button type="button" class="btn btn-outline-secondary d-none" id="adjCancel">Cancel edit</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <div class="card acc-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <h6 class="mb-0">Entries in <span class="month-label"></span></h6>
                                    <span class="ms-auto adj-totals" id="adjTotals"></span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered adj-table mb-0">
                                        <thead><tr><th>Employee</th><th>Type</th><th>Head</th><th class="text-end">Amount</th><th>Remarks</th><th style="width: 90px;"></th></tr></thead>
                                        <tbody id="adjRows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Bonus types --}}
            <div class="tab-pane fade" id="tabTypes" role="tabpanel">
                <div class="row g-3">
                    <div class="col-xl-4">
                        <div class="card acc-card">
                            <div class="card-body">
                                <h6 class="mb-3" id="typeFormTitle">New bonus type</h6>
                                <form id="typeForm" autocomplete="off" novalidate>
                                    <input type="hidden" name="id" id="type_id">
                                    <div class="mb-3">
                                        <label class="form-label" for="type_name">Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" id="type_name" class="form-control" maxlength="100" placeholder="e.g. Festival Bonus">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label" for="type_calc">Calculation</label>
                                            <select name="calc_type" id="type_calc" class="form-select">
                                                <option value="Percentage">Percentage</option>
                                                <option value="Amount">Fixed amount</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label" for="type_value">Value <span class="text-danger">*</span></label>
                                            <input type="number" name="value" id="type_value" class="form-control" min="0" step="0.01">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3 basis-box">
                                        <label class="form-label" for="type_basis">Percentage Of</label>
                                        <select name="basis" id="type_basis" class="form-select">
                                            <option value="Basic">Basic salary</option>
                                            <option value="Gross">Gross salary</option>
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label" for="type_eligible">Who Gets It</label>
                                            <select name="eligible_only" id="type_eligible" class="form-select">
                                                <option value="Yes">Bonus-eligible only</option>
                                                <option value="No">Everyone granted</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label" for="type_status">Status</label>
                                            <select name="status" id="type_status" class="form-select">
                                                <option value="Active">Active</option>
                                                <option value="Inactive">Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="type_description">Description</label>
                                        <input type="text" name="description" id="type_description" class="form-control" maxlength="255">
                                    </div>
                                    <div class="text-danger small mb-2" id="type_error"></div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-fill" id="typeSubmit">Save Type</button>
                                        <button type="button" class="btn btn-outline-secondary d-none" id="typeCancel">Cancel edit</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <div class="card acc-card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered adj-table mb-0">
                                        <thead><tr><th>Bonus Type</th><th>Rate</th><th>Who Gets It</th><th>Status</th><th>Description</th><th style="width: 90px;"></th></tr></thead>
                                        <tbody id="typeRows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const R = {
            types: "{{ route('admin.payroll-adjustments.types') }}",
            typeSave: "{{ route('admin.payroll-adjustments.types.save') }}",
            typeDelete: "{{ route('admin.payroll-adjustments.types.delete', '__ID__') }}",
            bonuses: "{{ route('admin.payroll-adjustments.bonuses') }}",
            bonusSave: "{{ route('admin.payroll-adjustments.bonuses.save') }}",
            bonusDelete: "{{ route('admin.payroll-adjustments.bonuses.delete', '__ID__') }}",
            entries: "{{ route('admin.payroll-adjustments.entries') }}",
            entrySave: "{{ route('admin.payroll-adjustments.entries.save') }}",
            entryDelete: "{{ route('admin.payroll-adjustments.entries.delete', '__ID__') }}"
        };
        const HEADS = { Addition: @json($additionHeads), Deduction: @json($deductionHeads) };
        const ABSENCE_BASIS = @json($settings->absence_basis ?? 'Gross');
        const esc = Voucher.esc, money = Voucher.money;
        let bonusCache = [], adjCache = [], typeCache = [];

        function month() { return $('#month').val(); }
        function monthLabel() { return new Date(month() + '-01T00:00:00').toLocaleString('en', { month: 'long', year: 'numeric' }); }
        function rate(calc, value, basis) {
            return calc === 'Percentage' ? (parseFloat(value) || 0) + '% of ' + String(basis).toLowerCase() : money(value) + ' fixed';
        }
        function confirmDelete(text, url, done) {
            Swal.fire({ title: text, icon: 'warning', showCancelButton: true, confirmButtonText: 'Remove', reverseButtons: true })
                .then(function(r) {
                    if (!r.isConfirmed) return;
                    $.ajax({
                        url: url,
                        type: 'POST',
                        dataType: 'json',
                        data: {},
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            toastr.success(response.message);
                            done();
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            Voucher.ajaxError(xhr, 'Unable to remove.');
                        }
                    });
                });
        }
        function submitForm($form, url, $btn, $err, label, done) {
            $btn.prop('disabled', true);
            Voucher.clearErrors($form);
            $err.text('');
            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: $form.serialize() + '&month=' + month(),
                success: function(response) {
                    // alert(JSON.stringify(response));
                    toastr.success(response.message);
                    done();
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        Voucher.showErrors($form, xhr.responseJSON.errors, $err);
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        $err.text(xhr.responseJSON.message);
                    } else {
                        Voucher.ajaxError(xhr, 'Unable to save.');
                    }
                },
                complete: function() { $btn.prop('disabled', false).text(label); }
            });
        }

        /* ---------------- Bonuses ---------------- */
        function loadBonuses() {
            $.ajax({
                url: R.bonuses,
                type: 'GET',
                dataType: 'json',
                data: { month: month() },
                success: function(response) {
                    // alert(JSON.stringify(response));
                    bonusCache = response.data;
                    let total = 0;
                    $('#bonusRows').html(response.data.length ? response.data.map(function(b) {
                        total += parseFloat(b.total) || 0;
                        return '<tr><td class="fw-semibold">' + esc(b.name) + '</td><td>' + esc(b.who) + '</td><td>' + esc(b.rule) +
                            (b.calc_type ? ' <span class="adj-pill lock">override</span>' : '') + '</td>' +
                            '<td class="text-center">' + b.employees + '</td><td class="text-end">' + money(b.total) + '</td>' +
                            '<td>' + esc(b.remarks || '') + '</td>' +
                            '<td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary bonus-edit" data-id="' + b.id + '" aria-label="Edit"><i class="fas fa-pen"></i></button> ' +
                            '<button type="button" class="btn btn-sm btn-outline-danger bonus-del" data-id="' + b.id + '" aria-label="Remove"><i class="fas fa-trash"></i></button></td></tr>';
                    }).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No bonus granted in ' + monthLabel() + '.</td></tr>');
                    $('#bonusTotals').html(response.data.length ? 'Estimated total <b>' + money(total) + '</b>' : '');
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });
        }

        function scopeInput() { return $('.scope-box[data-scope="' + $('#scope').val() + '"] .scope-input'); }
        function showScope() {
            $('.scope-box').addClass('d-none');
            $('.scope-input').removeAttr('name');
            const $box = $('.scope-box[data-scope="' + $('#scope').val() + '"]').removeClass('d-none');
            $box.find('.scope-input').attr('name', 'scope_id');
        }
        function resetBonusForm() {
            $('#bonusForm')[0].reset();
            $('#bonus_id').val('');
            $('#bonus_type_id, .scope-input').val('').trigger('change.select2');
            $('#override').prop('checked', false).trigger('change');
            showScope();
            $('#typeRule').text('');
            $('#bonusFormTitle').text('Grant a bonus');
            $('#bonusSubmit').text('Grant Bonus');
            $('#bonusCancel').addClass('d-none');
            Voucher.clearErrors($('#bonusForm'));
            $('#bonus_error').text('');
        }

        /* ---------------- Additions & deductions ---------------- */
        function loadEntries() {
            $.ajax({
                url: R.entries,
                type: 'GET',
                dataType: 'json',
                data: { month: month() },
                success: function(response) {
                    // alert(JSON.stringify(response));
                    adjCache = response.data;
                    $('#adjRows').html(response.data.length ? response.data.map(function(a) {
                        const add = a.adj_type === 'Addition';
                        return '<tr><td><span class="fw-semibold">' + esc(a.name) + '</span><div class="text-muted small">' + esc(a.employee_code || '') + (a.department ? ' · ' + esc(a.department) : '') + '</div></td>' +
                            '<td><span class="adj-pill ' + (add ? 'add' : 'ded') + '">' + a.adj_type + '</span></td>' +
                            '<td>' + esc(a.head) + (a.days ? ' <span class="text-muted">(' + parseFloat(a.days) + ' d)</span>' : '') + '</td>' +
                            '<td class="text-end ' + (add ? '' : 'text-danger') + '">' + (add ? '' : '−') + money(a.amount) + '</td>' +
                            '<td>' + esc(a.remarks || '') + '</td>' +
                            '<td class="text-nowrap">' + (a.locked ? '<span class="adj-pill lock" title="Salary already processed">Processed</span>' :
                                '<button type="button" class="btn btn-sm btn-outline-secondary adj-edit" data-id="' + a.id + '" aria-label="Edit"><i class="fas fa-pen"></i></button> ' +
                                '<button type="button" class="btn btn-sm btn-outline-danger adj-del" data-id="' + a.id + '" aria-label="Remove"><i class="fas fa-trash"></i></button>') + '</td></tr>';
                    }).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No additions or deductions in ' + monthLabel() + '.</td></tr>');
                    $('#adjTotals').html('Additions <b class="text-success">' + money(response.totals.additions) + '</b> · Deductions <b class="text-danger">' + money(response.totals.deductions) + '</b>');
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });
        }

        function adjType() { return $('input[name="adj_type"]:checked').val(); }
        function refreshHeads() {
            $('#headList').html(HEADS[adjType()].map(function(h) { return '<option value="' + esc(h) + '">'; }).join(''));
            const absence = adjType() === 'Deduction' && $.trim($('#head').val()).toLowerCase() === 'absence';
            $('#daysBox').toggleClass('d-none', !absence);
            $('.amount-star').toggleClass('d-none', absence);
            $('#amount').attr('placeholder', absence ? 'Blank = days × daily rate' : '');
            absenceHint();
        }
        function absenceHint() {
            const $o = $('#adj_employee option:selected');
            if (!$o.val() || $('#daysBox').hasClass('d-none')) { $('#daysHint').text(''); return; }
            const m = new Date(month() + '-01T00:00:00');
            const daysInMonth = new Date(m.getFullYear(), m.getMonth() + 1, 0).getDate();
            const base = parseFloat(ABSENCE_BASIS === 'Basic' ? $o.data('basic') : $o.data('gross')) || 0;
            const daily = base / daysInMonth;
            const days = parseFloat($('#days').val()) || 0;
            $('#daysHint').text('Daily rate ' + money(daily) + ' (' + ABSENCE_BASIS.toLowerCase() + ' / ' + daysInMonth + ' days)' + (days ? ' → ' + money(daily * days) : ''));
        }
        function resetAdjForm() {
            $('#adjForm')[0].reset();
            $('#adj_id').val('');
            $('#adj_employee').val('').trigger('change.select2');
            $('#typeAdd').prop('checked', true);
            refreshHeads();
            $('#adjFormTitle').text('Add an entry');
            $('#adjCancel').addClass('d-none');
            Voucher.clearErrors($('#adjForm'));
            $('#adj_error').text('');
        }

        /* ---------------- Types ---------------- */
        function loadTypes() {
            $.ajax({
                url: R.types,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    // alert(JSON.stringify(response));
                    typeCache = response.data;
                    $('#typeRows').html(response.data.length ? response.data.map(function(t) {
                        return '<tr><td class="fw-semibold">' + esc(t.name) + '</td><td>' + rate(t.calc_type, t.value, t.basis) + '</td>' +
                            '<td>' + (t.eligible_only === 'Yes' ? 'Bonus-eligible only' : 'Everyone granted') + '</td>' +
                            '<td><span class="adj-pill ' + (t.status === 'Active' ? 'add' : 'lock') + '">' + t.status + '</span></td>' +
                            '<td>' + esc(t.description || '') + '</td>' +
                            '<td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary type-edit" data-id="' + t.id + '" aria-label="Edit"><i class="fas fa-pen"></i></button> ' +
                            '<button type="button" class="btn btn-sm btn-outline-danger type-del" data-id="' + t.id + '" aria-label="Remove"><i class="fas fa-trash"></i></button></td></tr>';
                    }).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No bonus types yet.</td></tr>');
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });
        }
        function resetTypeForm() {
            $('#typeForm')[0].reset();
            $('#type_id').val('');
            $('#typeFormTitle').text('New bonus type');
            $('#typeCancel').addClass('d-none');
            $('.basis-box').removeClass('d-none');
            Voucher.clearErrors($('#typeForm'));
            $('#type_error').text('');
        }

        function loadMonth() {
            $('.month-label').text(monthLabel());
            loadBonuses();
            loadEntries();
            absenceHint();
        }

        $(document).ready(function() {
            // Open the tab named in the URL hash (#tabBonus, #tabAdj, #tabTypes) and keep the hash in step with the open tab.
            (function() {
                const btn = location.hash ? document.querySelector('[data-bs-target="' + location.hash + '"]') : null;
                if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
                $('[data-bs-toggle="tab"][data-bs-target^="#tab"]').on('shown.bs.tab', function() {
                    history.replaceState(null, '', location.pathname + location.search + $(this).attr('data-bs-target'));
                });
            })();
            Voucher.initSelect2($('#bonus_type_id, .scope-input, #adj_employee'));

            $('#month').on('change', loadMonth);

            /* bonuses */
            $('#bonus_type_id').on('change', function() {
                const $o = $(this).find(':selected');
                $('#typeRule').text($o.val() ? 'Default: ' + rate($o.data('calc'), $o.data('value'), $o.data('basis')) : '');
            });
            $('#scope').on('change', showScope);
            $('#override').on('change', function() {
                const on = $(this).is(':checked');
                $('#overrideBox').toggleClass('d-none', !on);
                $('#calc_type, #value').prop('disabled', !on);
            });
            $('#bonusForm').on('submit', function(e) {
                e.preventDefault();
                submitForm($(this), R.bonusSave, $('#bonusSubmit').text('Saving...'), $('#bonus_error'),
                    $('#bonus_id').val() ? 'Update Bonus' : 'Grant Bonus', function() { resetBonusForm(); loadBonuses(); });
            });
            $('#bonusCancel').on('click', resetBonusForm);
            $(document).on('click', '.bonus-edit', function() {
                const b = bonusCache.find(function(x) { return x.id == $(this).data('id'); }, this);
                if (!b) return;
                resetBonusForm();
                $('#bonus_id').val(b.id);
                $('#bonus_type_id').val(String(b.bonus_type_id)).trigger('change');
                $('#scope').val(b.scope);
                showScope();
                scopeInput().val(b.scope_id ? String(b.scope_id) : '').trigger('change.select2');
                if (b.calc_type) {
                    $('#override').prop('checked', true).trigger('change');
                    $('#calc_type').val(b.calc_type);
                    $('#value').val(parseFloat(b.value));
                }
                $('#bonus_remarks').val(b.remarks || '');
                $('#bonusFormTitle').text('Edit bonus');
                $('#bonusSubmit').text('Update Bonus');
                $('#bonusCancel').removeClass('d-none');
            });
            $(document).on('click', '.bonus-del', function() {
                confirmDelete('Remove this bonus from ' + monthLabel() + '?', R.bonusDelete.replace('__ID__', $(this).data('id')), loadBonuses);
            });

            /* additions & deductions */
            $('input[name="adj_type"]').on('change', refreshHeads);
            $('#head').on('input change', refreshHeads);
            $('#days, #adj_employee').on('input change', absenceHint);
            $('#adjForm').on('submit', function(e) {
                e.preventDefault();
                submitForm($(this), R.entrySave, $('#adjSubmit').text('Saving...'), $('#adj_error'), 'Save', function() { resetAdjForm(); loadEntries(); });
            });
            $('#adjCancel').on('click', resetAdjForm);
            $(document).on('click', '.adj-edit', function() {
                const a = adjCache.find(function(x) { return x.id == $(this).data('id'); }, this);
                if (!a) return;
                resetAdjForm();
                $('#adj_id').val(a.id);
                $('#adj_employee').val(String(a.employee_id)).trigger('change.select2');
                $(a.adj_type === 'Addition' ? '#typeAdd' : '#typeDed').prop('checked', true);
                $('#head').val(a.head);
                refreshHeads();
                $('#days').val(a.days ? parseFloat(a.days) : '');
                $('#amount').val(parseFloat(a.amount));
                $('#adj_remarks').val(a.remarks || '');
                absenceHint();
                $('#adjFormTitle').text('Edit entry');
                $('#adjCancel').removeClass('d-none');
            });
            $(document).on('click', '.adj-del', function() {
                confirmDelete('Remove this entry?', R.entryDelete.replace('__ID__', $(this).data('id')), loadEntries);
            });

            /* types */
            $('#type_calc').on('change', function() { $('.basis-box').toggleClass('d-none', $(this).val() !== 'Percentage'); });
            $('#typeForm').on('submit', function(e) {
                e.preventDefault();
                submitForm($(this), R.typeSave, $('#typeSubmit').text('Saving...'), $('#type_error'), 'Save Type', function() {
                    // The bonus type lists on the other tabs are rendered by the server: reload, but come back to this tab.
                    resetTypeForm(); loadTypes();
                    setTimeout(function() { history.replaceState(null, '', location.pathname + location.search + '#tabTypes'); location.reload(); }, 600);
                });
            });
            $('#typeCancel').on('click', resetTypeForm);
            $(document).on('click', '.type-edit', function() {
                const t = typeCache.find(function(x) { return x.id == $(this).data('id'); }, this);
                if (!t) return;
                $('#type_id').val(t.id);
                $('#type_name').val(t.name);
                $('#type_calc').val(t.calc_type).trigger('change');
                $('#type_value').val(parseFloat(t.value));
                $('#type_basis').val(t.basis);
                $('#type_eligible').val(t.eligible_only);
                $('#type_status').val(t.status);
                $('#type_description').val(t.description || '');
                $('#typeFormTitle').text('Edit ' + t.name);
                $('#typeCancel').removeClass('d-none');
            });
            $(document).on('click', '.type-del', function() {
                confirmDelete('Remove this bonus type?', R.typeDelete.replace('__ID__', $(this).data('id')), loadTypes);
            });

            showScope();
            refreshHeads();
            loadTypes();
            loadMonth();
        });
    </script>
@endpush
