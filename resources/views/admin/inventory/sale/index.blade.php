@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} Sales
@endsection

@section('content')

<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">

            <div class="card">

                <div class="card-header d-flex justify-content-between align-items-center">

                    <h5 class="mb-0">{{ $sale_type }} Sales</h5>

                    <div>
                        <a href="{{ route('sales.create', ['type' => $sale_type]) }}"
                           class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i>
                           {{ $sale_type}} Add Sale
                        </a>
                    </div>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered table-striped"
                               id="salesTable" width="100%">

                            <thead>
                            <tr>
                                <th width="2%">#</th>
                                <th  width="10%">Invoice</th>
                                <th  width="10%">Date</th>
                                <th  width="14%">Customer</th>
                                <th  width="10%" class="text-end">Subtotal</th>
                                <th  width="10%" class="text-end">Discount</th>
                                <th  width="10%" class="text-end">Total</th>
                                <th  width="10%" class="text-end">Paid</th>
                                <th  width="10%" class="text-end">Due</th>
                                <th  width="10%">Payment</th>
                                <th  width="4%">Action</th>
                            </tr>
                            </thead>

                            <tbody id="salesTableBody">
                            <tr>
                                <td colspan="13" class="text-center">
                                    Loading...
                                </td>
                            </tr>
                            </tbody>

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

$(document).ready(function () {

    loadSales();

    $('#saleType').on('change', function () {

        let type = $(this).val();

        window.location.href ="{{ route('sales.index') }}?type=" + encodeURIComponent(type);

    });

});


function loadSales() {

    let type = $('#saleType').val();

    $.ajax({

        url: "{{ route('sales.list') }}",

        type: "GET",

        data: {
            type: type
        },

        beforeSend: function () {

            $('#salesTableBody').html(`
                <tr>
                    <td colspan="13" class="text-center">
                        Loading...
                    </td>
                </tr>
            `);

        },

        success: function (response) {

            $('#salesTableBody').html(response.html);

            if ($.fn.DataTable) {

                if ($.fn.DataTable.isDataTable('#salesTable')) {
                    $('#salesTable').DataTable().destroy();
                }

                $('#salesTable').DataTable({
                    pageLength: 25,
                    ordering: true,
                    searching: true
                });

            }

        },

        error: function (xhr) {

            toastr.error(
                xhr.responseJSON?.message ||
                'Unable to load sales.'
            );

        }

    });

}


$(document).on('click', '.deleteSaleBtn', function () {

    let id = $(this).data('id');

    Swal.fire({

        title: 'Delete Sale?',

        text: 'This sale and related records will be deleted.',

        icon: 'warning',

        showCancelButton: true,

        confirmButtonText: 'Yes, Delete',

        cancelButtonText: 'Cancel'

    }).then((result) => {

        if (!result.isConfirmed) {
            return;
        }

        $.ajax({

            url: "{{ url('/admin/sales/delete') }}/" + id,

            type: 'POST',

            data: {
                _token: "{{ csrf_token() }}"
            },

            success: function (response) {

                if (response.status) {

                    toastr.success(response.message);

                    loadSales();

                } else {

                    toastr.error(response.message);

                }

            },

            error: function (xhr) {

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
