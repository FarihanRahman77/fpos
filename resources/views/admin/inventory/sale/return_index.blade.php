@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} Sale Returns
@endsection

@section('content')

<div class="container-fluid">

    <div class="card">

        <div class="card-header d-flex justify-content-between">

            <h5 class="mb-0">
                Sale Returns
            </h5>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped"
                       id="saleReturnsTable">

                    <thead>

                    <tr>
                        <th>#</th>
                        <th>Return No</th>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th width="150">Action</th>
                    </tr>

                    </thead>

                    <tbody id="saleReturnsBody">

                    <tr>
                        <td colspan="9"
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

    loadSaleReturns();

});


function loadSaleReturns() {

    $.ajax({

        url: "{{ route('sales.returns.list') }}",

        type: "GET",

        success: function (response) {

            $('#saleReturnsBody').html(response.html);

            if ($.fn.DataTable) {

                if ($.fn.DataTable.isDataTable('#saleReturnsTable')) {
                    $('#saleReturnsTable').DataTable().destroy();
                }

                $('#saleReturnsTable').DataTable({
                    pageLength: 25
                });

            }

        },

        error: function (xhr) {

            toastr.error(
                xhr.responseJSON?.message ||
                'Unable to load sale returns.'
            );

        }

    });

}


$(document).on('click', '.deleteSaleReturnBtn', function () {

    let id = $(this).data('id');

    Swal.fire({

        title: 'Delete Return?',

        text: 'This sale return will be deleted.',

        icon: 'warning',

        showCancelButton: true,

        confirmButtonText: 'Yes, Delete',

        cancelButtonText: 'Cancel'

    }).then(function (result) {

        if (!result.isConfirmed) {
            return;
        }

        $.ajax({

            url: "{{ url('/admin/sales/return/delete') }}/" + id,

            type: 'POST',

            data: {
                _token: "{{ csrf_token() }}"
            },

            success: function (response) {

                if (response.status) {

                    toastr.success(response.message);

                    loadSaleReturns();

                } else {

                    toastr.error(response.message);

                }

            },

            error: function (xhr) {

                toastr.error(
                    xhr.responseJSON?.message ||
                    'Unable to delete return.'
                );

            }

        });

    });

});

</script>

@endpush