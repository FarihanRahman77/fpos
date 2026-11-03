@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} Investors
@endsection

@push('styles')
    <style>
        .inv-num { text-align: right; white-space: nowrap; font-feature-settings: var(--font-feature-numeric); }
        .inv-neg { color: var(--color-danger); }
    </style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0">Investors</h5>
                <small class="text-muted">The owners of the business. Investments, capital withdrawals and advances are entered in Accounts › Suspense.</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <input type="text" id="searchBox" class="form-control" placeholder="Search name, code, phone, email" aria-label="Search investors">
                @perm('suspense.view')
                    <a href="{{ route('admin.suspense.index') }}" class="btn btn-outline-secondary text-nowrap"><i class="bi bi-wallet2"></i> Suspense</a>
                @endperm
                @perm('investors.create')
                    <button type="button" class="btn btn-primary text-nowrap" id="btnAdd"><i class="bi bi-plus-lg"></i> Add Investor</button>
                @endperm
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>NID</th>
                            <th class="text-end">Share</th>
                            <th class="text-end">Capital Invested</th>
                            <th class="text-end">Capital Withdrawn</th>
                            <th class="text-end">Capital</th>
                            <th class="text-end">Outstanding Suspense</th>
                            <th>Status</th>
                            <th width="110">Action</th>
                        </tr>
                    </thead>
                    <tbody id="partyBody">
                        <tr><td colspan="12" class="text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <div id="pageInfo" class="text-muted"></div>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrev">Prev</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnNext">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add / Edit Modal --}}
