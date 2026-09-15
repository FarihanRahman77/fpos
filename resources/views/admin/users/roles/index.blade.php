@extends('admin.master')

@section('title')
    {{ Session::get('companySettings')[0]['name'] ?? '' }} Roles
@endsection

@section('content')
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">

            <h4 class="mb-0">
                Roles
            </h4>

            <button type="button" class="btn btn-primary" id="addRoleBtn">
                <i class="fa fa-plus"></i>
                Add Role
            </button>

        </div>

        <div class="card shadow-sm">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered table-hover" id="roleTable" width="100%">

                        <thead>
                            <tr>
                                <th width="5%">SL</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Permissions</th>
                                <th>Status</th>
                                <th width="150">Action</th>
                            </tr>
                        </thead>

                    </table>

                </div>

            </div>

        </div>

    </div>


    {{-- Role Modal --}}
    <div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalTitle" aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                {{-- Header --}}
                <div class="modal-header py-2">

                    <h5 class="modal-title" id="roleModalTitle">
                        Add Role
                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>

                </div>


                <form id="roleForm">

                    @csrf

                    <input type="hidden" id="role_id" name="role_id">


                    {{-- Body --}}
                    <div class="modal-body">

                        <div class="row">

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label for="role_name" class="form-label">
                                        Name <span class="text-danger">*</span>
                                    </label>

                                    <input type="text" class="form-control" id="role_name" name="name"
                                        placeholder="Enter role name" autocomplete="off">

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label for="role_status" class="form-label">
                                        Status
                                    </label>

                                    <select class="form-select" id="role_status" name="status">

                                        <option value="Active">
                                            Active
                                        </option>

                                        <option value="Inactive">
                                            Inactive
                                        </option>

                                    </select>

                                </div>

                            </div>

                        </div>


                        <div class="mb-0">

                            <label for="role_description" class="form-label">
                                Description
                            </label>

                            <textarea class="form-control" id="role_description" name="description" rows="3" placeholder="Enter description"></textarea>

                        </div>

                    </div>


                    {{-- Footer --}}
                    <div class="modal-footer py-2">

                        <button type="button" class="btn btn-light me-auto" data-bs-dismiss="modal">

                            <i class="fa-solid fa-xmark"></i>
                            Close

                        </button>

                        <button type="submit" class="btn btn-primary">

                            <i class="fa-solid fa-floppy-disk"></i>
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
    $(document).ready(function() {

        var roleTable = $('#roleTable').DataTable({

            processing: true,

            ajax: {
                url: "{{ route('admin.roles.list') }}",
                type: "GET"
            },

            columns: [

                {
                    data: null,
                    render: function(data, type, row, meta) {
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
                    data: 'permissions'
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


        // Add Role
        $('#addRoleBtn').click(function() {

            $('#roleForm')[0].reset();

            $('#role_id').val('');

            $('#roleModalTitle').text('Add Role');

            $('#role_status').val('Active');

            $('#roleModal').modal('show');

        });


        // Save / Update Role
        $('#roleForm').submit(function(e) {

            e.preventDefault();

            var id = $('#role_id').val();

            var url = id
                ? "{{ url('roles/update') }}/" + id
                : "{{ route('admin.roles.store') }}";

            $.ajax({

                url: url,

                type: "POST",

                data: $(this).serialize(),

                success: function(response) {

                    if (response.status) {

                        $('#roleModal').modal('hide');

                        roleTable.ajax.reload(null, false);

                        toastr.success(response.message);

                    } else {

                        toastr.error(response.message);

                    }

                },

                error: function(xhr) {

                    if (xhr.status === 422) {

                        $.each(xhr.responseJSON.errors, function(key, value) {

                            toastr.error(value[0]);

                        });

                    } else {

                        toastr.error(
                            xhr.responseJSON?.message ||
                            'Something went wrong.'
                        );

                    }

                }

            });

        });


        // Edit Role
        $(document).on('click', '.editRole', function() {

            var id = $(this).data('id');

            $.ajax({

                url: "{{ url('roles/edit') }}/" + id,

                type: "GET",

                success: function(response) {

                    if (response.status) {

                        var role = response.data;

                        $('#role_id').val(role.id);

                        $('#role_name').val(role.name);

                        $('#role_description').val(role.description);

                        $('#role_status').val(role.status);

                        $('#roleModalTitle').text('Edit Role');

                        $('#roleModal').modal('show');

                    } else {

                        toastr.error(response.message);

                    }

                },

                error: function(xhr) {

                    toastr.error(
                        xhr.responseJSON?.message ||
                        'Unable to load role.'
                    );

                }

            });

        });


        // Delete Role
        $(document).on('click', '.deleteRole', function() {

            var id = $(this).data('id');

            Swal.fire({

                title: 'Are you sure?',

                text: 'This role will be deleted.',

                icon: 'warning',

                showCancelButton: true,

                confirmButtonText: 'Yes, Delete'

            }).then(function(result) {

                if (result.isConfirmed) {

                    $.ajax({

                        url: "{{ url('roles/delete') }}/" + id,

                        type: "POST",

                        data: {
                            _token: "{{ csrf_token() }}"
                        },

                        success: function(response) {

                            if (response.status) {

                                roleTable.ajax.reload(null, false);

                                toastr.success(response.message);

                            } else {

                                toastr.error(response.message);

                            }

                        },

                        error: function(xhr) {

                            toastr.error(
                                xhr.responseJSON?.message ||
                                'Unable to delete role.'
                            );

                        }

                    });

                }

            });

        });

    });
</script>
@endpush


