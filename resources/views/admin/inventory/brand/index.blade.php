@extends('admin.master')

@section('title')
{{ Session::get('companySettings')[0]['name'] ?? '' }} Brands
@endsection

@section('content')

<div class="container-fluid">


<div class="d-flex justify-content-between align-items-center mb-3">

    <h4>Brands</h4>

    <button type="button"
            class="btn btn-primary"
            onclick="openBrandModal()">
        Add Brand
    </button>

</div>


<div class="card">

    <div class="card-body">

        <table id="manageBrandTable"
               width="100%"
               class="table table-bordered table-hover">

            <thead>

                <tr>
                    <th width="6%">SL.</th>
                    <th>Brand Name</th>
                    <th>Slug</th>
                    <th width="10%">Status</th>
                    <th width="8%">Action</th>
                </tr>

            </thead>

        </table>

    </div>

</div>


</div>

<!-- Brand Modal -->

<div class="modal fade"
     id="brandModal"
     tabindex="-1"
     aria-labelledby="brandModalTitle"
     aria-hidden="true">


<div class="modal-dialog">

    <div class="modal-content">

        <div class="modal-header">

            <h5 class="modal-title"
                id="brandModalTitle">
                Add Brand
            </h5>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close">
            </button>

        </div>


        <form id="brandForm">

            @csrf

            <input type="hidden"
                   id="brand_id"
                   name="id">


            <div class="modal-body">

                <div class="mb-3">

                    <label for="brand_name"
                           class="form-label">
                        Brand Name
                    </label>

                    <input type="text"
                           class="form-control"
                           id="brand_name"
                           name="name"
                           placeholder="Enter brand name">

                    <span class="text-danger error-text name_error"></span>

                </div>


                <div class="mb-3">

                    <label for="brand_status"
                           class="form-label">
                        Status
                    </label>

                    <select class="form-select"
                            id="brand_status"
                            name="status">

                        <option value="Active">
                            Active
                        </option>

                        <option value="Inactive">
                            Inactive
                        </option>

                    </select>

                    <span class="text-danger error-text status_error"></span>

                </div>

            </div>


            <div class="modal-footer">

                <button type="button"
                        class="btn btn-secondary me-auto"
                        data-bs-dismiss="modal">
                    Close
                </button>

                <button type="submit"
                        class="btn btn-primary"
                        id="brandSubmitBtn">
                    Save
                </button>

            </div>

        </form>

    </div>

</div>


</div>

@endsection

@push('scripts')

<script>

let brandTable;
let brandModal;


$(document).ready(function () {

    brandModal = new bootstrap.Modal(
        document.getElementById('brandModal')
    );


    brandTable = $('#manageBrandTable').DataTable({

        processing: true,

        ajax: "{{ route('admin.brand.list') }}",

        columns: [

            { data: 0 },

            { data: 1 },

            { data: 2 },

            { data: 3 },

            { data: 4 }

        ]

    });


    $('#brandForm').on('submit', function (e) {

        e.preventDefault();

        $('.error-text').text('');

        let form = $(this);


        $('#brandSubmitBtn')
            .prop('disabled', true)
            .text('Saving...');


        $.ajax({

            url: "{{ route('admin.brand.save') }}",

            type: 'POST',

            data: form.serialize(),

            success: function (response) {
               // alert(JSON.stringify(response));
                if (response.status) {

                    brandModal.hide();

                    brandTable.ajax.reload(null, false);

                    toastr.success(response.message);

                    $('#brandForm')[0].reset();

                    $('#brand_id').val('');

                    $('#brand_status').val('Active');

                } else {

                    toastr.error(response.message);

                }

            },

            error: function (xhr) {
                //alert(JSON.stringify(xhr));
                if (xhr.status === 422) {

                    let errors = xhr.responseJSON.errors;

                    $.each(errors, function (key, value) {

                        $('.' + key + '_error')
                            .text(value[0]);

                    });

                } else {

                    toastr.error('Something went wrong.');

                }

            },

            complete: function () {

                $('#brandSubmitBtn')
                    .prop('disabled', false)
                    .text('Save');

            }

        });

    });

});


function openBrandModal()
{

    $('#brandForm')[0].reset();

    $('#brand_id').val('');

    $('#brand_status').val('Active');

    $('.error-text').text('');

    $('#brandModalTitle').text('Add Brand');

    $('#brandSubmitBtn').text('Save');

    brandModal.show();

}


function editBrand(id)
{

    $('.error-text').text('');

    $.ajax({

        url: "{{ url('admin/brand/edit') }}/" + id,

        type: 'GET',

        success: function (response) {

            if (response.status) {

                $('#brand_id').val(response.data.id);

                $('#brand_name').val(response.data.name);

                $('#brand_status').val(response.data.status);

                $('#brandModalTitle').text('Edit Brand');

                $('#brandSubmitBtn').text('Update');

                brandModal.show();

            } else {

                toastr.error(response.message);

            }

        },

        error: function () {

            toastr.error('Something went wrong.');

        }

    });

}


function deleteBrand(id)
{

    if (!confirm('Are you sure you want to delete this brand?')) {
        return;
    }


    $.ajax({

        url: "{{ url('admin/brand/delete') }}/" + id,

        type: 'POST',

        data: {
            _token: "{{ csrf_token() }}"
        },

        success: function (response) {
            alert(JSON.stringify(response));
            if (response.status) {
                brandTable.ajax.reload(null, false);
                toastr.success(response.message);
            } else {
                toastr.error(response.message);
            }
        },
        error: function (xhr) {
            alert(JSON.stringify(xhr));
            toastr.error('Something went wrong.');

        }

    });

}


function changeBrandStatus(id)
{

    $.ajax({

        url: "{{ url('admin/brand/status') }}/" + id,

        type: 'POST',

        data: {
            _token: "{{ csrf_token() }}"
        },

        success: function (response) {
            //alert(JSON.stringify(response));
            if (response.status) {

                brandTable.ajax.reload(null, false);

                toastr.success(response.message);

            } else {

                toastr.error(response.message);

            }

        },

        error: function (xhr) {
           // alert(JSON.stringify(xhr));
            toastr.error('Something went wrong.');

        }

    });

}

</script>

@endpush
