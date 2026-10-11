@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Chart of Accounts
@endsection

@push('styles')
    <style>
        .coa-stat { border: 0; border-radius: 14px; box-shadow: 0 2px 12px rgba(30, 41, 59, .07); }
        .coa-stat .icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; }
        .coa-stat .icon.i1 { background: #e0e7ff; color: #4338ca; }
        .coa-stat .icon.i2 { background: #fef3c7; color: #b45309; }
        .coa-stat .icon.i3 { background: #dcfce7; color: #15803d; }
        .coa-stat .icon.i4 { background: #f1f5f9; color: #475569; }

        .coa-card { border: 0; border-radius: 14px; box-shadow: 0 2px 12px rgba(30, 41, 59, .07); }
        #coaTable thead th { background: #f8fafc; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; white-space: nowrap; }
        #coaTable tbody td { vertical-align: middle; }
        #coaTable tbody tr:hover { background: #f8fafc; }

        .coa-code { font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: .85rem; background: #f1f5f9; padding: 2px 8px; border-radius: 6px; }
        .coa-indent-1 { padding-left: 1.5rem; }
        .coa-indent-2 { padding-left: 3rem; }
        .coa-layer { font-weight: 600; border: 1px solid transparent; }
        .coa-layer-1 { background: #e0e7ff; color: #3730a3; }
        .coa-layer-2 { background: #fef3c7; color: #92400e; }
        .coa-layer-3 { background: #dcfce7; color: #166534; }
        .coa-status-toggle { cursor: pointer; user-select: none; }

        .select2-container--default .select2-results__option .coa-opt-root { font-weight: 700; }
        .select2-container--default .select2-results__option .coa-opt-group { padding-left: 18px; }
        .coa-preview { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 12px; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex align-items-center mb-3">
            <div>
                <h4 class="mb-0">Chart of Accounts</h4>
                <small class="text-muted">1st layer → 2nd layer → 3rd layer account hierarchy</small>
            </div>

            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="refreshCoaBtn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button type="button" class="btn btn-primary" id="addCoaBtn">
                    <i class="fas fa-plus"></i> Add Chart Of Account
                </button>
            </div>
        </div>

        {{-- Summary --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card coa-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i1"><i class="fas fa-layer-group"></i></div>
                    <div><div class="text-muted small">1st Layer</div><h4 class="mb-0" id="rootAccountCount">0</h4></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card coa-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-folder-open"></i></div>
                    <div><div class="text-muted small">2nd Layer</div><h4 class="mb-0" id="groupAccountCount">0</h4></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card coa-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-file-alt"></i></div>
                    <div><div class="text-muted small">3rd Layer</div><h4 class="mb-0" id="ledgerAccountCount">0</h4></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card coa-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-sitemap"></i></div>
                    <div><div class="text-muted small">Total Accounts</div><h4 class="mb-0" id="totalAccountCount">0</h4></div>
                </div></div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card coa-card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label" for="coaLayerFilter">Account Layer</label>
                        <select id="coaLayerFilter" class="form-select">
                            <option value="">All Layers</option>
                            <option value="1st Layer">1st Layer</option>
                            <option value="2nd Layer">2nd Layer</option>
                            <option value="3rd Layer">3rd Layer</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="coaTypeFilter">Account Type</label>
                        <select id="coaTypeFilter" class="form-select">
                            <option value="">All Types</option>
                            <option>Asset</option>
                            <option>Liquid Asset</option>
                            <option>Liability</option>
                            <option>Equity</option>
                            <option>Income</option>
                            <option>Expense</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="coaStatusFilter">Status</label>
                        <select id="coaStatusFilter" class="form-select">
                            <option value="">All Statuses</option>
                            <option>Active</option>
                            <option>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-outline-secondary w-100" id="resetCoaFilters">
                            <i class="fas fa-undo"></i> Reset Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table (options are read from data-* attributes, so the JS call stays minimal) --}}
        <div class="card coa-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="coaTable" class="table table-hover align-middle w-100 datatable"
                        data-page-length="25"
                        data-order="[]"
                        data-auto-width="false"
                        data-length-menu='[[10,25,50,100,-1],["10","25","50","100","All"]]'>
                        <thead>
                            <tr>
                                <th data-orderable="false" data-searchable="false" width="5%">SL.</th>
                                <th>Account Code</th>
                                <th>Account Name</th>
                                <th>Layer</th>
                                <th>Account Type</th>
                                <th>Normal Balance</th>
                                <th class="text-end">Opening Balance</th>
                                <th>Opening Date</th>
                                <th>Status</th>
                                <th data-orderable="false" data-searchable="false" width="12%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-transparent text-muted small">
                1st layer accounts cannot be deleted. Only 3rd layer accounts can be selected in journal entries.
            </div>
        </div>
    </div>

    {{-- Add / Edit modal --}}
    <div class="modal fade" id="coaModal" tabindex="-1" aria-labelledby="coaModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="coaForm" novalidate>
                    @csrf
                    <input type="hidden" id="account_id">

                    <div class="modal-header">
                        <h5 class="modal-title" id="coaModalTitle">Add Chart Of Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="alert alert-info py-2 small mb-3" id="coaHint">
                            Select a parent account. The code, type and normal balance are generated automatically.
                        </div>

                        <div class="row g-3">
                            <div class="col-12" id="parentSelectGroup">
                                <label class="form-label" for="parent_id">Parent Account <span class="text-danger">*</span></label>
                                <select id="parent_id" name="parent_id" class="form-select">
                                    <option value="">Select Parent Account</option>
                                </select>
                                <div class="text-danger small" id="parent_id_error"></div>
                            </div>

                            <div class="col-12" id="parentNameGroup" style="display:none">
                                <label class="form-label" for="parent_label">Parent Account</label>
                                <input type="text" id="parent_label" class="form-control" readonly>
                            </div>

                            <div class="col-12">
                                <div class="coa-preview">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1" for="account_code">Account Code</label>
                                            <input type="text" id="account_code" class="form-control" readonly placeholder="Auto">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1" for="account_type">Account Type</label>
                                            <input type="text" id="account_type" class="form-control" readonly placeholder="Auto">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1" for="normal_balance">Normal Balance</label>
                                            <input type="text" id="normal_balance" class="form-control" readonly placeholder="Auto">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label" for="account_name">Account Name <span class="text-danger">*</span></label>
                                <input type="text" id="account_name" name="account_name" class="form-control"
                                    maxlength="150" placeholder="e.g. NCC Bank-Ac-15413356">
                                <div class="text-danger small" id="account_name_error"></div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="account_status">Status</label>
                                <select id="account_status" name="status" class="form-select">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                                <div class="text-danger small" id="status_error"></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="opening_balance">Opening Balance</label>
                                <input type="number" step="0.01" min="0" id="opening_balance" name="opening_balance"
                                    class="form-control" value="0.00">
                                <div class="text-danger small" id="opening_balance_error"></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="opening_balance_entry_datetime">Opening Balance Date &amp; Time</label>
                                <input type="datetime-local" id="opening_balance_entry_datetime"
                                    name="opening_balance_entry_datetime" class="form-control">
                                <div class="text-danger small" id="opening_balance_entry_datetime_error"></div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="coaSaveBtn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        let coaTable;
        let coaModal;
        let coaParents = [];

        const coaRoutes = {
            store:    @json(route('admin.chart-of-accounts.store')),
            show:     @json(route('admin.chart-of-accounts.show', ['id' => '__ID__'])),
            update:   @json(route('admin.chart-of-accounts.update', ['id' => '__ID__'])),
            destroy:  @json(route('admin.chart-of-accounts.destroy', ['id' => '__ID__'])),
            status:   @json(route('admin.chart-of-accounts.status', ['id' => '__ID__'])),
            nextCode: @json(route('admin.chart-of-accounts.next-code', ['parentId' => '__ID__'])),
        };

        function coaUrl(name, id) {
            return coaRoutes[name].replace('__ID__', encodeURIComponent(id));
        }

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': @json(csrf_token()),
                'Accept': 'application/json'
            }
        });

        /* ---------- notifications ---------- */
        function coaNotify(type, message) {
            if (typeof toastr !== 'undefined') {
                toastr[type](message);
            } else {
                alert(message);
            }
        }

        function coaConfirm(options, onConfirm) {
            if (typeof Swal !== 'undefined') {
                Swal.fire(Object.assign({
                    icon: 'warning',
                    showCancelButton: true,
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                }, options)).then(function(result) {
                    if (result.isConfirmed) onConfirm();
                });
            } else if (confirm(options.text || options.title)) {
                onConfirm();
            }
        }

        /* ---------- form errors ---------- */
        function clearCoaErrors() {
            $('#coaForm .text-danger').text('');
            $('#coaForm .is-invalid').removeClass('is-invalid');
        }

        function showCoaErrors(errors) {
            clearCoaErrors();
            Object.keys(errors || {}).forEach(function(field) {
                $('#coaForm [name="' + field + '"]').addClass('is-invalid');
                $('#' + field + '_error').text(Array.isArray(errors[field]) ? errors[field][0] : errors[field]);
            });
        }

        function nowLocalInput() {
            const d = new Date();
            d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
            return d.toISOString().slice(0, 16);
        }

        /* ---------- parent dropdown (1st layers in bold) ---------- */
        function populateParents(selectedId) {
            const $p = $('#parent_id');

            $p.empty().append(new Option('Select Parent Account', '', false, false));

            coaParents.forEach(function(a) {
                const layer = Number(a.layer_level);
                let label = a.account_code + ' - ' + a.account_name;

                if (layer === 2 && a.root_name) {
                    label += ' (' + a.root_name + ')';
                }

                const $opt = $('<option>').val(a.id).text(label).attr('data-layer', layer);

                if (layer === 1) {
                    $opt.css('font-weight', 'bold');
                }

                $p.append($opt);
            });

            $p.val(selectedId ? String(selectedId) : '').trigger('change');
        }

        function initParentSelect2() {
            if (!$.fn.select2) {
                return; // native select with bold options is used as a fallback
            }

            $('#parent_id').select2({
                placeholder: 'Select Parent Account',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#coaModal'),
                templateResult: function(option) {
                    if (!option.id) return option.text;

                    const layer = Number($(option.element).data('layer'));

                    return $('<span>')
                        .addClass(layer === 1 ? 'coa-opt-root' : 'coa-opt-group')
                        .text(option.text);
                }
            });
        }

        function resetPreview() {
            $('#account_code, #account_type, #normal_balance').val('');
            $('#coaHint').text('Select a parent account. The code, type and normal balance are generated automatically.');
        }

        /* ---------- modal: add ---------- */
        function openAddModal(parentId) {
            $('#coaForm')[0].reset();
            clearCoaErrors();
            resetPreview();

            $('#account_id').val('');
            $('#coaModalTitle').text('Add Chart Of Account');
            $('#coaSaveBtn').prop('disabled', false).text('Save');

            $('#parentSelectGroup').show();
            $('#parentNameGroup').hide();
            $('#account_status').prop('disabled', false).val('Active');
            $('#opening_balance').val('0.00');

            populateParents(parentId || '');

            coaModal.show();
        }

        /* ---------- modal: edit ---------- */
        function openEditModal(id) {
            $.get(coaUrl('show', id)).done(function(response) {
                if (!response.status) {
                    coaNotify('error', response.message || 'Unable to load account.');
                    return;
                }

                const a = response.data;

                $('#coaForm')[0].reset();
                clearCoaErrors();

                $('#account_id').val(a.id);
                $('#account_code').val(a.account_code);
                $('#account_type').val(a.account_type);
                $('#normal_balance').val(a.normal_balance);
                $('#parent_label').val(a.parent_label);
                $('#account_name').val(a.account_name);
                $('#account_status').val(a.status).prop('disabled', Number(a.layer_level) === 1);
                $('#opening_balance').val(a.opening_balance);
                $('#opening_balance_entry_datetime').val(a.opening_balance_entry_datetime);

                $('#parentSelectGroup').hide();
                $('#parentNameGroup').show();

                $('#coaHint').text(
                    a.layer_label + ' account.' +
                    (Number(a.layer_level) === 1 ? ' 1st layer accounts cannot be deactivated or deleted.' : '')
                );

                $('#coaModalTitle').text('Edit Chart Of Account');
                $('#coaSaveBtn').prop('disabled', false).text('Update');

                coaModal.show();
            }).fail(function(xhr) {
                coaNotify('error', xhr.responseJSON?.message || 'Unable to load account.');
            });
        }

        /* ---------- status / delete ---------- */
        function toggleStatus(id, name, current) {
            const next = current === 'Active' ? 'Inactive' : 'Active';

            coaConfirm({
                title: 'Mark as ' + next + '?',
                text: '"' + name + '" will be marked ' + next + '.',
                confirmButtonText: 'Yes, mark ' + next
            }, function() {
                $.post(coaUrl('status', id)).done(function(response) {
                    coaNotify(response.status ? 'success' : 'error', response.message);
                    coaTable.ajax.reload(null, false);
                }).fail(function(xhr) {
                    coaNotify('error', xhr.responseJSON?.message || 'Unable to change status.');
                });
            });
        }

        function deleteAccount(id, name) {
            coaConfirm({
                title: 'Delete account?',
                text: 'Delete "' + name + '"? It must have no child accounts, no journal history and a zero opening balance.',
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, delete'
            }, function() {
                $.post(coaUrl('destroy', id)).done(function(response) {
                    coaNotify(response.status ? 'success' : 'error', response.message);
                    coaTable.ajax.reload(null, false);
                }).fail(function(xhr) {
                    coaNotify('error', xhr.responseJSON?.message || 'Unable to delete account.');
                });
            });
        }

        /* ---------- ready ---------- */
        $(document).ready(function() {
            coaModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('coaModal'));
            initParentSelect2();

            // Counts + parent list come back in the same response as the rows.
            $('#coaTable').on('xhr.dt', function(e, settings, json) {
                if (!json) return;

                const c = json.counts || {};
                $('#rootAccountCount').text(c.roots ?? 0);
                $('#groupAccountCount').text(c.groups ?? 0);
                $('#ledgerAccountCount').text(c.ledgers ?? 0);
                $('#totalAccountCount').text((c.roots ?? 0) + (c.groups ?? 0) + (c.ledgers ?? 0));

                coaParents = json.parents || [];
            });

            // Keep SL. sequential after search / filter / paging.
            $('#coaTable').on('draw.dt', function(e, settings) {
                const api = new $.fn.dataTable.Api(settings);
                const start = api.page.info().start;

                api.column(0, { page: 'current' }).nodes().each(function(cell, i) {
                    cell.innerHTML = start + i + 1;
                });
            });

            /* ===== the only DataTable call ===== */
            coaTable = $('#coaTable').DataTable({

                ajax: "{{ route('admin.chart-of-accounts.index') }}",

                processing: true,
            });

            /* ----- filters (column indexes: 3 layer, 4 type, 8 status) ----- */
            function columnFilter(col, value) {
                coaTable.column(col).search(value ? '^' + $.fn.dataTable.util.escapeRegex(value) + '$' : '', true, false).draw();
            }

            $('#coaLayerFilter').on('change', function() { columnFilter(3, this.value); });
            $('#coaTypeFilter').on('change', function() { columnFilter(4, this.value); });
            $('#coaStatusFilter').on('change', function() { columnFilter(8, this.value); });

            $('#resetCoaFilters').on('click', function() {
                $('#coaLayerFilter, #coaTypeFilter, #coaStatusFilter').val('');
                coaTable.search('').columns().search('').draw();
            });

            $('#refreshCoaBtn').on('click', function() { coaTable.ajax.reload(null, false); });
            $('#addCoaBtn').on('click', function() { openAddModal(); });

            /* ----- row actions (delegated) ----- */
            $('#coaTable').on('click', '.coa-edit', function() {
                openEditModal($(this).data('id'));
            });

            $('#coaTable').on('click', '.coa-add-child', function() {
                openAddModal($(this).data('id'));
            });

            $('#coaTable').on('click', '.coa-delete', function() {
                deleteAccount($(this).data('id'), $(this).attr('data-name') || '');
            });

            $('#coaTable').on('click', '.coa-status-toggle', function() {
                toggleStatus($(this).data('id'), $(this).attr('data-name') || '', $(this).attr('data-status'));
            });

            /* ----- parent change => preview generated code ----- */
            $('#parent_id').on('change', function() {
                if ($('#account_id').val()) return; // edit mode

                const parentId = $(this).val();

                if (!parentId) {
                    resetPreview();
                    return;
                }

                $.get(coaUrl('nextCode', parentId)).done(function(response) {
                    if (!response.status) return;

                    const d = response.data;
                    $('#account_code').val(d.account_code);
                    $('#account_type').val(d.account_type);
                    $('#normal_balance').val(d.normal_balance);
                    $('#coaHint').text('This will create a ' + d.layer_label + ' account.');
                    $('#parent_id_error').text('');
                }).fail(function(xhr) {
                    resetPreview();
                    showCoaErrors(xhr.responseJSON?.errors || { parent_id: [xhr.responseJSON?.message || 'Invalid parent.'] });
                });
            });

            // Auto-fill the date when an opening balance is typed.
            $('#opening_balance').on('input', function() {
                if (parseFloat(this.value) > 0 && !$('#opening_balance_entry_datetime').val()) {
                    $('#opening_balance_entry_datetime').val(nowLocalInput());
                }
            });

            /* ----- create / update ----- */
            $('#coaForm').on('submit', function(e) {
                e.preventDefault();
                clearCoaErrors();

                const id = $('#account_id').val();
                const data = {
                    account_name: $.trim($('#account_name').val()),
                    opening_balance: $('#opening_balance').val() || 0,
                    opening_balance_entry_datetime: $('#opening_balance_entry_datetime').val()
                };

                if (!$('#account_status').prop('disabled')) {
                    data.status = $('#account_status').val();
                }

                if (!data.account_name) {
                    showCoaErrors({ account_name: ['Account name is required.'] });
                    return;
                }

                if (!id) {
                    data.parent_id = $('#parent_id').val();

                    if (!data.parent_id) {
                        showCoaErrors({ parent_id: ['Please select a parent account.'] });
                        return;
                    }
                }

                const $btn = $('#coaSaveBtn');
                const label = id ? 'Update' : 'Save';

                $btn.prop('disabled', true).text(id ? 'Updating...' : 'Saving...');

                $.ajax({
                    url: id ? coaUrl('update', id) : coaRoutes.store,
                    type: 'POST',
                    data: data
                }).done(function(response) {
                    if (!response.status) {
                        coaNotify('error', response.message || 'Unable to save account.');
                        return;
                    }

                    coaModal.hide();
                    coaNotify('success', response.message);
                    coaTable.ajax.reload(null, false);
                }).fail(function(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON?.errors) {
                        showCoaErrors(xhr.responseJSON.errors);
                    } else {
                        coaNotify('error', xhr.responseJSON?.message || 'Unable to save account.');
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text(label);
                });
            });
        });
    </script>
@endpush
