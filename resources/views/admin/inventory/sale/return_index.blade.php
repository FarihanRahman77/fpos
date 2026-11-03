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

            @perm('sale_returns.create')
                <button type="button" class="btn btn-primary btn-sm" id="newReturnBtn">
                    <i class="fas fa-plus"></i> New Return
                </button>
            @endperm

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

{{-- New return: pick the invoice the goods were sold on, then the return page opens. --}}
<div class="modal fade" id="newReturnModal" tabindex="-1" aria-labelledby="newReturnTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newReturnTitle">New Sale Return</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="returnInvoicePicker">Sale invoice <span class="text-danger">*</span></label>
                <select id="returnInvoicePicker" class="form-control"></select>
                <div class="form-text">Search by invoice no., customer name or phone.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="openReturnBtn">Continue</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')

<script>

$(document).ready(function () {

    loadSaleReturns();

    $('#returnInvoicePicker').select2({
        width: '100%',
        dropdownParent: $('#newReturnModal'),
        placeholder: 'Search invoice',
        ajax: {
            url: "{{ route('sales.return.invoices') }}",
            dataType: 'json',
            delay: 250,
            data: function (p) { return { q: p.term || '' }; },
            processResults: function (res) { return { results: res.results || [] }; }
        }
    });

    $('#newReturnBtn').on('click', function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('newReturnModal')).show();
    });

    $('#openReturnBtn').on('click', function () {
        let picked = $('#returnInvoicePicker').select2('data')[0];
        if (!picked || !picked.url) {
            toastr.error('Choose the sale invoice first.');
            return;
        }
        window.location.href = picked.url;
    });

});


function loadSaleReturns() {

    $.ajax({

        url: "{{ route('sales.returns.list') }}",

        type: "GET",

        dataType: 'json',

        success: function(response) {
            // alert(JSON.stringify(response));

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

        error: function(xhr) {
            // alert(JSON.stringify(xhr));

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

            dataType: 'json',

            data: {
                _token: "{{ csrf_token() }}"
            },

            success: function(response) {
                // alert(JSON.stringify(response));

                if (response.status) {

                    toastr.success(response.message);

                    loadSaleReturns();

                } else {

                    toastr.error(response.message);

                }

            },

            error: function(xhr) {
                // alert(JSON.stringify(xhr));

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