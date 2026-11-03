@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} {{ $type }}
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0">{{ $type }}</h4>
                @if ($type == 'Discount')
                    <small class="text-muted">Discount given (to customers) lowers what a customer owes; discount received (from suppliers) lowers what we owe.</small>
                @elseif ($type == 'Payment')
                    <small class="text-muted">Pay suppliers, or pay a customer's credit (advance or returns kept as credit) back in cash.</small>
                @else
                    <small class="text-muted">Receive from customers (walk-in customers with a due are listed too); optionally against one invoice.</small>
                @endif
            </div>

            <button type="button" class="btn btn-primary" onclick="openpaymentModal()">
                Add {{ $type }}
            </button>
        </div>

        @if ($type == 'Discount')
            <div class="btn-group mb-3" role="group" aria-label="Discount side">
                <button type="button" class="btn btn-outline-primary active discount-side" data-side="">All</button>
                <button type="button" class="btn btn-outline-primary discount-side" data-side="customer">Given to customers</button>
                <button type="button" class="btn btn-outline-primary discount-side" data-side="supplier">Received from suppliers</button>
            </div>
        @endif

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
                                <label for="payment_date" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date"
                                    value="{{ date('Y-m-d') }}">
                                <span class="text-danger error-text payment_date_error"></span>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="party_id" class="form-label">
                                    @if ($type == 'Payment')
                                        Supplier / customer credit
                                    @elseif($type == 'Payment Receive')
                                        Customer
                                    @else
                                        Party
                                    @endif
                                    <span class="text-danger">*</span>
                                </label>
                                <select class="form-control select2" id="party_id" name="party_id">
                                    <option value="">Select Party</option>
                                    @php
                                        $groups = $type == 'Discount'
                                            ? ['Customers (discount given)' => $parties->whereIn('type', ['Customer', 'Walk-In Customer']), 'Suppliers (discount received)' => $parties->where('type', 'Supplier')]
                                            : ($type == 'Payment'
                                                ? ['Suppliers' => $parties->where('type', 'Supplier'), 'Customers with credit (pay back)' => $parties->whereIn('type', ['Customer', 'Walk-In Customer'])]
                                                : ['Customers' => $parties]);
                                    @endphp
                                    @foreach ($groups as $groupName => $groupParties)
                                        @if ($groupParties->isNotEmpty())
                                            <optgroup label="{{ $groupName }}">
                                                @foreach ($groupParties as $party)
                                                    <option value="{{ $party->id }}" data-type="{{ $party->type }}">{{ $party->label }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                                <span class="text-danger error-text party_id_error"></span>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="due_amount" class="form-label">Balance</label>
                                <input type="text" class="form-control" id="due_amount" name="due_amount" readonly
                                    placeholder="Due Amount">
                                <span class="text-danger error-text due_amount_error"></span>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control" id="amount"
                                    name="amount" placeholder="Amount">
                                <span class="text-danger error-text amount_error"></span>
                            </div>



                            @if ($type == 'Payment Receive')
                                <div class="col-md-8 mb-3">
                                    <label for="sale_id" class="form-label">Against invoice <small class="text-muted">(optional)</small></label>
                                    <select class="form-control select2" id="sale_id" name="sale_id">
                                        <option value="">On account (no particular invoice)</option>
                                    </select>
                                    <span class="text-danger error-text sale_id_error"></span>
                                </div>
                            @endif

                            @if ($type != 'Discount')
                                <div class="col-md-4 mb-3">
                                    <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-control select2" id="payment_method" name="payment_method">
                                        <option value="">Select Payment Method</option>
                                        @foreach ($childCoas as $childCoa)
                                            <option value="{{ $childCoa->id }}">{{ $childCoa->account_name }}</option>
                                        @endforeach
                                    </select>
                                    <span class="text-danger error-text payment_method_error"></span>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="payment_method_account" class="form-label">Payment Account <span class="text-danger">*</span></label>
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
        let discountSide = '';

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
                    data: function(d) {
                        d.side = discountSide;
                    },
                    dataSrc: function(json) {
                        if (json.status === false) {
                            toastr.error(json.message || 'Failed to load data.');
                            return [];
                        }
                        return json.data;
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
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
                    dataType: 'json',
                    data: {
                        type: type,
                        party: partyId
                    },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (response.status) {
                            $('#due_amount').val(response.due_text || (parseFloat(response.due) || 0).toFixed(2));
                        }
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        toastr.error('Could not load due amount.');
                    }
                });
            });

            // Payment Receive: the customer's open invoices, to apply the receipt to one of them.
            $('#party_id').on('change', function() {
                let $invoice = $('#sale_id');
                if (!$invoice.length) {
                    return;
                }
                $invoice.html('<option value="">On account (no particular invoice)</option>').trigger('change');
                if (!$(this).val()) {
                    return;
                }
                $.ajax({
                    url: "{{ route('admin.payment.invoices') }}",
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        party: $(this).val()
                    },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        $.each(response.invoices || [], function(i, inv) {
                            $invoice.append($('<option>', { value: inv.id, text: inv.text }).attr('data-due', inv.due));
                        });
                        $invoice.trigger('change');
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        toastr.error('Could not load the open invoices.');
                    }
                });
            });

            $('#sale_id').on('change', function() {
                let due = $(this).find(':selected').data('due');
                if (due) {
                    $('#amount').val(parseFloat(due).toFixed(2));
                }
            });

            $('.discount-side').on('click', function() {
                $('.discount-side').removeClass('active');
                $(this).addClass('active');
                discountSide = $(this).data('side') || '';
                paymentTable.ajax.reload();
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
                    dataType: 'html',
                    data: {
                        parent_id: paymentMethod
                    },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        $account.html(response).trigger('change');
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
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
                    dataType: 'json',
                    data: $(this).serialize(),

                    success: function(response) {
                        // alert(JSON.stringify(response));
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
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON?.errors) {
                            let shown = false;
                            $.each(xhr.responseJSON.errors, function(key, value) {
                                if ($('.' + key + '_error').length) {
                                    $('.' + key + '_error').text(value[0]);
                                    shown = true;
                                }
                            });
                            if (!shown) {
                                toastr.error(xhr.responseJSON.message || 'Please check the form.');
                            }
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
                confirmButtonColor: 'var(--color-danger)',
                cancelButtonColor: 'var(--color-text-muted)',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {

                if (!result.isConfirmed) {
                    return;
                }

                $.ajax({
                    url: "{{ route('admin.payment.delete') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _token: "{{ csrf_token() }}",
                        id: id
                    },

                    success: function(response) {
                        // alert(JSON.stringify(response));
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
                        // alert(JSON.stringify(xhr));
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
