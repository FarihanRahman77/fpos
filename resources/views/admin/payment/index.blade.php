@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} {{ $type }}
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>{{ $type }}</h4>

            <button type="button" class="btn btn-primary" onclick="openpaymentModal()">
                Add {{ $type }}
            </button>
        </div>

        <div class="card">
            <div class="card-body">
                <table id="managePaymentTable" width="100%" style="width:100%"
                    class="table table-bordered table-hover datatable">
                    <thead>
                        <tr>
                            <th width="6%">SL.</th>
                            <th>{{ $type }} Code</th>
                            <th>Date</th>
                            <th>Party</th>
                            <th>Payment Method</th>
                            <th>Amount</th>
                            <th width="10%">Status</th>
                            <th width="8%">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalTitle">Add {{ $type }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="paymentForm">
                    @csrf

                    <input type="hidden" id="type" name="type" value="{{ $type }}">

                    <div class="modal-body">
                        <div class="row">

                            <div class="col-md-4 mb-3">
                                <label for="payment_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date"
                                    value="{{ date('Y-m-d') }}">
                                <span class="text-danger error-text payment_date_error"></span>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="party_id" class="form-label">
                                    @if ($type == 'Payment')
                                        Supplier
                                    @elseif($type == 'Payment Receive')
                                        Customer
                                    @else
                                        Party
                                    @endif
                                </label>
                                <select class="form-control select2" id="party_id" name="party_id">
                                    <option value="">Select Party</option>
                                    @foreach ($parties as $party)
                                        <option value="{{ $party->id }}">{{ $party->name }}@if ($party->phone)
                                                ({{ $party->phone }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                <span class="text-danger error-text party_id_error"></span>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="due_amount" class="form-label">Due</label>
                                <input type="text" class="form-control" id="due_amount" name="due_amount" readonly
                                    placeholder="Due Amount">
                                <span class="text-danger error-text due_amount_error"></span>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="amount"
                                    name="amount" placeholder="Amount">
                                <span class="text-danger error-text amount_error"></span>
                            </div>



                            @if ($type != 'Discount')
                                <div class="col-md-4 mb-3">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select class="form-control select2" id="payment_method" name="payment_method">
                                        <option value="">Select Payment Method</option>
                                        @foreach ($childCoas as $childCoa)
                                            <option value="{{ $childCoa->id }}">{{ $childCoa->account_name }}</option>
                                        @endforeach
                                    </select>
                                    <span class="text-danger error-text payment_method_error"></span>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="payment_method_account" class="form-label">Payment Account</label>
                                    <select class="form-control select2" id="payment_method_account" name="payment_method_account">
                                        <option value="">Select payment method first</option>
                                    </select>
                                    <span class="text-danger error-text payment_method_account_error"></span>
                                </div>
                            @endif

                            <div class="col-md-12 mb-3">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" placeholder="Remarks"></textarea>
                                <span class="text-danger error-text remarks_error"></span>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="paymentSubmitBtn">Save</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        let paymentTable;
        let paymentModal;

        $(document).ready(function() {

            // Select2 inside the modal: dropdownParent is the key fix
            $('#paymentModal .select2').select2({
                width: '100%',
                dropdownParent: $('#paymentModal')
            });

            paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));

            paymentTable = $('#managePaymentTable').DataTable({
                processing: true,
                ajax: {
                    url: "{{ route('admin.payment.list', $type) }}",
                    type: 'GET',
                    dataSrc: function(json) {
                        if (json.status === false) {
                            toastr.error(json.message || 'Failed to load data.');
                            return [];
                        }
                        return json.data;
                    },
                    error: function() {
                        toastr.error('Failed to load data.');
                    }
                },
                columns: [{
                        data: 0
                    },
                    {
                        data: 1
                    },
                    {
                        data: 2
                    },
                    {
                        data: 3
                    },
                    {
                        data: 4
                    },
                    {
                        data: 5
                    },
                    {
                        data: 6
                    },
                    {
                        data: 7,
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            // Load due amount when party changes
            $('#party_id').on('change', function() {
                let partyId = $(this).val();
                let type = $('#type').val();
                $('#due_amount').val('');

                if (!partyId) {
                    return;
                }

                $.ajax({
                    url: "{{ route('admin.payment.due') }}",
                    type: 'GET',
                    data: {
                        type: type,
                        party: partyId
                    },
                    success: function(response) {
                      //  alert(JSON.stringify(response));
                        if (response.status) {
                            $('#due_amount').val(response.due);
                        }
                    },
                    error: function(xhr) {
                        //alert(JSON.stringify(xhr));
                        toastr.error('Could not load due amount.');
                    }
                });
            });

            // Load the accounts of the selected payment method (same as the sale page)
            $('#payment_method').on('change', function() {
                let paymentMethod = $(this).val();
                let $account = $('#payment_method_account');

                if (!paymentMethod) {
                    $account.html('<option value="">Select payment method first</option>').trigger('change');
                    return;
                }

                $.ajax({
                    url: "{{ route('admin.getCoaChilds') }}",
                    type: 'GET',
                    data: {
                        parent_id: paymentMethod
                    },
                    success: function(response) {
                        $account.html(response).trigger('change');
                    },
                    error: function(xhr) {
                        toastr.error('Could not load payment accounts.');
                    }
                });
            });

            // Save
            $('#paymentForm').on('submit', function(e) {
                e.preventDefault();

                $('.error-text').text('');

                $('#paymentSubmitBtn').prop('disabled', true).text('Saving...');

                $.ajax({
                    url: "{{ route('admin.payment.store') }}",
                    type: 'POST',
                    data: $(this).serialize(),

                    success: function(response) {
                        if (response.status) {
                            paymentModal.hide();
                            paymentTable.ajax.reload(null, false);
                            toastr.success(response.message);
                            resetPaymentForm();
                        } else {
                            toastr.error(response.message);
                        }
                    },

                    error: function(xhr) {
                        if (xhr.status === 422) {
                            $.each(xhr.responseJSON.errors, function(key, value) {
                                $('.' + key + '_error').text(value[0]);
                            });
                        } else {
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    },

                    complete: function() {
                        $('#paymentSubmitBtn').prop('disabled', false).text('Save');
                    }
                });
            });

            // Clean form whenever modal is closed
            document.getElementById('paymentModal').addEventListener('hidden.bs.modal', resetPaymentForm);
        });

        function resetPaymentForm() {
            $('#paymentForm')[0].reset();
            $('#party_id').val('').trigger('change');
            $('#payment_method').val('').trigger('change');
            $('#due_amount').val('');
            $('.error-text').text('');
        }

        function openpaymentModal() {
            resetPaymentForm();
            $('#paymentSubmitBtn').prop('disabled', false).text('Save');
            paymentModal.show();
        }

        function deletepayment(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This payment will be deleted.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {

                if (!result.isConfirmed) {
                    return;
                }

                $.ajax({
                    url: "{{ url('admin/payment/delete') }}",
                    data: {
                        id: id
                    },
                    type: 'GET',

                    success: function(response) {
                        if (response.status) {
                            paymentTable.ajax.reload(null, false);

                            Swal.fire({
                                title: 'Deleted!',
                                text: response.message,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },

                    error: function(xhr) {
                        Swal.fire(
                            'Error',
                            xhr.responseJSON?.message || 'Something went wrong.',
                            'error'
                        );
                    }
                });
            });
        }
    </script>
@endpush
