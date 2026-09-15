@extends('admin.master')

@section('title')
    {{ Session::get('companySettings')[0]['name'] ?? '' }} Permissions
@endsection

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h4 class="mb-0">
            Permissions
        </h4>

        <button type="button"
                class="btn btn-primary"
                id="addPermissionBtn">

            <i class="fa fa-plus"></i>
            Add Permission

        </button>

    </div>


    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover"
                       id="permissionTable"
                       width="100%">

                    <thead>

                        <tr>

                            <th width="5%">SL</th>

                            <th>Name</th>

                            <th>Slug</th>

                            <th>Module</th>

                            <th>Status</th>

                            <th width="120">Action</th>

                        </tr>

                    </thead>

                </table>

            </div>

        </div>

    </div>

</div>



<div class="modal fade"
     id="permissionModal"
     tabindex="-1"
     aria-labelledby="permissionModalTitle"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header py-2">

                <h5 class="modal-title" id="permissionModalTitle">
                    Add Permission
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>

            </div>


            <form id="permissionForm">

                @csrf

                <input type="hidden"
                       id="permission_id"
                       name="permission_id">


                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-6">

                            <div class="mb-3">

                                <label for="permission_name" class="form-label">
                                    Name <span class="text-danger">*</span>
                                </label>

                                <input type="text"
                                       class="form-control"
                                       name="name"
                                       id="permission_name"
                                       placeholder="Enter permission name"
                                       autocomplete="off">

                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="mb-3">

                                <label for="permission_module" class="form-label">
                                    Module
                                </label>

                                <input type="text"
                                       class="form-control"
                                       name="module"
                                       id="permission_module"
                                       placeholder="e.g. Users"
                                       autocomplete="off">

                            </div>

                        </div>

                    </div>


                    <div class="mb-3">

                        <label for="permission_description" class="form-label">
                            Description
                        </label>

                        <textarea class="form-control"
                                  name="description"
                                  id="permission_description"
                                  rows="3"
                                  placeholder="Enter description"></textarea>

                    </div>


                    <div class="mb-0">

                        <label for="permission_status" class="form-label">
                            Status
                        </label>

                        <select class="form-select"
                                name="status"
                                id="permission_status">

                            <option value="Active">
                                Active
                            </option>

                            <option value="Inactive">
                                Inactive
                            </option>

                        </select>

                    </div>

                </div>


                <div class="modal-footer py-2">

                    <button type="button"
                            class="btn btn-light me-auto"
                            data-bs-dismiss="modal">

                        Close

                    </button>

                    <button type="submit"
                            class="btn btn-primary">

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

    var permissionTable = $('#permissionTable').DataTable({

        processing: true,

        ajax: {
            url: "{{ route('admin.permissions.list') }}",
            type: "GET"
        },

        columns: [

            {
                data: null,

                render: function (data, type, row, meta) {

                    return meta.row + 1;

                }
            },

            {
                data: 'name'
            },

            {
                data: 'slug'
            },

            {
                data: 'module'
            },

            {
                data: 'status'
            },

            {
                data: 'actions',
                orderable: false,
                searchable: false
            }

        ]

    });


    $('#addPermissionBtn').click(function () {

        $('#permissionForm')[0].reset();

        $('#permission_id').val('');

        $('#permission_status').val('Active');

        $('#permissionModalTitle')
            .text('Add Permission');

        $('#permissionModal').modal('show');

    });


    $('#permissionForm').submit(function (e) {

        e.preventDefault();

        var id = $('#permission_id').val();

        var url = id
            ? "{{ url('permissions/update') }}/" + id
            : "{{ route('admin.permissions.store') }}";

        $.ajax({

            url: url,

            type: "POST",

            data: $(this).serialize(),

            success: function (response) {

                if (response.status) {

                    $('#permissionModal').modal('hide');

                    permissionTable.ajax.reload(null, false);

                    toastr.success(
                        response.message
                    );

                }

            },

            error: function (xhr) {

                if (xhr.status === 422) {

                    $.each(
                        xhr.responseJSON.errors,
                        function (key, value) {

                            toastr.error(value[0]);

                        }
                    );

                } else {

                    toastr.error(
                        xhr.responseJSON?.message ||
                        'Something went wrong.'
                    );

                }

            }

        });

    });


    $(document).on(
        'click',
        '.editPermission',
        function () {

            var id = $(this).data('id');

            $.ajax({

                url: "{{ url('permissions/edit') }}/" + id,

                type: "GET",

                success: function (response) {

                    if (response.status) {

                        var permission = response.data;

                        $('#permission_id')
                            .val(permission.id);

                        $('#permission_name')
                            .val(permission.name);

                        $('#permission_slug')
                            .val(permission.slug);

                        $('#permission_module')
                            .val(permission.module);

                        $('#permission_description')
                            .val(permission.description);

                        $('#permission_status')
                            .val(permission.status);

                        $('#permissionModalTitle')
                            .text('Edit Permission');

                        $('#permissionModal')
                            .modal('show');

                    }

                }

            });

        }
    );


    $(document).on(
        'click',
        '.deletePermission',
        function () {

            var id = $(this).data('id');

            Swal.fire({

                title: 'Are you sure?',

                text: 'This permission will be deleted.',

                icon: 'warning',

                showCancelButton: true,

                confirmButtonText: 'Yes, Delete'

            }).then(function (result) {

                if (result.isConfirmed) {

                    $.ajax({

                        url:
                            "{{ url('permissions/delete') }}/"
                            + id,

                        type: "POST",

                        data: {
                            _token:
                                "{{ csrf_token() }}"
                        },

                        success: function (response) {

                            if (response.status) {

                                permissionTable.ajax
                                    .reload(null, false);

                                toastr.success(
                                    response.message
                                );

                            }

                        }

                    });

                }

            });

        }
    );

});

</script>

@endpush
