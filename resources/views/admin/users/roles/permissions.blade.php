@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Role Permissions
@endsection

@push('styles')
    <style>
        .perm-grid th, .perm-grid td { vertical-align: middle; }
        .perm-grid .perm-col { width: 84px; text-align: center; }
        .perm-grid .perm-group td { background: var(--color-surface-soft); color: var(--color-text-muted); font-size: var(--text-caption); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .perm-grid .perm-na { color: var(--color-text-muted); font-size: var(--text-micro); }
        .perm-grid .form-check-input { width: 18px; height: 18px; margin: 0; cursor: pointer; }
        .perm-hint { font-size: var(--text-caption); color: var(--color-text-muted); }
    </style>
@endpush

@section('content')
<div class="container-fluid">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-0">Permissions — {{ $role->name }}</h4>
            <small class="text-muted">Tick what this role may do. Menus and buttons a user can't use are hidden, and the server refuses them.</small>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    @if ($isSuper)
        <div class="alert alert-info">This is the <b>Superadmin</b> role: it always has every permission, whatever is ticked here.</div>
    @endif

    <form id="permissionAssignForm" autocomplete="off">
        @csrf

        <div class="card acc-card">
            <div class="card-body">

                <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
                    <div>
                        <label class="form-label mb-1" for="presetSelect">Apply preset</label>
                        <div class="d-flex gap-2">
                            <select id="presetSelect" class="form-select">
                                <option value="">Choose a preset…</option>
                                @foreach ($presets as $key => $preset)
                                    <option value="{{ $key }}">{{ $preset['label'] }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-primary" id="applyPresetBtn">Apply</button>
                        </div>
                    </div>
                    <div class="perm-hint">A preset only ticks the boxes; review them, then save.</div>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="selectAllPermissions">Select all</button>
                        <button type="button" class="btn btn-outline-secondary" id="unselectAllPermissions">Clear all</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle acc-table perm-grid mb-0">
                        <thead>
                            <tr>
                                <th>Module</th>
                                @foreach (\App\Http\Controllers\Admin\PermissionController::ACTIONS as $action => $actionLabel)
                                    <th class="perm-col">
                                        <label class="d-block mb-1" for="col_{{ $action }}">{{ $actionLabel }}</label>
                                        <input type="checkbox" class="form-check-input col-toggle" id="col_{{ $action }}" data-action="{{ $action }}" aria-label="All {{ $actionLabel }}">
                                    </th>
                                @endforeach
                                <th class="perm-col">All</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grid as $group => $modules)
                                <tr class="perm-group"><td colspan="6">{{ $group }}</td></tr>
                                @foreach ($modules as $module => [$label, $cells])
                                    <tr data-module="{{ $module }}">
                                        <td>{{ $label }}</td>
                                        @foreach ($cells as $action => $permissionId)
                                            <td class="perm-col">
                                                @if ($permissionId)
                                                    <input type="checkbox" class="form-check-input permission-checkbox" name="permissions[]"
                                                        value="{{ $permissionId }}" data-action="{{ $action }}" data-module="{{ $module }}"
                                                        aria-label="{{ $action }} {{ $label }}"
                                                        {{ in_array($permissionId, $assignedPermissions) ? 'checked' : '' }}>
                                                @else
                                                    <span class="perm-na" aria-hidden="true">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="perm-col">
                                            <input type="checkbox" class="form-check-input row-toggle" data-module="{{ $module }}" aria-label="All of {{ $label }}">
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach

                            @if ($others->count())
                                <tr class="perm-group"><td colspan="6">Other (custom) permissions</td></tr>
                                @foreach ($others as $permission)
                                    <tr>
                                        <td>{{ $permission->name }} <small class="text-muted">{{ $permission->slug }}</small></td>
                                        <td class="perm-col">
                                            <input type="checkbox" class="form-check-input permission-checkbox" name="permissions[]" value="{{ $permission->id }}"
                                                aria-label="{{ $permission->name }}" {{ in_array($permission->id, $assignedPermissions) ? 'checked' : '' }}>
                                        </td>
                                        <td colspan="4"></td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($canEdit)
                <div class="card-footer d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary" id="savePermissionsBtn"><i class="bi bi-save"></i> Save permissions</button>
                </div>
            @endif
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    var presets = @json($presets);
    var $boxes = $('.permission-checkbox');

    function syncToggles() {
        $('.row-toggle').each(function () {
            var $row = $('.permission-checkbox[data-module="' + $(this).data('module') + '"]');
            $(this).prop('checked', $row.length > 0 && $row.filter(':checked').length === $row.length);
        });
        $('.col-toggle').each(function () {
            var $col = $('.permission-checkbox[data-action="' + $(this).data('action') + '"]');
            $(this).prop('checked', $col.length > 0 && $col.filter(':checked').length === $col.length);
        });
    }

    $boxes.on('change', syncToggles);
    $('.row-toggle').on('change', function () {
        $('.permission-checkbox[data-module="' + $(this).data('module') + '"]').prop('checked', $(this).is(':checked'));
        syncToggles();
    });
    $('.col-toggle').on('change', function () {
        $('.permission-checkbox[data-action="' + $(this).data('action') + '"]').prop('checked', $(this).is(':checked'));
        syncToggles();
    });
    $('#selectAllPermissions').on('click', function () { $boxes.prop('checked', true); syncToggles(); });
    $('#unselectAllPermissions').on('click', function () { $boxes.prop('checked', false); syncToggles(); });

    $('#applyPresetBtn').on('click', function () {
        var preset = presets[$('#presetSelect').val()];
        if (!preset) {
            toastr.warning('Choose a preset first.');
            return;
        }
        var ids = preset.ids.map(String);
        $boxes.each(function () {
            if ($(this).data('module')) {
                $(this).prop('checked', ids.indexOf(String($(this).val())) !== -1);
            }
        });
        syncToggles();
        toastr.info(preset.label + ' preset applied. Review, then save.');
    });

    syncToggles();

    $('#permissionAssignForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#savePermissionsBtn').prop('disabled', true);

        $.ajax({
            url: "{{ route('admin.roles.permissions.update', $role->id) }}",
            type: 'POST',
            dataType: 'json',
            data: $(this).serialize(),
            success: function(response) {
                // alert(JSON.stringify(response));
                $btn.prop('disabled', false);
                if (response.status) {
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                // alert(JSON.stringify(xhr));
                $btn.prop('disabled', false);
                toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to save the permissions.');
            }
        });
    });
});
</script>
@endpush
