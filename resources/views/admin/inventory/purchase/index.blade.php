@extends('admin.master')

@section('title')
    {{$generalSetting['company_name'] ?? '' }} Purchases
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Purchase List
                        </h5>
                        <a href="{{ route('purchases.create') }}" class="btn btn-primary">
                            <i class="fa fa-plus"></i>
                            Add Purchase
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="purchaseTable" class="table table-bordered table-striped datatable"
                                style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Invoice</th>
                                        <th>Date</th>
                                        <th>Supplier</th>
                                        <th>Subtotal</th>
                                        <th>Discount</th>
                                        <th>Grand Total</th>
                                        <th>Paid</th>
                                        <th>Due</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {

            loadPurchases();


            function loadPurchases() {

                $.ajax({
                    url: "{{ route('purchases.list') }}",
                    type: 'GET',

                    success: function(response) {

                        if (response.status) {

                            // destroy first so DataTables doesn't hold stale rows
                            if ($.fn.DataTable.isDataTable('#purchaseTable')) {
                                $('#purchaseTable').DataTable().destroy();
                            }

                            $('#purchaseTable tbody').html(response.html);

                            $('#purchaseTable').DataTable();

                        } else {
                            toastr.error(response.message || 'Could not load purchases.');
                        }
                    },

                    error: function() {
                        toastr.error('Something went wrong while loading purchases.');
                    }
                });
            }


            /*
            |--------------------------------------------------------------------------
            | Delete
            |--------------------------------------------------------------------------
            */

            $(document).on('click', '.deleteBtn', function() {

                let id = $(this).data('id');


                if (!confirm('Are you sure you want to delete this purchase?')) {

                    return;
                }


                $.ajax({

                    url: "{{ url('/admin/purchases/delete') }}/" + id,

                    type: 'POST',

                    data: {

                        _token: "{{ csrf_token() }}"

                    },

                    success: function(response) {

                        if (response.status) {

                            toastr.success(response.message);

                            loadPurchases();

                        } else {

                            toastr.error(response.message);

                        }

                    },

                    error: function(xhr) {

                        toastr.error(
                            xhr.responseJSON?.message ||
                            'Something went wrong.'
                        );

                    }

                });

            });


            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $(document).on('click', '.statusBtn', function() {

                let id = $(this).data('id');


                $.ajax({

                    url: "{{ url('/admin/purchases/status') }}/" + id,

                    type: 'POST',

                    data: {

                        _token: "{{ csrf_token() }}"

                    },

                    success: function(response) {

                        if (response.status) {

                            toastr.success(response.message);

                            loadPurchases();

                        } else {

                            toastr.error(response.message);

                        }

                    },

                    error: function(xhr) {

                        toastr.error(
                            xhr.responseJSON?.message ||
                            'Something went wrong.'
                        );

                    }

                });

            });

        });
    </script>
@endpush
