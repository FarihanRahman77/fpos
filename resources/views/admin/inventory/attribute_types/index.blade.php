@extends('admin.master')

@section('title')
    {{ Session::get('companySettings')[0]['name'] ?? '' }} Attribute Categories
@endsection

@section('content')

<style>

    .modal-header {
        border-bottom: 1px solid #dee2e6;
    }

    .modal-footer {
        border-top: 1px solid #dee2e6;
        justify-content: space-between;
    }

    .modal-title {
        font-weight: 600;
    }

    .required {
        color: red;
    }

    #manageAttributeTypeTable td {
        vertical-align: middle;
    }

</style>


<div class="container-fluid">


    <!-- PAGE HEADER -->

    <div class="row mb-3">

        <div class="col-md-6">

            <h4 class="mb-0">
                Attribute Categories
            </h4>

        </div>


        <div class="col-md-6 text-end">

            <button type="button"
                    class="btn btn-primary"
                    id="addAttributeTypeBtn">

                <i class="fas fa-plus"></i>

                Add Attribute Category

            </button>

        </div>

    </div>



    <!-- TABLE -->

    <div class="card">

        <div class="card-body">

            <div class="table-responsive">

                <table id="manageAttributeTypeTable"
                       class="table table-bordered"
                       width="100%">

                    <thead>

                        <tr>

                            <th width="5%"
                                class="text-center">
                                SL#
                            </th>

                            <th width="25%"
                                class="text-center">
                                Name
                            </th>

                            <th width="25%"
                                class="text-center">
                                Slug
                            </th>

                            <th width="15%"
                                class="text-center">
                                Attributes
                            </th>

                            <th width="15%"
                                class="text-center">
                                Created Date
                            </th>

                            <th width="7%"
                                class="text-center">
                                Status
                            </th>

                            <th width="8%"
                                class="text-center">
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody></tbody>

                </table>

            </div>

        </div>

    </div>

</div>



<!-- ========================================================= -->
<!-- ADD / EDIT MODAL -->
<!-- ========================================================= -->

<div class="modal fade"
     id="attributeTypeModal"
     tabindex="-1"
     aria-labelledby="attributeTypeModalTitle"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">


            <form id="attributeTypeForm">

                @csrf


                <input type="hidden"
                       name="attribute_type_id"
                       id="attribute_type_id">


                <!-- HEADER -->

                <div class="modal-header">

                    <h5 class="modal-title"
                        id="attributeTypeModalTitle">

                        Add Attribute Category

                    </h5>


                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close">
                    </button>

                </div>


                <!-- BODY -->

                <div class="modal-body">

                    <div class="mb-3">

                        <label for="attribute_type_name"
                               class="form-label">

                            Category Name

                            <span class="required">*</span>

                        </label>


                        <input type="text"
                               class="form-control border-dark"
                               name="name"
                               id="attribute_type_name"
                               autocomplete="off"
                               required>

                    </div>


                    <div class="mb-0">

                        <label for="attribute_type_slug"
                               class="form-label">

                            Slug

                        </label>


                        <input type="text"
                               class="form-control border-dark"
                               id="attribute_type_slug"
                               readonly>

                    </div>

                </div>


                <!-- FOOTER -->

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">

                        Close

                    </button>


                    <button type="submit"
                            class="btn btn-primary"
                            id="saveAttributeTypeBtn">

                        Save

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>



<!-- ========================================================= -->
<!-- DELETE MODAL -->
<!-- ========================================================= -->

<div class="modal fade"
     id="deleteAttributeTypeModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-sm">

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title">

                    Delete Attribute Category

                </h5>


                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>


            <div class="modal-body">

                Are you sure you want to delete this attribute category?

                <input type="hidden"
                       id="delete_attribute_type_id">

            </div>


            <div class="modal-footer">

                <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                    Cancel

                </button>


                <button type="button"
                        class="btn btn-danger"
                        id="confirmDeleteAttributeType">

                    Delete

                </button>

            </div>

        </div>

    </div>

