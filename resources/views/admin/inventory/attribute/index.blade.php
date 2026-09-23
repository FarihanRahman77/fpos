@extends('admin.master')

@section('title')
{{ Session::get('companySettings')[0]['name'] ?? '' }} Attributes
@endsection

@section('content')

<div class="container-fluid">


<div class="d-flex justify-content-between align-items-center mb-3">

    <h4>Attributes</h4>

    <button type="button"
            class="btn btn-primary"
            onclick="openAttributeModal()">
        Add Attribute
    </button>

</div>


<div class="card">

    <div class="card-body">

        <table id="manageAttributeTable"
               width="100%"
               class="table table-bordered table-hover">

            <thead>

                <tr>
                    <th width="5%">SL.</th>
                    <th width="30%">Attribute Name</th>
                    <th  width="30%">Slug</th>
                    <th  width="20%">Attribute Type</th>
                    <th width="10%">Status</th>
                    <th width="5%">Action</th>
                </tr>

            </thead>

        </table>

    </div>

</div>


</div>

<!-- Attribute Modal -->

<div class="modal fade"
     id="attributeModal"
     tabindex="-1"
     aria-labelledby="attributeModalTitle"
     aria-hidden="true">


<div class="modal-dialog">

    <div class="modal-content">

        <div class="modal-header">

            <h5 class="modal-title"
                id="attributeModalTitle">
                Add Attribute
            </h5>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close">
            </button>

        </div>


        <form id="attributeForm">

            @csrf

            <input type="hidden"
                   id="attribute_id"
                   name="id">


            <div class="modal-body">

                <div class="mb-3">

                    <label for="attribute_name"
                           class="form-label">
                        Attribute Name
                    </label>

                    <input type="text"
                           class="form-control  border-dark"
                           id="attribute_name"
                           name="name"
                           placeholder="Enter attribute name">

                    <span class="text-danger error-text name_error"></span>

                </div>

                <div class="mb-3">

                    <label for="attribute_name"
                           class="form-label">
                        Attribute Type
                    </label>

                    <select type="text"
                           class="form-control border-dark"
                           id="attribute_type_id"
                           name="attribute_type_id">
                        <option value="">Select Attribute Types</option>
                        @foreach($attributeTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>

                    <span class="text-danger error-text name_error"></span>

                </div>


                <div class="mb-3">

                    <label for="attribute_status"
                           class="form-label">
                        Status
                    </label>

                    <select class="form-select  border-dark"
                            id="attribute_status"
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
                        id="attributeSubmitBtn">
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

$(document).ready(function () {
    $('.select2').select2({
        width: '100%',
        dropdownParent: $('#productModal')
    });
});


let attributeTable;
let attributeModal;


$(document).ready(function () {


    attributeModal = new bootstrap.Modal(
        document.getElementById('attributeModal')
    );


    attributeTable = $('#manageAttributeTable').DataTable({

        processing: true,

        ajax: "{{ route('admin.attribute.list') }}"

    });


    $('#attributeForm').on('submit', function (e) {

        e.preventDefault();

        $('.error-text').text('');

        let form = $(this);


        $('#attributeSubmitBtn')
            .prop('disabled', true)
            .text('Saving...');


        $.ajax({

            url: "{{ url('admin/attribute/save') }}",

            type: 'POST',

            data: form.serialize(),

            success: function (response) {

                if (response.status) {

                    attributeModal.hide();

                    attributeTable.ajax.reload(null, false);

                    toastr.success(response.message);

                    $('#attributeForm')[0].reset();

                    $('#attribute_id').val('');

                    $('#attribute_status').val('Active');

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

                $('#attributeSubmitBtn')
                    .prop('disabled', false)
                    .text('Save');

            }

        });

    });

});


function openAttributeModal()
{

    $('#attributeForm')[0].reset();

    $('#attribute_id').val('');

    $('#attribute_status').val('Active');

    $('.error-text').text('');

    $('#attributeModalTitle').text('Add Attribute');

    $('#attributeSubmitBtn').text('Save');

    attributeModal.show();

}


function editAttribute(id)
{

    $('.error-text').text('');

    $.ajax({

        url: "{{ url('admin/attribute/edit') }}/" + id,

        type: 'GET',

        success: function (response) {

            if (response.status) {

                $('#attribute_id').val(response.data.id);

                $('#attribute_name').val(response.data.name);
                
                $('#attribute_type_id').val(response.data.attribute_type_id);

                $('#attribute_status').val(response.data.status);

                $('#attributeModalTitle').text('Edit Attribute');

                $('#attributeSubmitBtn').text('Update');

                attributeModal.show();

            } else {

                toastr.error(response.message);

            }

        },

        error: function () {

            toastr.error('Something went wrong.');

        }

    });

}


function deleteAttribute(id)
{

    if (!confirm('Are you sure you want to delete this attribute?')) {
        return;
    }


    $.ajax({

        url: "{{ url('admin/attribute/delete') }}/" + id,

        type: 'POST',

        data: {
            _token: "{{ csrf_token() }}"
        },

        success: function (response) {

            if (response.status) {

                attributeTable.ajax.reload(null, false);

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


function changeAttributeStatus(id)
{

    $.ajax({

        url: "{{ url('admin/attribute/status') }}/" + id,

        type: 'POST',

        data: {
            _token: "{{ csrf_token() }}"
        },

        success: function (response) {

            if (response.status) {

                attributeTable.ajax.reload(null, false);

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