<div class="modal fade" id="partyModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Investor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="partyForm">
                    <input type="hidden" id="party_id">
                    <div class="row g-3">
                        <div class="col-md-4" id="codeWrap" style="display:none;">
                            <label class="form-label" for="code">Code</label>
                            <input type="text" id="code" class="form-control" placeholder="Auto-generated" readonly>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" class="form-control" placeholder="Name">
                            <span class="text-danger" id="nameError"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="phone">Phone <span class="text-danger">*</span></label>
                            <input type="text" id="phone" class="form-control" placeholder="Mobile Number">
                            <span class="text-danger" id="phoneError"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="alt_phone">Alt Phone</label>
                            <input type="text" id="alt_phone" class="form-control" placeholder="Alternative">
                            <span class="text-danger" id="alt_phoneError"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" class="form-control" placeholder="example@gmail.com">
                            <span class="text-danger" id="emailError"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="nid">NID</label>
                            <input type="text" id="nid" class="form-control" placeholder="National ID number">
                            <span class="text-danger" id="nidError"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="share_percent">Share %</label>
                            <input type="number" step="0.01" min="0" max="100" id="share_percent" class="form-control" placeholder="Optional">
                            <span class="text-danger" id="share_percentError"></span>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Address</label>
                            <textarea id="address" class="form-control" rows="2" placeholder="Address"></textarea>
                            <span class="text-danger" id="addressError"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="opening_capital">Opening Capital</label>
                            <input type="number" step="0.01" min="0" id="opening_capital" class="form-control" value="0" placeholder="0.00">
                            <span class="text-danger" id="opening_capitalError"></span>
                            <small class="text-muted">Capital already invested before these books (main branch).</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="opening_balance_date">Capital Date</label>
                            <input type="date" id="opening_balance_date" class="form-control">
                            <span class="text-danger" id="opening_balance_dateError"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="status">Status</label>
                            <select id="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <span class="text-danger" id="statusError"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btnSave">Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

    const partyType = 'Investor';
    const partyModal = new bootstrap.Modal(document.getElementById('partyModal'));
    const canEdit = @json(\App\Http\Controllers\Admin\PermissionController::allows('investors.edit'));
    const canDelete = @json(\App\Http\Controllers\Admin\PermissionController::allows('investors.delete'));
    const canSuspense = @json(\App\Http\Controllers\Admin\PermissionController::allows('suspense.view'));
    const suspenseUrl = "{{ route('admin.suspense.index') }}";
    let currentPage = 1, lastPage = 1, searchTimer = null;

    const fields = ['name', 'email', 'phone', 'alt_phone', 'address', 'nid', 'share_percent', 'opening_capital', 'opening_balance_date', 'status'];

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }

    function money(v) {
        return parseFloat(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function clearErrors() {
        $('#partyForm .is-invalid').removeClass('is-invalid');
        $('#partyForm [id$="Error"]').text('');
    }

    $('#partyForm').on('input change', '.form-control, .form-select', function () {
        $(this).removeClass('is-invalid');
        $('#' + this.id + 'Error').text('');
    });

    function showError(xhr) {
        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
            clearErrors();
            let other = '';
            $.each(xhr.responseJSON.errors, function (field, msgs) {
                if ($('#' + field + 'Error').length) {
                    $('#' + field).addClass('is-invalid');
                    $('#' + field + 'Error').text(msgs[0]);
                } else {
                    other += '<li>' + esc(msgs[0]) + '</li>';
                }
            });
            if (other) {
                Swal.fire({ icon: 'error', title: 'Validation Error', html: '<ul class="text-start mb-0">' + other + '</ul>' });
            } else {
                Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Please correct the highlighted fields', timer: 2500, showConfirmButton: false });
            }
            return;
        }
        let msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Something went wrong';
        Swal.fire({ icon: 'error', title: 'Error', text: msg });
    }

    function showSuccess(msg) {
        Swal.fire({ icon: 'success', title: 'Success', text: msg, timer: 1500, showConfirmButton: false });
    }

    /* ---------- List ---------- */
    function loadParties(page) {
        currentPage = page || 1;
        $.ajax({
            url: "{{ url('/admin/parties/list') }}/" + encodeURIComponent(partyType),
            type: 'GET',
            dataType: 'json',
            data: { page: currentPage, search: $('#searchBox').val() },
            success: function(response) {
                // alert(JSON.stringify(response));
                lastPage = response.last_page;
                let rows = '';
                if (!response.data.length) {
                    rows = '<tr><td colspan="12" class="text-center text-muted">No investors yet</td></tr>';
                }
                $.each(response.data, function (i, p) {
                    const badge = p.status === 'Active' ? 'bg-success' : 'bg-secondary';
                    const items = [];
                    if (canSuspense) items.push({ label: 'Suspense & statement', icon: 'fas fa-file-invoice', href: suspenseUrl + '?person=Investor-' + p.id });
                    if (canEdit) items.push({ label: 'Edit', icon: 'fas fa-edit', class: 'btn-edit', attrs: { 'data-id': p.id } });
                    if (canDelete) items.push({ label: 'Delete', icon: 'fas fa-trash', class: 'text-danger btn-delete', attrs: { 'data-id': p.id } });
                    const share = p.share_percent !== null && p.share_percent !== '' ? parseFloat(p.share_percent) + '%' : '-';
                    rows += `<tr>
                        <td>${response.from + i}</td>
                        <td>${esc(p.code)}</td>
                        <td>${esc(p.name)}</td>
                        <td>${esc(p.phone)}</td>
                        <td>${esc(p.nid || '-')}</td>
                        <td class="inv-num">${esc(share)}</td>
                        <td class="inv-num">${money(p.invested)}</td>
                        <td class="inv-num">${money(p.capital_withdrawn)}</td>
                        <td class="inv-num fw-semibold">${money(p.capital)}</td>
                        <td class="inv-num ${p.outstanding < 0 ? 'inv-neg' : ''}">${money(p.outstanding)}</td>
                        <td><span class="badge ${badge} ${canEdit ? 'btn-status' : ''}" ${canEdit ? 'role="button"' : ''} data-id="${p.id}">${esc(p.status)}</span></td>
                        <td>${items.length ? actionDropdown(items) : ''}</td>
                    </tr>`;
                });
                $('#partyBody').html(rows);
                $('#pageInfo').text('Page ' + response.current_page + ' of ' + response.last_page + ' (' + response.total + ' records)');
                $('#btnPrev').prop('disabled', response.current_page <= 1);
                $('#btnNext').prop('disabled', response.current_page >= response.last_page);
            },
            error: function(xhr) {
                // alert(JSON.stringify(xhr));
                showError(xhr);
            }
        });
    }

    function resetForm() {
        clearErrors();
        $('#party_id').val('');
        $('#code').val('');
        $('#codeWrap').hide();
        $.each(fields, function (i, f) { $('#' + f).val(''); });
        $('#opening_capital').val(0);
        $('#status').val('Active');
    }

    $('#btnAdd').on('click', function () {
        resetForm();
        $('#modalTitle').text('Add Investor');
        partyModal.show();
    });

    $(document).on('click', '.btn-edit', function () {
        const id = $(this).data('id');
        $.ajax({
            url: "{{ url('/admin/parties/show') }}",
            type: 'GET',
            dataType: 'json',
            data: { id: id },
            success: function(response) {
                // alert(JSON.stringify(response));
                resetForm();
                $('#party_id').val(response.id);
                $('#code').val(response.code);
                $('#codeWrap').show();
                $.each(fields, function (i, f) { $('#' + f).val(response[f]); });
                if (response.opening_balance_date) $('#opening_balance_date').val(String(response.opening_balance_date).slice(0, 10));
                $('#modalTitle').text('Edit Investor');
                partyModal.show();
            },
            error: function(xhr) {
                // alert(JSON.stringify(xhr));
                showError(xhr);
            }
        });
    });

    $('#btnSave').on('click', function () {
        const id = $('#party_id').val();
        let data = { type: partyType };
        $.each(fields, function (i, f) { data[f] = $('#' + f).val(); });
        if (id) data.id = id;
        const $btn = $(this).prop('disabled', true);

        $.ajax({
            url: id ? "{{ url('/admin/parties/update') }}" : "{{ url('/admin/parties/store') }}",
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function(response) {
                // alert(JSON.stringify(response));
                partyModal.hide();
                showSuccess(response.message);
                loadParties(id ? currentPage : 1);
            },
            error: function(xhr) {
                // alert(JSON.stringify(xhr));
                showError(xhr);
            },
            complete: function () { $btn.prop('disabled', false); }
        });
    });

    $(document).on('click', '.btn-status', function () {
        $.ajax({
            url: "{{ url('/admin/parties/toggle-status') }}",
            type: 'POST',
            dataType: 'json',
            data: { id: $(this).data('id') },
            success: function(response) {
                // alert(JSON.stringify(response));
                showSuccess(response.message);
                loadParties(currentPage);
            },
            error: function(xhr) {
                // alert(JSON.stringify(xhr));
                showError(xhr);
            }
        });
    });

    $(document).on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        Swal.fire({
            icon: 'warning',
            title: 'Are you sure?',
            text: 'This investor will be deleted.',
            showCancelButton: true,
            confirmButtonColor: 'var(--color-danger)',
            confirmButtonText: 'Yes, delete'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url: "{{ url('/admin/parties/delete') }}",
                type: 'POST',
                dataType: 'json',
                data: { id: id },
                success: function(response) {
                    // alert(JSON.stringify(response));
                    showSuccess(response.message);
                    loadParties(currentPage);
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    showError(xhr);
                }
            });
        });
    });

    $('#searchBox').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadParties(1); }, 400);
    });
    $('#btnPrev').on('click', function () { if (currentPage > 1) loadParties(currentPage - 1); });
    $('#btnNext').on('click', function () { if (currentPage < lastPage) loadParties(currentPage + 1); });

    $(function () { loadParties(1); });
</script>
@endpush