</div>

@endsection



@push('scripts')

<script>

var attributeTypeTable;


/*
|--------------------------------------------------------------------------
| DOCUMENT READY
|--------------------------------------------------------------------------
*/

$(document).ready(function() {

    attributeTypeTable =$('#manageAttributeTypeTable').DataTable({

            ajax: {

                url: "{{ route('admin.attribute_types.list') }}",

                type: "GET",

                dataSrc: function(json) {
                    //alert(JSON.stringify(json));
                    if (json.status === false) {

                        toastr.error(
                            json.message ||
                            'Unable to load attribute categories.'
                        );

                        return [];

                    }

                    return json.data || [];

                },

                error: function(xhr) {

                    //alert(JSON.stringify(xhr));

                }

            },


            processing: true,

            pageLength: 25,

            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],


            order: [
                [0, 'asc']
            ],


            columns: [

                {
                    data: null,

                    className: 'text-center',

                    render: function(
                        data,
                        type,
                        row,
                        meta
                    ) {

                        return meta.row + 1;

                    }

                },


                {
                    data: 1
                },


                {
                    data: 2
                },


                {
                    data: 3,

                    className: 'text-center'
                },


                {
                    data: 4,

                    className: 'text-center'
                },


                {
                    data: 5,

                    className: 'text-center',

                    orderable: false,

                    searchable: false
                },


                {
                    data: 6,

                    className: 'text-center',

                    orderable: false,

                    searchable: false
                }

            ],


            language: {

                processing: 'Loading...',

                emptyTable:
                    'No attribute categories found.'

            }

        });



    /*
    |--------------------------------------------------------------------------
    | ADD
    |--------------------------------------------------------------------------
    */

    $('#addAttributeTypeBtn').on(
        'click',
        function() {

            resetAttributeTypeForm();

            $('#attributeTypeModalTitle')
                .text('Add Attribute Category');

            $('#saveAttributeTypeBtn')
                .text('Save');

            $('#attributeTypeModal')
                .modal('show');

        }
    );



    /*
    |--------------------------------------------------------------------------
    | NAME -> SLUG PREVIEW
    |--------------------------------------------------------------------------
    */

    $('#attribute_type_name').on(
        'keyup',
        function() {

            let value = $(this).val();

            let slug = value
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');


            $('#attribute_type_slug')
                .val(slug);

        }
    );



    /*
    |--------------------------------------------------------------------------
    | SAVE / UPDATE
    |--------------------------------------------------------------------------
    */

    $('#attributeTypeForm').on(
        'submit',
        function(e) {

            e.preventDefault();


            let id =
                $('#attribute_type_id').val();


            let url;


            if (id) {

                url =
                    "{{ url('admin/attribute_types/update') }}/" +id;

            } else {

                url =
                    "{{ route('admin.attribute_types.store') }}";

            }


            let formData =
                new FormData(this);


            $('#saveAttributeTypeBtn')
                .prop('disabled', true)
                .text('Saving...');


            $.ajax({

                url: url,

                type: 'POST',

                data: formData,

                processData: false,

                contentType: false,


                success: function(response) {

                    if (response.status) {

                        $('#attributeTypeModal')
                            .modal('hide');


                        toastr.success(
                            response.message
                        );


                        attributeTypeTable
                            .ajax
                            .reload(null, false);

                    } else {

                        toastr.error(
                            response.message ||
                            'Unable to save.'
                        );

                    }

                },


                error: function(xhr) {

                    if (xhr.status === 422) {

                        let errors =
                            xhr.responseJSON.errors;


                        $.each(
                            errors,
                            function(
                                key,
                                value
                            ) {

                                toastr.error(
                                    value[0]
                                );

                            }
                        );

                    } else {

                        toastr.error(
                            xhr.responseJSON?.message ||
                            'Something went wrong.'
                        );

                    }

                },


                complete: function() {

                    $('#saveAttributeTypeBtn')
                        .prop('disabled', false)
                        .text(
                            id
                            ? 'Update'
                            : 'Save'
                        );

                }

            });

        }
    );



    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'click',
        '.editAttributeTypeBtn',
        function() {

            let id =
                $(this).data('id');


            $.ajax({

                url:
                    "{{ url('admin/attribute_types/edit') }}/" +
                    id,

                type: 'GET',


                success: function(response) {
                    //alert(JSON.stringify(response));
                    if (!response.status) {

                        toastr.error(
                            response.message
                        );

                        return;

                    }


                    resetAttributeTypeForm();


                    let data =
                        response.data;


                    $('#attribute_type_id')
                        .val(data.id);


                    $('#attribute_type_name')
                        .val(data.name)
                        .trigger('keyup');


                    $('#attributeTypeModalTitle')
                        .text(
                            'Edit Attribute Category'
                        );


                    $('#saveAttributeTypeBtn')
                        .text('Update');


                    $('#attributeTypeModal')
                        .modal('show');

                },


                error: function(xhr) {
                    //alert(JSON.stringify(xhr));
                    toastr.error(
                        'Unable to load attribute category.'
                    );

                }

            });

        }
    );



    /*
    |--------------------------------------------------------------------------
    | DELETE BUTTON
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'click',
        '.deleteAttributeTypeBtn',
        function() {

            let id =
                $(this).data('id');


            $('#delete_attribute_type_id')
                .val(id);


            $('#deleteAttributeTypeModal')
                .modal('show');

        }
    );



    /*
    |--------------------------------------------------------------------------
    | CONFIRM DELETE
    |--------------------------------------------------------------------------
    */

    $('#confirmDeleteAttributeType').on(
        'click',
        function() {

            let button = $(this);

            let id =
                $('#delete_attribute_type_id')
                .val();


            if (!id) {

                return;

            }


            button
                .prop('disabled', true)
                .text('Deleting...');


            $.ajax({

                url:
                    "{{ url('admin/attribute_types/delete') }}/" +
                    id,

                type: 'POST',

                data: {

                    _token:
                        "{{ csrf_token() }}"

                },


                success: function(response) {

                    if (response.status) {

                        $('#deleteAttributeTypeModal')
                            .modal('hide');


                        toastr.success(
                            response.message
                        );


                        attributeTypeTable
                            .ajax
                            .reload(null, false);

                    } else {

                        toastr.error(
                            response.message
                        );

                    }

                },


                error: function(xhr) {

                    toastr.error(
                        xhr.responseJSON?.message ||
                        'Unable to delete attribute category.'
                    );

                },


                complete: function() {

                    button
                        .prop('disabled', false)
                        .text('Delete');

                }

            });

        }
    );



    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'click',
        '.attributeTypeStatusBtn',
        function() {

            let id =
                $(this).data('id');


            $.ajax({

                url:
                    "{{ url('attribute-types/status') }}/" +
                    id,

                type: 'POST',

                data: {

                    _token:
                        "{{ csrf_token() }}"

                },


                success: function(response) {

                    if (response.status) {

                        toastr.success(
                            response.message
                        );


                        attributeTypeTable
                            .ajax
                            .reload(null, false);

                    } else {

                        toastr.error(
                            response.message
                        );

                    }

                },


                error: function() {

                    toastr.error(
                        'Unable to change status.'
                    );

                }

            });

        }
    );



    /*
    |--------------------------------------------------------------------------
    | RESET MODAL
    |--------------------------------------------------------------------------
    */

    $('#attributeTypeModal').on(
        'hidden.bs.modal',
        function() {

            resetAttributeTypeForm();

        }
    );

});



/*
|--------------------------------------------------------------------------
| RESET FORM
|--------------------------------------------------------------------------
*/

function resetAttributeTypeForm()
{

    $('#attributeTypeForm')[0].reset();


    $('#attribute_type_id')
        .val('');


    $('#attribute_type_slug')
        .val('');


    $('#saveAttributeTypeBtn')
        .prop('disabled', false)
        .text('Save');

}

</script>

@endpush
