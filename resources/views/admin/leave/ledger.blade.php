@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Leave Ledger
@endsection

@push('styles')
    <style>
        .ledger-block { border: 1px solid var(--admin-border); border-radius: var(--radius-lg); overflow: hidden; }
        .ledger-head { padding: 8px 14px; background: var(--admin-surface-soft); border-bottom: 1px solid var(--admin-border); }
        .ledger-table { font-size: var(--text-caption); }
        .ledger-table tbody td, .ledger-table thead th { min-width: 0; padding: .4rem 12px; }
        #reportBody { min-height: 120px; }
        #reportBody.loading { opacity: .45; pointer-events: none; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Leave Ledger</h4>
                <small class="text-muted">Yearly entitlement from the employee's leave group, adjustments for this employee, and leave taken.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" id="adjustBtn"><i class="fas fa-plus-minus"></i> Adjust Balance</button>
            </div>
        </div>

        <div class="card acc-card mb-3">
            <div class="card-body">
                <form id="filterForm" class="row g-3 align-items-end" novalidate>
                    <div class="col-md-5">
                        <label class="form-label" for="employee_id">Employee <span class="text-danger">*</span></label>
                        <select id="employee_id" class="form-select">
                            @if ($selected)<option value="{{ $selected->id }}" selected>{{ $selected->name }} ({{ $selected->employee_code }})</option>@endif
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="year">Year</label>
                        <input type="number" id="year" class="form-control" value="{{ $year }}" min="2000" max="2100">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Show</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body" id="reportBody">
                <div class="text-center text-muted py-5">Select an employee.</div>
            </div>
        </div>
    </div>

    {{-- Adjustment --}}
    <div class="modal fade" id="adjustModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="adjustForm" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title">Adjust Leave Balance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">Gives this employee more (+) or fewer (−) days of a leave type than the group rule —
                            e.g. carry forward, special grant, encashment.</p>
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label" for="adj_type">Leave Type <span class="text-danger">*</span></label>
                                <select name="leave_type_id" id="adj_type" class="form-select">
                                    @foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="adj_days">Days (+/−) <span class="text-danger">*</span></label>
                                <input type="number" step="0.5" name="days" id="adj_days" class="form-control text-end" placeholder="e.g. 5 or -2">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="adj_date">Date</label>
                                <input type="date" name="entry_date" id="adj_date" class="form-control">
                            </div>
                            <div class="col-md-7">
                                <label class="form-label" for="adj_remarks">Remarks <span class="text-danger">*</span></label>
                                <input type="text" name="remarks" id="adj_remarks" class="form-control" maxlength="255" placeholder="e.g. Carry forward from last year">
                                <div class="text-danger small field-error"></div>
                            </div>
                        </div>
                        <div class="text-danger small mt-2" id="adjust_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const Ledger = {
            modal: null,

            load: function() {
                const employeeId = $('#employee_id').val();
                if (!employeeId) return;
                history.replaceState(null, '', '?employee_id=' + employeeId);

                $('#reportBody').addClass('loading');
                $.ajax({
                    url: "{{ route('admin.leave.ledger.data') }}",
                    type: 'GET',
                    dataType: 'json',
                    data: { employee_id: employeeId, year: $('#year').val() },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        $('#reportBody').html(response.html);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load the ledger.');
                    },
                    complete: function() { $('#reportBody').removeClass('loading'); }
                });
            },

            removeAdjustment: function(id) {
                Swal.fire({ title: 'Remove this adjustment?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'var(--color-danger)', confirmButtonText: 'Remove', reverseButtons: true })
                    .then(function(r) {
                        if (!r.isConfirmed) return;
                        $.ajax({
                            url: "{{ url('admin/leave/ledger/adjustment') }}/" + id + '/delete',
                            type: 'POST',
                            dataType: 'json',
                            success: function(response) {
                                // alert(JSON.stringify(response));
                                toastr.success(response.message);
                                Ledger.load();
                            },
                            error: function(xhr) {
                                // alert(JSON.stringify(xhr));
                                Voucher.ajaxError(xhr, 'Unable to remove.');
                            }
                        });
                    });
            }
        };

        $(document).ready(function() {
            Ledger.modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('adjustModal'));

            $('#employee_id').select2({
                theme: 'bootstrap-5', width: '100%', placeholder: 'Search by name or ID',
                ajax: { url: "{{ route('admin.employee.search') }}", dataType: 'json', delay: 250, data: function(p) { return { q: p.term || '' }; } }
            }).on('change', Ledger.load);
            $('#year').on('change', Ledger.load);
            $('#filterForm').on('submit', function(e) { e.preventDefault(); Ledger.load(); });

            $('#adjustBtn').on('click', function() {
                if (!$('#employee_id').val()) {
                    toastr.warning('Select the employee first.');
                    return;
                }
                $('#adjustForm')[0].reset();
                Voucher.clearErrors($('#adjustForm'));
                $('#adjust_error').text('');
                $('#adj_date').val(Voucher.todayLocal());
                Ledger.modal.show();
            });

            $('#adjustForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{ route('admin.leave.adjustment') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize() + '&' + $.param({ employee_id: $('#employee_id').val(), year: $('#year').val() }),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        Ledger.modal.hide();
                        Ledger.load();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($('#adjustForm'), xhr.responseJSON.errors, $('#adjust_error'));
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save.');
                        }
                    }
                });
            });

            Ledger.load();
        });
    </script>
@endpush
