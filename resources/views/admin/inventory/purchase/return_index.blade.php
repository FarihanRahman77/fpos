@extends('admin.master')

@section('title')
{{ $generalSetting['company_name'] ?? '' }} Purchase Returns
@endsection

@section('content')

```
<div class="container-fluid">

    <div class="row">

        <div class="col-md-12">

            <div class="card">

                <div class="card-header d-flex justify-content-between align-items-center">

                    <h5 class="mb-0">
                        Purchase Return List
                    </h5>

                    <a href="{{ route('purchases.index') }}"
                       class="btn btn-secondary">

                        <i class="fa fa-arrow-left me-1"></i>

                        Purchase List

                    </a>

                </div>


                <div class="card-body">

                    <div class="table-responsive">

                        <table id="purchaseReturnTable"
                               class="table table-bordered table-striped datatable"
                               style="width:100%">

                            <thead>

                                <tr>

                                    <th>#</th>

                                    <th>Return No</th>

                                    <th>Purchase Invoice</th>

                                    <th>Date</th>

                                    <th>Supplier</th>

                                    <th>Subtotal</th>

                                    <th>Discount</th>

                                    <th>Grand Total</th>

                                    <th>Status</th>

                                    <th>Action</th>

                                </tr>

                            </thead>

                            <tbody>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>
```

@endsection

@push('scripts')

<script>

$(document).ready(function () {

    /*
    |--------------------------------------------------------------------------
    | Load Purchase Returns
    |--------------------------------------------------------------------------
    */

    loadPurchaseReturns();


    function loadPurchaseReturns() {

        $.ajax({

            url: "{{ route('purchases.return.list') }}",

            type: "GET",

            success: function (response) {

                //alert(JSON.stringify(response));
                if (response.status) {

                    /*
                    |--------------------------------------------------------------------------
                    | Destroy Existing DataTable
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $.fn.DataTable.isDataTable(
                            '#purchaseReturnTable'
                        )
                    ) {

                        $('#purchaseReturnTable')
                            .DataTable()
                            .destroy();

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Load HTML
                    |--------------------------------------------------------------------------
                    */

                    $('#purchaseReturnTable tbody')
                        .html(response.html);


                    /*
                    |--------------------------------------------------------------------------
                    | Initialize DataTable
                    |--------------------------------------------------------------------------
                    */

                    $('#purchaseReturnTable')
                        .DataTable({

                            pageLength: 25,

                            ordering: true,

                            searching: true,

                            responsive: true,

                            columnDefs: [

                                {
                                    orderable: false,
                                    searchable: false,
                                    targets: [0, 9]
                                }

                            ]

                        });

                } else {

                    toastr.error(
                        response.message ||
                        'Could not load purchase returns.'
                    );

                }

            },

            error: function (xhr) {
                //alert(JSON.stringify(xhr));
                toastr.error(

                    xhr.responseJSON?.message ||
                    'Something went wrong while loading purchase returns.'

                );

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Delete Purchase Return
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'click',
        '.deleteReturnBtn',
        function () {

            let id =
                $(this).data('id');


            if (!id) {

                toastr.error(
                    'Invalid purchase return.'
                );

                return;

            }


            if (
                !confirm(
                    'Are you sure you want to delete this purchase return?'
                )
            ) {

                return;

            }


            $.ajax({

                url:
                    "{{ url('/admin/purchases/returns/delete') }}/" +
                    id,

                type: "POST",

                data: {

                    _token:
                        "{{ csrf_token() }}"

                },

                success: function (response) {
                   // alert(JSON.stringify(response));
                    if (response.status) {

                        toastr.success(
                            response.message
                        );


                        loadPurchaseReturns();

                    } else {

                        toastr.error(
                            response.message
                        );

                    }

                },

                error: function (xhr) {
                   // alert(JSON.stringify(xhr));
                    toastr.error(

                        xhr.responseJSON?.message ||
                        'Something went wrong.'

                    );

                }

            });

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'click',
        '.returnStatusBtn',
        function () {

            let id =
                $(this).data('id');


            if (!id) {

                toastr.error(
                    'Invalid purchase return.'
                );

                return;

            }


            $.ajax({

                url:
                    "{{ url('/admin/purchases/returns/status') }}/" +
                    id,

                type: "POST",

                data: {

                    _token:
                        "{{ csrf_token() }}"

                },

                success: function (response) {

                    if (response.status) {

                        toastr.success(
                            response.message
                        );


                        loadPurchaseReturns();

                    } else {

                        toastr.error(
                            response.message
                        );

                    }

                },

                error: function (xhr) {

                    toastr.error(

                        xhr.responseJSON?.message ||
                        'Something went wrong.'

                    );

                }

            });

        }
    );

});

</script>

@endpush
