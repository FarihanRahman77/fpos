@extends('admin.master')

@section('title', 'Users')

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h4 class="mb-1">Users</h4>
            <p class="text-muted mb-0">
                Manage system users
            </p>
        </div>

        <button type="button"
                class="btn btn-primary"
                id="addUserBtn">

            <i class="bi bi-plus-circle"></i>
            Add User

        </button>

    </div>


    <div class="card shadow-sm border-0">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle"
                       id="usersTable">

                    <thead>
                        <tr>

                            <th width="60">#</th>

                            <th width="80">Image</th>

                            <th>Name</th>

                            <th>Email</th>

                            <th>Designation</th>

                            <th>Role</th>

                            <th>Status</th>

                            <th width="150">Action</th>

                        </tr>
                    </thead>

                    <tbody>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>



<!-- =========================================================
     USER MODAL
========================================================= -->

<div class="modal fade"
     id="userModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title"
                    id="userModalTitle">

                    Add User

                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>


            <form id="userForm"
                  enctype="multipart/form-data">

                @csrf

                <input type="hidden"
                       id="user_id"
                       name="user_id">


                <div class="modal-body">

                    <div id="validationErrors"
                         class="alert alert-danger d-none">

                    </div>


                    <div class="row">


                        <!-- IMAGE -->

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                User Image
                            </label>


                            <div class="text-center">

                                <img src=""
                                     id="imagePreview"
                                     class="img-thumbnail mb-3 d-none"
                                     style="width:150px;height:150px;object-fit:cover;">


                                <div id="noImagePreview"
                                     class="border rounded d-flex align-items-center justify-content-center mx-auto mb-3"
                                     style="width:150px;height:150px;">

                                    <i class="bi bi-person"
                                       style="font-size:60px;color:#aaa;">
                                    </i>

                                </div>


                                <input type="file"
                                       class="form-control"
                                       name="image"
                                       id="image"
                                       accept="image/*">

                                <small class="text-muted">
                                    JPG, JPEG, PNG, WEBP. Max 2MB.
                                </small>

                            </div>

                        </div>


                        <!-- FORM -->

                        <div class="col-md-8">

                            <div class="row">


                                <!-- NAME -->

                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Name
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="text"
                                           name="name"
                                           id="name"
                                           class="form-control"
                                           placeholder="Enter name">

                                </div>


                                <!-- EMAIL -->

                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Email
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="email"
                                           name="email"
                                           id="email"
                                           class="form-control"
                                           placeholder="Enter email">

                                </div>


                                <!-- PASSWORD -->

                                <div class="col-md-6 mb-3">

                                    <label class="form-label">

                                        Password

                                        <span class="text-danger"
                                              id="passwordRequired">
                                            *
                                        </span>

                                    </label>

                                    <input type="password"
                                           name="password"
                                           id="password"
                                           class="form-control"
                                           placeholder="Enter password">

                                    <small class="text-muted"
                                           id="passwordHelp">

                                        Required when creating user.

                                    </small>

                                </div>


                                <!-- ROLE -->

                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Role
                                        <span class="text-danger">*</span>
                                    </label>

                                    <select name="role_id"
                                            id="role_id"
                                            class="form-select">

                                        <option value="">
                                            Select Role
                                        </option>

                                        @foreach($roles as $role)

                                            <option value="{{ $role->id }}">
                                                {{ $role->name }}
                                            </option>

                                        @endforeach

                                    </select>

                                </div>


                                <!-- DESIGNATION -->

                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Designation
                                    </label>

                                    <input type="text"
                                           name="designation"
                                           id="designation"
                                           class="form-control"
                                           placeholder="e.g. Manager">

                                </div>


                                <!-- STATUS -->

                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Status
                                    </label>

                                    <select name="status"
                                            id="status"
                                            class="form-select">

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

                    </div>

                </div>


                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">

                        Close

                    </button>

                    <button type="submit"
                            class="btn btn-primary"
                            id="saveUserBtn">

                        <i class="bi bi-check-circle"></i>
                        Save User

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


    /*
    |--------------------------------------------------------------------------
    | Load Users
    |--------------------------------------------------------------------------
    */

    loadUsers();


    function loadUsers()
    {

        $.ajax({

            url: "{{ route('admin.users.list') }}",

            type: "GET",

            dataType: "json",

            success: function(response)
            {

                let html = '';

                if(response.status && response.data.length > 0)
                {

                    $.each(response.data, function(index, user)
                    {

                        let image = '';

                        if(user.image)
                        {

                            image = `
                                <img src="{{ asset('') }}${user.image}"
                                     style="width:45px;height:45px;object-fit:cover;"
                                     class="rounded-circle">
                            `;

                        }
                        else
                        {

                            image = `
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                     style="width:45px;height:45px;">

                                    <i class="bi bi-person text-muted"></i>

                                </div>
                            `;

                        }


                        let status = '';

                        if(user.status === 'Active')
                        {

                            status = `
                                <span class="badge bg-success">
                                    Active
                                </span>
                            `;

                        }
                        else
                        {

                            status = `
                                <span class="badge bg-danger">
                                    Inactive
                                </span>
                            `;

                        }


                        html += `

                            <tr>

                                <td>
                                    ${index + 1}
                                </td>

                                <td>
                                    ${image}
                                </td>

                                <td>
                                    <strong>
                                        ${user.name ?? ''}
                                    </strong>
                                </td>

                                <td>
                                    ${user.email ?? ''}
                                </td>

                                <td>
                                    ${user.designation ?? '-'}
                                </td>

                                <td>
                                    ${user.role_name ?? '-'}
                                </td>

                                <td>
                                    ${status}
                                </td>

                                <td>

                                    <button type="button"
                                            class="btn btn-sm btn-info editUser"
                                            data-id="${user.id}"
                                            title="Edit">

                                        <i class="bi bi-pencil"></i>

                                    </button>


                                    <button type="button"
                                            class="btn btn-sm btn-danger deleteUser"
                                            data-id="${user.id}"
                                            title="Delete">

                                        <i class="bi bi-trash"></i>

                                    </button>

                                </td>

                            </tr>

                        `;

                    });

                }
                else
                {

                    html = `

                        <tr>

                            <td colspan="8"
                                class="text-center text-muted py-4">

                                No users found.

                            </td>

                        </tr>

                    `;

                }


                $('#usersTable tbody').html(html);

            },

            error: function()
            {

                toastr.error('Unable to load users.');

            }

        });

    }



    /*
    |--------------------------------------------------------------------------
    | Add User
    |--------------------------------------------------------------------------
    */

    $('#addUserBtn').on('click', function()
    {
      
        resetForm();

        $('#userModalTitle').text('Add User');

        $('#saveUserBtn').html(`
            <i class="bi bi-check-circle"></i>
            Save User
        `);

        $('#passwordRequired').removeClass('d-none');

        $('#passwordHelp').text(
            'Required when creating user.'
        );


        let modal = new bootstrap.Modal(
            document.getElementById('userModal')
        );

        modal.show();

    });



    /*
    |--------------------------------------------------------------------------
    | Image Preview
    |--------------------------------------------------------------------------
    */

    $('#image').on('change', function(event)
    {

        let file = event.target.files[0];

        if(!file)
        {
            return;
        }


        let reader = new FileReader();

        reader.onload = function(e)
        {

            $('#imagePreview')
                .attr('src', e.target.result)
                .removeClass('d-none');

            $('#noImagePreview')
                .addClass('d-none');

        };


        reader.readAsDataURL(file);

    });



    /*
    |--------------------------------------------------------------------------
    | Submit User
    |--------------------------------------------------------------------------
    */

    $('#userForm').on('submit', function(e)
    {

        e.preventDefault();


        let userId = $('#user_id').val();

        let url = '';

        if(userId)
        {

            url = "{{ url('admin/users/update') }}/" + userId;

        }
        else
        {

            url = "{{ route('admin.users.store') }}";

        }


        let formData = new FormData(this);


        $('#saveUserBtn')
            .prop('disabled', true)
            .html(`
                <span class="spinner-border spinner-border-sm"></span>
                Saving...
            `);


        $('#validationErrors')
            .addClass('d-none')
            .html('');


        $.ajax({

            url: url,

            type: "POST",

            data: formData,

            processData: false,

            contentType: false,

            success: function(response)
            {

                if(response.status)
                {

                    toastr.success(response.message);

                    bootstrap.Modal
                        .getInstance(
                            document.getElementById('userModal')
                        )
                        .hide();

                    resetForm();

                    loadUsers();

                }

            },

            error: function(xhr)
            {

                if(xhr.status === 422)
                {

                    let errors = xhr.responseJSON.errors;

                    let errorHtml = '<ul class="mb-0">';

                    $.each(errors, function(key, messages)
                    {

                        $.each(messages, function(index, message)
                        {

                            errorHtml += `
                                <li>${message}</li>
                            `;

                        });

                    });

                    errorHtml += '</ul>';


                    $('#validationErrors')
                        .html(errorHtml)
                        .removeClass('d-none');

                }
                else
                {

                    toastr.error(
                        xhr.responseJSON?.message ??
                        'Something went wrong.'
                    );

                }

            },

            complete: function()
            {

                $('#saveUserBtn')
                    .prop('disabled', false)
                    .html(`
                        <i class="bi bi-check-circle"></i>
                        Save User
                    `);

            }

        });

    });



    /*
    |--------------------------------------------------------------------------
    | Edit User
    |--------------------------------------------------------------------------
    */

    $(document).on('click', '.editUser', function()
    {

        let id = $(this).data('id');


        resetForm();


        $.ajax({

            url: "{{ url('admin/users/edit') }}/" + id,

            type: "GET",

            dataType: "json",

            success: function(response)
            {

                if(!response.status)
                {
                    toastr.error(response.message);
                    return;
                }


                let user = response.data;


                $('#user_id').val(user.id);

                $('#name').val(user.name);

                $('#email').val(user.email);

                $('#designation').val(user.designation);

                $('#role_id').val(user.role_id);

                $('#status').val(user.status);


                /*
                | Password is optional during edit
                */

                $('#password').val('');

                $('#passwordRequired')
                    .addClass('d-none');

                $('#passwordHelp').text(
                    'Leave blank to keep the current password.'
                );


                /*
                | Existing Image
                */

                if(user.image)
                {

                    $('#imagePreview')
                        .attr(
                            'src',
                            "{{ asset('') }}" + user.image
                        )
                        .removeClass('d-none');

                    $('#noImagePreview')
                        .addClass('d-none');

                }


                $('#userModalTitle')
                    .text('Edit User');


                $('#saveUserBtn').html(`
                    <i class="bi bi-check-circle"></i>
                    Update User
                `);


                let modal = new bootstrap.Modal(
                    document.getElementById('userModal')
                );

                modal.show();

            },

            error: function()
            {

                toastr.error(
                    'Unable to load user.'
                );

            }

        });

    });



    /*
    |--------------------------------------------------------------------------
    | Delete User
    |--------------------------------------------------------------------------
    */

    $(document).on('click', '.deleteUser', function()
    {

        let id = $(this).data('id');


        Swal.fire({

            title: 'Are you sure?',

            text: 'This user will be moved to deleted status.',

            icon: 'warning',

            showCancelButton: true,

            confirmButtonText: 'Yes, delete it!',

            cancelButtonText: 'Cancel'

        }).then((result) =>
        {

            if(result.isConfirmed)
            {

                $.ajax({

                    url: "{{ url('admin/users/delete') }}/" + id,

                    type: "POST",

                    data: {
                        _token: "{{ csrf_token() }}"
                    },

                    success: function(response)
                    {
                        alert(JSON.stringify(response));
                        if(response.status)
                        {

                            toastr.success(
                                response.message
                            );

                            loadUsers();

                        }
                        else
                        {

                            toastr.error(
                                response.message
                            );

                        }

                    },

                    error: function(xhr)
                    {
                        alert(JSON.stringify(xhr));
                        toastr.error(
                            xhr.responseJSON?.message ??
                            'Unable to delete user.'
                        );

                    }

                });

            }

        });

    });



    /*
    |--------------------------------------------------------------------------
    | Reset Form
    |--------------------------------------------------------------------------
    */

    function resetForm()
    {

        $('#userForm')[0].reset();

        $('#user_id').val('');

        $('#status').val('Active');

        $('#role_id').val('');

        $('#validationErrors')
            .addClass('d-none')
            .html('');


        $('#imagePreview')
            .attr('src', '')
            .addClass('d-none');

        $('#noImagePreview')
            .removeClass('d-none');


        $('#passwordRequired')
            .removeClass('d-none');

        $('#passwordHelp')
            .text(
                'Required when creating user.'
            );

    }


});

</script>

@endpush
