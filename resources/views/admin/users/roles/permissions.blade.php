@extends('admin.master')

@section('title')
    {{ Session::get('companySettings')[0]['name'] ?? '' }}
    - Assign Permissions
@endsection

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>

            <h4 class="mb-1">
                Assign Permissions
            </h4>

            <div class="text-muted">

                Role:
                <strong>
                    {{ $role->name }}
                </strong>

            </div>

        </div>

        <a href="{{ route('admin.roles.index') }}"
           class="btn btn-secondary">

            <i class="fa fa-arrow-left"></i>
            Back

        </a>

    </div>


    <form id="permissionAssignForm">

        @csrf


        <div class="card shadow-sm">

            <div class="card-header">

                <div class="d-flex justify-content-between align-items-center">

                    <strong>
                        Permissions
                    </strong>

                    <div>

                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                id="selectAllPermissions">

                            Select All

                        </button>

                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                id="unselectAllPermissions">

                            Unselect All

                        </button>

                    </div>

                </div>

            </div>


            <div class="card-body">

                @php
                    $groupedPermissions = $permissions->groupBy(function ($permission) {
                        return $permission->module ?: 'Other';
                    });
                @endphp


                @forelse($groupedPermissions as $module => $modulePermissions)

                    <div class="border rounded mb-3">

                        <div class="bg-light p-2">

                            <div class="custom-control custom-checkbox">

                                <input type="checkbox"
                                       class="custom-control-input module-checkbox"
                                       id="module_{{ Str::slug($module) }}"
                                       data-module="{{ Str::slug($module) }}">

                                <label class="custom-control-label font-weight-bold"
                                       for="module_{{ Str::slug($module) }}">

                                    {{ $module }}

                                </label>

                            </div>

                        </div>


                        <div class="p-3">

                            <div class="row">

                                @foreach($modulePermissions as $permission)

                                    <div class="col-md-4 mb-3">

                                        <div class="custom-control custom-checkbox">

                                            <input type="checkbox"
                                                   class="custom-control-input permission-checkbox permission-module-{{ Str::slug($module) }}"
                                                   name="permissions[]"
                                                   value="{{ $permission->id }}"
                                                   id="permission_{{ $permission->id }}"
                                                   {{ in_array($permission->id, $assignedPermissions) ? 'checked' : '' }}>

                                            <label class="custom-control-label"
                                                   for="permission_{{ $permission->id }}">

                                                {{ $permission->name }}

                                            </label>

                                        </div>

                                        <small class="text-muted ml-4">

                                            {{ $permission->slug }}

                                        </small>

                                    </div>

                                @endforeach

                            </div>

                        </div>

                    </div>

                @empty

                    <div class="alert alert-warning">

                        No active permissions found.

                    </div>

                @endforelse

            </div>


            <div class="card-footer text-right">

                <button type="submit"
                        class="btn btn-primary">

                    <i class="fa fa-save"></i>
                    Save Permissions

                </button>

            </div>

        </div>

    </form>

</div>

@endsection


@push('scripts')

<script>

$(document).ready(function () {


    $('#selectAllPermissions').click(function () {

        $('.permission-checkbox').prop(
            'checked',
            true
        );

        $('.module-checkbox').prop(
            'checked',
            true
        );

    });


    $('#unselectAllPermissions').click(function () {

        $('.permission-checkbox').prop(
            'checked',
            false
        );

        $('.module-checkbox').prop(
            'checked',
            false
        );

    });


    $('.module-checkbox').change(function () {

        var module = $(this).data('module');

        $('.permission-module-' + module)
            .prop(
                'checked',
                $(this).is(':checked')
            );

    });


    $('.permission-checkbox').change(function () {

        var classes = $(this).attr('class');

        var moduleClass = classes
            .split(' ')
            .find(function (className) {

                return className.indexOf(
                    'permission-module-'
                ) === 0;

            });


        if (moduleClass) {

            var module = moduleClass.replace(
                'permission-module-',
                ''
            );

            var total = $(
                '.permission-module-' + module
            ).length;

            var checked = $(
                '.permission-module-' + module + ':checked'
            ).length;

            $('#module_' + module).prop(
                'checked',
                total === checked
            );

        }

    });


    $('#permissionAssignForm').submit(function (e) {

        e.preventDefault();

        $.ajax({

            url:
                "{{ route('admin.roles.permissions.update', $role->id) }}",

            type: "POST",

            data: $(this).serialize(),

            success: function (response) {

                if (response.status) {

                    toastr.success(
                        response.message
                    );

                } else {

                    toastr.error(
                        response.message
                    );

                }

            },

            error: function (xhr) {

                toastr.error(
                    xhr.responseJSON?.message ||
                    'Unable to assign permissions.'
                );

            }

        });

    });

});

</script>

@endpush
