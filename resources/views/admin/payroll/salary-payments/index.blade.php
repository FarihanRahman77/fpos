@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Salary Payments
@endsection

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Salary Payments</h4>
                <small class="text-muted">Full and partial salary payments made from the salary sheet. Cancelling one reverses it and makes that amount due again.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <input type="number" id="filterYear" class="form-control" style="width: 110px;" value="{{ now()->year }}" min="2000" max="2100" aria-label="Year">
                <a href="{{ route('admin.reports.salary-sheet') }}" class="btn btn-primary"><i class="fas fa-money-check-alt"></i> Salary Sheet</a>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="payTable" class="table table-hover align-middle w-100 acc-table" data-page-length="25" data-order="[]" data-auto-width="false">
                        <thead>
                            <tr>
                                <th data-orderable="false" width="4%">SL.</th>
                                <th>No.</th>
                                <th>Month</th>
                                <th>Paid On</th>
                                <th>Paid From</th>
                                <th class="text-center">Employees</th>
                                <th class="text-end">Paid</th>
                                <th>Status</th>
                                <th data-orderable="false" width="6%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewTitle">Salary Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewBody"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const listUrl = "{{ route('admin.salary-payments.list') }}";

        const SalaryPay = {
            table: null,
            viewModal: null,

            reload: function() { SalaryPay.table.ajax.url(listUrl + '?year=' + $('#filterYear').val()).load(null, false); },

            view: function(id) {
                $.ajax({
                    url: "{{ url('admin/salary-payments') }}/" + id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const p = response.data;
                        const m = Voucher.money;
                        const kv = function(k, v) { return '<div class="col-6 col-md-3"><div class="text-muted small">' + k + '</div><div>' + Voucher.esc(v || '-') + '</div></div>'; };
                        const rows = response.details.map(function(d, i) {
                            return '<tr><td>' + (i + 1) + '</td><td><span class="fw-semibold">' + Voucher.esc(d.name) + '</span><div class="text-muted small">' +
                                Voucher.esc(d.employee_code) + '</div></td>' +
                                '<td>' + Voucher.esc(d.department || '-') + '</td>' +
                                '<td class="text-end">' + m(d.net_payable) + '</td><td class="text-end">' + m(d.paid_amount) + '</td>' +
                                '<td class="text-end">' + m(parseFloat(d.net_payable) - parseFloat(d.paid_amount)) + '</td>' +
                                '<td class="text-end fw-semibold">' + m(d.amount) + '</td></tr>';
                        }).join('');

                        $('#viewTitle').text('Salary Payment ' + p.payment_no);
                        $('#viewBody').html(
                            '<div class="row g-3 mb-3">' +
                                kv('Month', new Date(p.month + 'T00:00:00').toLocaleString('en', { month: 'long', year: 'numeric' })) +
                                kv('Paid On', p.payment_date) + kv('Paid From', p.account) + kv('Status', p.payment_status) +
                                kv('Processed Salary', p.run_no) + kv('Posted By', p.created_by_name) +
                                (p.remarks ? kv('Remarks', p.remarks) : '') +
                                (p.payment_status === 'Cancelled' ? kv('Cancel Reason', p.cancel_reason) : '') +
                            '</div>' +
                            '<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>#</th><th>Employee</th><th>Department</th>' +
                            '<th class="text-end">Net Payable</th><th class="text-end">Paid (all payments)</th><th class="text-end">Still Due</th>' +
                            '<th class="text-end">This Payment</th></tr></thead><tbody>' + rows + '</tbody>' +
                            '<tfoot><tr class="fw-semibold"><td colspan="6">Total (' + p.employee_count + ')</td><td class="text-end">' + m(p.total_paid) +
                            '</td></tr></tfoot></table></div>'
                        );
                        SalaryPay.viewModal.show();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load the payment.');
                    }
                });
            },

            cancel: function(id) {
                Swal.fire({
                    title: 'Cancel this salary payment?',
                    text: 'The cash / bank entry is reversed and the amount becomes due again. Write the reason:',
                    input: 'text', inputPlaceholder: 'Reason', icon: 'warning', showCancelButton: true,
                    confirmButtonText: 'Cancel payment', cancelButtonText: 'Keep', confirmButtonColor: 'var(--color-danger)', reverseButtons: true,
                    inputValidator: function(v) { return v && v.trim() ? null : 'Write the reason.'; }
                }).then(function(r) {
                    if (!r.isConfirmed) return;
                    $.ajax({
                        url: "{{ url('admin/salary-payments') }}/" + id + '/cancel',
                        type: 'POST',
                        dataType: 'json',
                        data: { reason: r.value },
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            toastr.success(response.message);
                            SalaryPay.reload();
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            Voucher.ajaxError(xhr, 'Unable to cancel.');
                        }
                    });
                });
            }
        };

        $(document).ready(function() {
            SalaryPay.viewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal'));
            Voucher.keepSerial($('#payTable'));
            SalaryPay.table = $('#payTable').DataTable({ ajax: listUrl + '?year=' + $('#filterYear').val(), processing: true });
            $('#filterYear').on('change', SalaryPay.reload);
        });
    </script>
@endpush
