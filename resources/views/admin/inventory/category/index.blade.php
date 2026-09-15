@extends('admin.master')

@section('title')
    {{ Session::get('companySettings')[0]['name'] ?? '' }} Categories
@endsection

@section('content')

<div class="container-fluid">

    <div class="d-flex align-items-center mb-3">

        <h4 class="mb-0">
            Categories
        </h4>

        <button type="button"
                class="btn btn-primary ms-auto"
                onclick="openCategoryModal()">

            <i class="fas fa-plus"></i>
            Add Category

        </button>

    </div>


    <div class="card">

        <div class="card-body">

            <table id="manageCategoryTable"
                   width="100%"
                   class="table table-bordered table-hover">

                <thead>

                    <tr>

                        <th width="6%">SL.</th>

                        <th>Category Name</th>

                        <th>Slug</th>

                        <th width="10%">Status</th>

                        <th width="8%">Action</th>

                    </tr>

                </thead>

            </table>

        </div>

    </div>

</div>


{{-- Category Modal --}}

<div class="modal fade"
     id="categoryModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title"
                    id="categoryModalTitle">

                    Add Category

                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>


            <form id="categoryForm">

                @csrf

                <input type="hidden"
                       name="id"
                       id="category_id">


                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            Category Name
                        </label>

                        <input type="text"
                               name="name"
                               id="category_name"
                               class="form-control">

                        <div class="text-danger small"
                             id="category_name_error">
                        </div>

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
                            id="categorySaveBtn">

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

let categoryTable;

let categoryModal =
    new bootstrap.Modal(
        document.getElementById('categoryModal')
    );


$(document).ready(function () {

    categoryTable = $('#manageCategoryTable').DataTable({

        ajax: "{{ route('admin.category.list') }}",

        processing: true,

        pageLength: 25,

        order: [],

        columnDefs: [

            {
                targets: 0,
                orderable: false
            },

            {
                targets: 4,
                orderable: false,
                searchable: false
            }

        ]

    });


    $('#categoryForm').on('submit', function (e) {

        e.preventDefault();

        saveCategory();

    });

});


function openCategoryModal()
{
    $('#categoryForm')[0].reset();

    $('#category_id').val('');

    $('#category_name_error').html('');

    $('#categoryModalTitle').text('Add Category');

    $('#categorySaveBtn')
        .prop('disabled', false)
        .text('Save');

    categoryModal.show();
}


function saveCategory()
{
    let form = $('#categoryForm');

    let button = $('#categorySaveBtn');

    button.prop('disabled', true)
          .text('Saving...');


    $('.text-danger').html('');


    $.ajax({

        url: "{{ route('admin.category.save') }}",

        type: "POST",

        data: form.serialize(),

        success: function (response) {

            if (response.status) {

                categoryModal.hide();

                categoryTable.ajax.reload(null, false);

                toastr.success(response.message);

            }

        },

        error: function (xhr) {

            if (xhr.status === 422) {

                let errors = xhr.responseJSON.errors;

                if (errors.name) {

                    $('#category_name_error')
                        .html(errors.name[0]);
                }

            } else {

                toastr.error(
                    xhr.responseJSON?.message ||
                    'Something went wrong.'
                );
            }

        },

        complete: function () {

            button.prop('disabled', false)
                  .text(
                      $('#category_id').val()
                          ? 'Update'
                          : 'Save'
                  );

        }

    });
}


function editCategory(id)
{
    $.ajax({

        url: "{{ url('admin/category/edit') }}/" + id,

        type: "GET",

        success: function (response) {

            if (response.status) {

                $('#category_id')
                    .val(response.data.id);

                $('#category_name')
                    .val(response.data.name);

                $('#category_name_error').html('');

                $('#categoryModalTitle')
                    .text('Edit Category');

                $('#categorySaveBtn')
                    .text('Update');

                categoryModal.show();

            }

        },

        error: function (xhr) {

            toastr.error(
                xhr.responseJSON?.message ||
                'Unable to load category.'
            );

        }

    });
}


function deleteCategory(id)
{
    if (!confirm('Are you sure you want to delete this category?')) {
        return;
    }


    $.ajax({

        url: "{{ route('admin.category.delete') }}",

        type: "POST",

        data: {

            _token: "{{ csrf_token() }}",

            id: id

        },

        success: function (response) {

            if (response.status) {

                categoryTable.ajax.reload(null, false);

                toastr.success(response.message);

            }

        },

        error: function (xhr) {

            toastr.error(
                xhr.responseJSON?.message ||
                'Unable to delete category.'
            );

        }

    });
}


function changeCategoryStatus(id)
{
    $.ajax({

        url: "{{ route('admin.category.status') }}",

        type: "POST",

        data: {

            _token: "{{ csrf_token() }}",

            id: id

        },

        success: function (response) {

            if (response.status) {

                categoryTable.ajax.reload(null, false);

                toastr.success(response.message);

            }

        },

        error: function (xhr) {

            toastr.error(
                xhr.responseJSON?.message ||
                'Unable to change status.'
            );

        }

    });
}

</script>

@endpush
