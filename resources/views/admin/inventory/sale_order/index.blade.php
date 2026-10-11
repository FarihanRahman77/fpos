@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} Sale Orders
@endsection

@section('content')

<div class="container-fluid">

    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h5 class="mb-0">
                Sale Orders
            </h5>

            <a href="{{ route('sales.orders.create') }}"
               class="btn btn-primary">

                <i class="bi bi-plus-circle"></i>

                Add Sale Order

            </a>

        </div>


        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped datatable"
                       id="saleOrdersTable">

                    <thead>

                    <tr>

                        <th>#</th>

                        <th>Order No</th>

                        <th>Order Date</th>

                        <th>Delivery Date</th>

                        <th>Customer</th>

                        <th>Total</th>

                        <th>Advance</th>

                        <th>Due</th>

                        <th>Status</th>

                        <th width="150">
                            Action
                        </th>

                    </tr>

                    </thead>


                    <tbody id="saleOrdersBody">

                    <tr>

                        <td colspan="10"
                            class="text-center">

                            Loading...

                        </td>

                    </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

@endsection


@push('scripts')

<script>

$(document).ready(function () {

    loadSaleOrders();

});


/*
|--------------------------------------------------------------------------
| LOAD SALE ORDERS
|--------------------------------------------------------------------------
*/
function loadSaleOrders() {

    $.ajax({

        url: "{{ route('sales.orders.list') }}",

        type: "GET",

        success: function (response) {

            if (
                $.fn.DataTable &&
                $.fn.DataTable.isDataTable(
                    '#saleOrdersTable'
                )
            ) {

                $('#saleOrdersTable')
                    .DataTable()
                    .destroy();
            }


            $('#saleOrdersBody')
                .html(response.html);


            if ($.fn.DataTable) {

                $('#saleOrdersTable')
                    .DataTable({

                        pageLength: 25

                    });
            }

        },

        error: function (xhr) {

            toastr.error(
                xhr.responseJSON?.message ||
                'Unable to load sale orders.'
            );

        }

    });

}


/*
|--------------------------------------------------------------------------
| CANCEL ORDER
|--------------------------------------------------------------------------
*/
$(document).on(
    'click',
    '.cancelOrderBtn',
    function () {

        let id =
            $(this).data('id');


        Swal.fire({

            title: 'Cancel Sale Order?',

            text:
                'The order will be marked as Cancelled.',

            icon: 'warning',

            input: 'textarea',

            inputLabel:
                'Reason (optional)',

            inputAttributes: {

                maxlength: 500

            },

            showCancelButton: true,

            confirmButtonText:
                'Yes, Cancel Order',

            cancelButtonText:
                'Back'

        }).then(function (result) {

            if (!result.isConfirmed) {

                return;
            }


            $.ajax({

                url:
                    "{{ url('/admin/sales/orders/cancel') }}/"
                    + id,

                type: "POST",

                data: {

                    _token:
                        "{{ csrf_token() }}",

                    reason:
                        result.value || ''

                },

                success: function (response) {

                    if (response.status) {

                        toastr.success(
                            response.message
                        );

                        loadSaleOrders();

                    } else {

                        toastr.error(
                            response.message
                        );
                    }

                },

                error: function (xhr) {

                    toastr.error(
                        xhr.responseJSON?.message ||
                        'Unable to cancel order.'
                    );

                }

            });

        });

    }
);


/*
|--------------------------------------------------------------------------
| DELETE ORDER
|--------------------------------------------------------------------------
*/
$(document).on(
    'click',
    '.deleteOrderBtn',
    function () {

        let id =
            $(this).data('id');


        Swal.fire({

            title:
                'Delete Sale Order?',

            text:
                'This sale order will be deleted.',

            icon:
                'warning',

            showCancelButton:
                true,

            confirmButtonText:
                'Yes, Delete',

            cancelButtonText:
                'Cancel'

        }).then(function (result) {

            if (!result.isConfirmed) {

                return;
            }


            $.ajax({

                url:
                    "{{ url('/admin/sales/orders/delete') }}/"
                    + id,

                type:
                    "POST",

                data: {

                    _token:
                        "{{ csrf_token() }}"

                },

                success: function (response) {

                    if (response.status) {

                        toastr.success(
                            response.message
                        );

                        loadSaleOrders();

                    } else {

                        toastr.error(
                            response.message
                        );
                    }

                },

                error: function (xhr) {

                    toastr.error(
                        xhr.responseJSON?.message ||
                        'Unable to delete order.'
                    );

                }

            });

        });

    }
);


</script>

@endpush
