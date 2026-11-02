@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Branches
@endsection

@push('styles')
    <style>
        .br-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: var(--space-lg); }
        .br-card {
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
            padding: var(--space-lg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: var(--color-surface);
            box-shadow: var(--shadow-1);
        }
        .br-card.is-main { border-color: var(--color-accent); }
        .br-card.is-off { opacity: .65; }
        .br-card h3 { margin: 0; font-size: var(--text-heading-sm); font-weight: var(--font-weight-regular); }
        .br-meta { font-size: var(--text-caption); color: var(--color-text-muted); }
        .br-stats { display: flex; gap: var(--space-lg); font-size: var(--text-caption); }
        .br-stats b { display: block; font-size: var(--text-body-lg); font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); }
        .br-pill { display: inline-block; padding: 0 var(--space-sm); border-radius: var(--radius-pill); font-size: var(--text-micro); font-weight: var(--font-weight-medium); }
        .br-pill.main { background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .br-pill.off { background: var(--color-surface-soft); color: var(--color-text-muted); }
        .br-actions { display: flex; flex-wrap: wrap; gap: var(--space-xs); margin-top: auto; padding-top: var(--space-sm); }
        .access-table td, .access-table th { min-width: 0; padding: .5rem 10px; font-size: var(--text-caption); vertical-align: middle; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Branches</h4>
                <small class="text-muted">Each sale, purchase, stock movement, payment, expense and employee belongs to a branch. Switch branch from the top bar.</small>
            </div>
            <div class="ms-auto">
                <button type="button" class="btn btn-primary" id="addBranch"><i class="bi bi-plus-lg"></i> New Branch</button>
            </div>
        </div>

        <div class="br-grid mb-4" id="branchGrid"></div>

        <div class="card acc-card">
            <div class="card-body">
                <h6 class="mb-1">Who works where</h6>
                <p class="text-muted small mb-3">Home branch is where a user's new records go. “All branches” users can also view everything combined.</p>
                <div class="table-responsive">
                    <table class="table table-bordered access-table mb-0">
                        <thead><tr><th>User</th><th>Home Branch</th><th>Also Works In</th><th>All Branches</th><th style="width: 90px;"></th></tr></thead>
                        <tbody>
                            @foreach ($users as $u)
                                <tr data-user="{{ $u->id }}">
                                    <td><span class="fw-semibold">{{ $u->name }}</span><div class="text-muted small">{{ $u->email }}</div></td>
                                    <td><select class="form-select form-select-sm acc-home" aria-label="Home branch"></select></td>
                                    <td><select class="form-select form-select-sm acc-more" multiple aria-label="Other branches"></select></td>
                                    <td>
                                        <select class="form-select form-select-sm acc-all" aria-label="All branches">
                                            <option value="No" @selected($u->all_branches === 'No')>No</option>
                                            <option value="Yes" @selected($u->all_branches === 'Yes')>Yes</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn btn-sm btn-outline-primary acc-save" data-home="{{ $u->branch_id }}">Save</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="branchModal" tabindex="-1" aria-labelledby="branchTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="branchForm" autocomplete="off" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="branchTitle">New Branch</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="br_id">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="br_name">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="br_name" class="form-control" maxlength="120">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="br_code">Code</label>
                                <input type="text" name="code" id="br_code" class="form-control" maxlength="20">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="br_address">Address</label>
                                <input type="text" name="address" id="br_address" class="form-control" maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="br_phone">Phone</label>
                                <input type="text" name="phone" id="br_phone" class="form-control" maxlength="50">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="br_email">Email</label>
                                <input type="email" name="email" id="br_email" class="form-control" maxlength="120">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="br_status">Status</label>
                                <select name="status" id="br_status" class="form-select">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="text-danger small mt-2" id="br_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="brSubmit">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const R = {
            list: "{{ route('admin.branches.list') }}",
            save: "{{ route('admin.branches.save') }}",
            main: "{{ route('admin.branches.main', '__ID__') }}",
            del: "{{ route('admin.branches.delete', '__ID__') }}",
            access: "{{ route('admin.branches.access', '__ID__') }}",
            accessSave: "{{ route('admin.branches.access.save') }}"
        };
        const esc = Voucher.esc;
        let branches = [];

        function loadBranches() {
            $.ajax({
                url: R.list,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    // alert(JSON.stringify(response));
                    branches = response.data;
                    $('#branchGrid').html(branches.map(function(b) {
                        const main = b.is_main === 'Yes', off = b.status !== 'Active';
                        return '<div class="br-card ' + (main ? 'is-main' : '') + (off ? ' is-off' : '') + '">' +
                            '<div class="d-flex align-items-start gap-2"><h3>' + esc(b.name) + '</h3>' +
                            (main ? '<span class="br-pill main ms-auto">Main</span>' : (off ? '<span class="br-pill off ms-auto">Inactive</span>' : '')) + '</div>' +
                            '<div class="br-meta">' + esc([b.code, b.address].filter(Boolean).join(' · ') || 'No address') + '</div>' +
                            (b.phone ? '<div class="br-meta"><i class="bi bi-telephone"></i> ' + esc(b.phone) + '</div>' : '') +
                            '<div class="br-stats"><div><b>' + b.sales + '</b>sales</div><div><b>' + b.employees + '</b>employees</div><div><b>' + b.users.length + '</b>users</div></div>' +
                            '<div class="br-actions">' +
                                '<button type="button" class="btn btn-sm btn-outline-secondary br-edit" data-id="' + b.id + '"><i class="bi bi-pencil"></i> Edit</button>' +
                                (!main ? '<button type="button" class="btn btn-sm btn-outline-secondary br-main" data-id="' + b.id + '">Make main</button>' +
                                    '<button type="button" class="btn btn-sm btn-outline-danger br-del" data-id="' + b.id + '" aria-label="Remove"><i class="bi bi-trash"></i></button>' : '') +
                            '</div></div>';
                    }).join(''));

                    const active = branches.filter(function(b) { return b.status === 'Active'; });
                    const opts = active.map(function(b) { return '<option value="' + b.id + '">' + esc(b.name) + '</option>'; }).join('');
                    $('.access-table tr[data-user]').each(function() {
                        const $tr = $(this), userId = $tr.data('user');
                        const home = $tr.find('.acc-save').data('home');
                        $tr.find('.acc-home').html(opts).val(String(home || (active[0] || {}).id || ''));
                        const $more = $tr.find('.acc-more').html(opts);
                        $.ajax({
                            url: R.access.replace('__ID__', userId),
                            type: 'GET',
                            dataType: 'json',
                            success: function(response) {
                                // alert(JSON.stringify(response));
                                $more.val((response.branches || []).map(String)).trigger('change');
                            },
                            error: function(xhr) {
                                // alert(JSON.stringify(xhr));
                                toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                            }
                        });
                    });
                    Voucher.initSelect2($('.acc-more'));
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });
        }

        $(document).ready(function() {
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('branchModal'));

            $('#addBranch').on('click', function() {
                $('#branchForm')[0].reset();
                $('#br_id').val('');
                $('#branchTitle').text('New Branch');
                Voucher.clearErrors($('#branchForm'));
                $('#br_error').text('');
                modal.show();
            });

            $(document).on('click', '.br-edit', function() {
                const b = branches.find(function(x) { return x.id == $(this).data('id'); }, this);
                if (!b) return;
                $('#br_id').val(b.id);
                $('#br_name').val(b.name);
                $('#br_code').val(b.code || '');
                $('#br_address').val(b.address || '');
                $('#br_phone').val(b.phone || '');
                $('#br_email').val(b.email || '');
                $('#br_status').val(b.status);
                $('#branchTitle').text('Edit ' + b.name);
                Voucher.clearErrors($('#branchForm'));
                $('#br_error').text('');
                modal.show();
            });

            $('#branchForm').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#brSubmit').prop('disabled', true);
                $.ajax({
                    url: R.save,
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        modal.hide();
                        loadBranches();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($('#branchForm'), xhr.responseJSON.errors, $('#br_error'));
                        } else {
                            $('#br_error').text((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to save.');
                        }
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });

            $(document).on('click', '.br-main', function() {
                $.ajax({
                    url: R.main.replace('__ID__', $(this).data('id')),
                    type: 'POST',
                    dataType: 'json',
                    data: {},
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        loadBranches();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to change.');
                    }
                });
            });

            $(document).on('click', '.br-del', function() {
                const id = $(this).data('id');
                Swal.fire({ title: 'Remove this branch?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Remove', reverseButtons: true })
                    .then(function(r) {
                        if (!r.isConfirmed) return;
                        $.ajax({
                            url: R.del.replace('__ID__', id),
                            type: 'POST',
                            dataType: 'json',
                            data: {},
                            success: function(response) {
                                // alert(JSON.stringify(response));
                                toastr.success(response.message);
                                loadBranches();
                            },
                            error: function(xhr) {
                                // alert(JSON.stringify(xhr));
                                Voucher.ajaxError(xhr, 'Unable to remove.');
                            }
                        });
                    });
            });

            $(document).on('click', '.acc-save', function() {
                const $tr = $(this).closest('tr');
                $.ajax({
                    url: R.accessSave,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        user_id: $tr.data('user'),
                        branch_id: $tr.find('.acc-home').val(),
                        all_branches: $tr.find('.acc-all').val(),
                        branches: $tr.find('.acc-more').val() || []
                    },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to save.');
                    }
                });
            });

            loadBranches();
        });
    </script>
@endpush
