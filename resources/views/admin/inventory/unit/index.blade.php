@extends('admin.master')

@section('title')
{{ Session::get('companySettings')[0]['name'] ?? '' }} Units
@endsection

@section('content')

<div class="container-fluid">


<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Units</h4>

    <button type="button"
            class="btn btn-primary"
            onclick="openUnitModal()">
        Add Unit
    </button>
</div>

<div class="card">
    <div class="card-body">

        <table id="manageUnitTable"
               width="100%"
               class="table table-bordered table-hover">

            <thead>
                <tr>
                    <th width="6%">SL.</th>
                    <th>Unit Name</th>
                    <th>Slug</th>
                    <th width="10%">Status</th>
                    <th width="8%">Action</th>
                </tr>
            </thead>

        </table>

    </div>
</div>


</div>

<!-- Unit Modal -->

<div class="modal fade"
     id="unitModal"
     tabindex="-1"
     aria-labelledby="unitModalTitle"
     aria-hidden="true">


<div class="modal-dialog">

    <div class="modal-content">

        <div class="modal-header">

            <h5 class="modal-title" id="unitModalTitle">
                Add Unit
            </h5>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close">
            </button>

        </div>

        <form id="unitForm">

            @csrf

            <input type="hidden"
                   id="unit_id"
                   name="id">

            <div class="modal-body">

                <div class="mb-3">

                    <label for="unit_name" class="form-label">
                        Unit Name
                    </label>

                    <input type="text"
                           class="form-control"
                           id="unit_name"
                           name="name"
                           placeholder="Enter unit name">

                    <span class="text-danger error-text name_error"></span>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Status
                    </label>

                    <select class="form-select"
                            id="unit_status"
                            name="status">

                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>

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
                        id="unitSubmitBtn">
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

let unitTable;
let unitModal;


$(document).ready(function () {

    unitModal = new bootstrap.Modal(
        document.getElementById('unitModal')
    );


    unitTable = $('#manageUnitTable').DataTable({

        processing: true,

        ajax: "{{ route('admin.unit.list') }}",

        columns: [

            { data: 0 },

            { data: 1 },

            { data: 2 },

            { data: 3 },

            { data: 4 }

        ]

    });


    $('#unitForm').on('submit', function (e) {

        e.preventDefault();

        $('.error-text').text('');

        let form = $(this);

        let id = $('#unit_id').val();

        let url = id
            ? "{{ url('admin/unit/save') }}"
            : "{{ url('admin/unit/save') }}";


        $('#unitSubmitBtn')
            .prop('disabled', true)
            .text('Saving...');


        $.ajax({

            url: url,

            type: 'POST',

            data: form.serialize(),

            success: function (response) {

                if (response.status) {

                    unitModal.hide();

                    unitTable.ajax.reload(null, false);

                    toastr.success(response.message);

                    $('#unitForm')[0].reset();

                    $('#unit_id').val('');

                    $('#unit_status').val('Active');

                } else {

                    toastr.error(response.message);

                }

            },

            error: function (xhr) {

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

                $('#unitSubmitBtn')
                    .prop('disabled', false)
                    .text('Save');

            }

        });

    });

});


function openUnitModal()
{

    $('#unitForm')[0].reset();

    $('#unit_id').val('');

    $('#unit_status').val('Active');

    $('.error-text').text('');

    $('#unitModalTitle').text('Add Unit');

    $('#unitSubmitBtn').text('Save');

    unitModal.show();

}


function editUnit(id)
{

    $('.error-text').text('');

    $.ajax({

        url: "{{ url('admin/unit/edit') }}/" + id,

        type: 'GET',

        success: function (response) {

            if (response.status) {

                $('#unit_id').val(response.data.id);

                $('#unit_name').val(response.data.name);

                $('#unit_status').val(response.data.status);

                $('#unitModalTitle').text('Edit Unit');

                $('#unitSubmitBtn').text('Update');

                unitModal.show();

            } else {

                toastr.error(response.message);

            }

        },

        error: function () {

            toastr.error('Something went wrong.');

        }

    });

}


function deleteUnit(id)
{

    if (!confirm('Are you sure you want to delete this unit?')) {
        return;
    }


    $.ajax({

        url: "{{ url('admin/unit/delete') }}/" + id,

        type: 'POST',

        data: {
            _token: "{{ csrf_token() }}"
        },

        success: function (response) {

            if (response.status) {

                unitTable.ajax.reload(null, false);

                toastr.success(response.message);

            } else {

                toastr.error(response.message);

            }

        },

        error: function () {

            toastr.error('Something went wrong.');

        }

    });

}


function changeUnitStatus(id)
{

    $.ajax({

        url: "{{ url('admin/unit/status') }}/" + id,

        type: 'POST',

        data: {
            _token: "{{ csrf_token() }}"
        },

        success: function (response) {

            if (response.status) {

                unitTable.ajax.reload(null, false);

                toastr.success(response.message);

            } else {

                toastr.error(response.message);

            }

        },

        error: function () {

            toastr.error('Something went wrong.');

        }

    });

}

</script>

@endpush
