@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} {{ $party_type }}
@endsection

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0">{{ $party_type }} List</h5>
            <div class="d-flex gap-2">
                <input type="text" id="searchBox" class="form-control" placeholder="Search name, code, phone, email">
                <button type="button" class="btn btn-primary text-nowrap" id="btnAdd">
                    <i class="bi bi-plus-lg"></i> Add {{ $party_type }}
                </button>
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
                            <th>Email</th>
                            <th class="text-end">Opening Balance</th>
                            <th class="text-end">Credit Limit</th>
                            <th>Status</th>
                            <th width="110">Action</th>
                        </tr>
                    </thead>
                    <tbody id="partyBody">
                        <tr><td colspan="9" class="text-center">Loading...</td></tr>
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
<div class="modal fade" id="partyModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add {{ $party_type }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="partyForm">
                    <input type="hidden" id="party_id">
                    <div class="row g-3">
                        <div class="col-md-4" id="codeWrap" style="display:none;">
                            <label class="form-label">Code</label>
                            <input type="text" id="code" class="form-control" readonly>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" class="form-control" placeholder="Name">
                            <span class="text-danger" id="nameError"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" id="email" class="form-control" placeholder="example@gmail.com">
                            <span class="text-danger" id="emailError"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" id="phone" class="form-control" placeholder="Mobile Number">
                            <span class="text-danger" id="phoneError"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Alt Phone</label>
                            <input type="text" id="alt_phone" class="form-control" placeholder="Alternative">
                            <span class="text-danger" id="alt_phoneError"></span>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea id="address" class="form-control" rows="2" placeholder="Address"></textarea>
                            <span class="text-danger" id="addressError"></span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Opening Balance</label>
                            <input type="number" step="0.01" min="0" id="opening_balance" class="form-control" value="0">
                            <span class="text-danger" id="opening_balanceError"></span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Balance Type</label>
                            <select id="opening_balance_type" class="form-select">
                                <option value="Due">Due</option>
                                <option value="Advance">Advance</option>
                            </select>
                            <span class="text-danger" id="opening_balance_typeError"></span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Balance Date</label>
                            <input type="date" id="opening_balance_date" class="form-control">
                            <span class="text-danger" id="opening_balance_dateError"></span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Credit Limit</label>
                            <input type="number" step="0.01" min="0" id="credit_limit" class="form-control" value="0">
                            <span class="text-danger" id="credit_limitError"></span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
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
    // Requires jQuery, Bootstrap 5 and SweetAlert2 (Swal) to be loaded in admin.master
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

    const partyType = @json($party_type);
    const partyModal = new bootstrap.Modal(document.getElementById('partyModal'));
    let currentPage = 1, lastPage = 1, searchTimer = null;

    const fields = ['name', 'email', 'phone', 'alt_phone', 'address', 'opening_balance',
                    'opening_balance_type', 'opening_balance_date', 'credit_limit', 'status'];

    function esc(v) {
        return $('<div>').text(v ?? '').html();
    }

    function money(v) {
        return parseFloat(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function clearErrors() {
        $('#partyForm .is-invalid').removeClass('is-invalid');
        $('#partyForm [id$="Error"]').text('');
    }

    // clear a field's error as soon as the user edits it
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
                    other += '<li>' + esc(msgs[0]) + '</li>';   // errors with no input (e.g. type)
                }
            });
            if (other) {
                Swal.fire({ icon: 'error', title: 'Validation Error', html: '<ul class="text-start mb-0">' + other + '</ul>' });
            } else {
                Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Please correct the highlighted fields',
                            timer: 2500, showConfirmButton: false });
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
            data: { page: currentPage, search: $('#searchBox').val() },
            success: function (res) {
                lastPage = res.last_page;
                let rows = '';
                if (!res.data.length) {
                    rows = '<tr><td colspan="9" class="text-center text-muted">No data found</td></tr>';
                }
                $.each(res.data, function (i, p) {
                    const badge = p.status === 'Active' ? 'bg-success' : 'bg-secondary';
                    rows += `<tr>
                        <td>${res.from + i}</td>
                        <td>${esc(p.code)}</td>
                        <td>${esc(p.name)}</td>
                        <td>${esc(p.phone)}</td>
                        <td>${esc(p.email)}</td>
                        <td class="text-end">${money(p.opening_balance)} <small class="text-muted">(${esc(p.opening_balance_type)})</small></td>
                        <td class="text-end">${money(p.credit_limit)}</td>
                        <td><span class="badge ${badge} btn-status" role="button" data-id="${p.id}">${esc(p.status)}</span></td>
                        <td>
                            <button class="btn btn-sm btn-info btn-edit" data-id="${p.id}"><i class="bi bi-pencil-square"></i></button>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="${p.id}"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>`;
                });
                $('#partyBody').html(rows);
                $('#pageInfo').text('Page ' + res.current_page + ' of ' + res.last_page + ' (' + res.total + ' records)');
                $('#btnPrev').prop('disabled', res.current_page <= 1);
                $('#btnNext').prop('disabled', res.current_page >= res.last_page);
            },
            error: showError
        });
    }

    /* ---------- Form helpers ---------- */
    function resetForm() {
        clearErrors();
        $('#party_id').val('');
        $('#code').val('');
        $('#codeWrap').hide();
        $.each(fields, function (i, f) { $('#' + f).val(''); });
        $('#opening_balance').val(0);
        $('#credit_limit').val(0);
        $('#opening_balance_type').val('Due');
        $('#status').val('Active');
    }

    /* ---------- Add ---------- */
    $('#btnAdd').on('click', function () {
        resetForm();
        $('#modalTitle').text('Add ' + partyType);
        partyModal.show();
    });

    /* ---------- Edit (fetch) ---------- */
    $(document).on('click', '.btn-edit', function () {
        const id = $(this).data('id');
        $.ajax({
            url: "{{ url('/admin/parties/show') }}",
            type: 'GET',
            data: { id: id },
            success: function (p) {
                resetForm();
                $('#party_id').val(p.id);
                $('#code').val(p.code);
                $('#codeWrap').show();
                $.each(fields, function (i, f) { $('#' + f).val(p[f]); });
                $('#modalTitle').text('Edit ' + partyType);
                partyModal.show();
            },
            error: showError
        });
    });

    /* ---------- Save (store / update) ---------- */
    $('#btnSave').on('click', function () {
        const id = $('#party_id').val();
        let data = { type: partyType };
        $.each(fields, function (i, f) { data[f] = $('#' + f).val(); });
        if (id) data.id = id;

        const $btn = $(this).prop('disabled', true);

        $.ajax({
            url: id ? "{{ url('/admin/parties/update') }}" : "{{ url('/admin/parties/store') }}",
            type: 'POST',
            data: data,
            success: function (res) {
                partyModal.hide();
                showSuccess(res.message);
                loadParties(id ? currentPage : 1);
            },
            error: showError,
            complete: function () { $btn.prop('disabled', false); }
        });
    });

    /* ---------- Toggle status ---------- */
    $(document).on('click', '.btn-status', function () {
        $.ajax({
            url: "{{ url('/admin/parties/toggle-status') }}",
            type: 'POST',
            data: { id: $(this).data('id') },
            success: function (res) {
                showSuccess(res.message);
                loadParties(currentPage);
            },
            error: showError
        });
    });

    /* ---------- Delete ---------- */
    $(document).on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        Swal.fire({
            icon: 'warning',
            title: 'Are you sure?',
            text: 'This ' + partyType + ' will be deleted.',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url: "{{ url('/admin/parties/delete') }}",
                type: 'POST',
                data: { id: id },
                success: function (res) {
                    showSuccess(res.message);
                    loadParties(currentPage);
                },
                error: showError
            });
        });
    });

    /* ---------- Search & pagination ---------- */
    $('#searchBox').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadParties(1); }, 400);
    });
    $('#btnPrev').on('click', function () { if (currentPage > 1) loadParties(currentPage - 1); });
    $('#btnNext').on('click', function () { if (currentPage < lastPage) loadParties(currentPage + 1); });

    $(function () { loadParties(1); });
</script>
@endpush
