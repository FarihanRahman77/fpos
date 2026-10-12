@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Journals
@endsection

@section('content')
    <div class="container-fluid">

        <div class="d-flex align-items-center mb-3">
            <div>
                <h4 class="mb-0">Journals</h4>
                <small class="text-muted">Manual debit / credit entries. Total debit must equal total credit.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button type="button" class="btn btn-primary" onclick="addJournal()">
                    <i class="fas fa-plus"></i> Add Journal
                </button>
            </div>
        </div>

        {{-- Summary --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i1"><i class="fas fa-book"></i></div>
                    <div><div class="text-muted small">Total (filtered)</div><h5 class="mb-0" id="sumFilteredTotal">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-list-ol"></i></div>
                    <div><div class="text-muted small">Journals (filtered)</div><h5 class="mb-0" id="sumFilteredCount">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-calendar-alt"></i></div>
                    <div><div class="text-muted small">This Month</div><h5 class="mb-0" id="sumMonthTotal">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-calendar-day"></i></div>
                    <div><div class="text-muted small">Today</div><h5 class="mb-0" id="sumTodayTotal">0.00</h5></div>
                </div></div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card acc-card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label" for="filterFrom">From</label>
                        <input type="date" id="filterFrom" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filterTo">To</label>
                        <input type="date" id="filterTo" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filterGroup">Group</label>
                        <select id="filterGroup" class="form-select">
                            <option value="">All groups</option>
                            @foreach ($groups->groupBy('account_type') as $type => $items)
                                <optgroup label="{{ $type }}">
                                    @foreach ($items as $group)
                                        <option value="{{ $group->id }}">{{ $group->account_code }} - {{ $group->account_name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filterAccount">Account</label>
                        <select id="filterAccount" class="form-select" disabled>
                            <option value="">Select group first</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="button" class="btn btn-primary flex-fill" id="applyFilters" title="Apply filters">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary flex-fill" id="resetFilters" title="Reset filters">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="journalTable" class="table table-hover align-middle w-100 acc-table"
                        data-page-length="25" data-order="[]" data-auto-width="false">
                        <thead>
                            <tr>
                                <th data-orderable="false" data-searchable="false" width="5%">SL.</th>
                                <th>Journal No</th>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Accounts</th>
                                <th class="text-end">Amount</th>
                                <th data-orderable="false" data-searchable="false" width="12%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Add / Edit modal --}}
    <div class="modal fade" id="journalModal" tabindex="-1" aria-labelledby="journalModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <form id="journalForm" novalidate>
                    @csrf
                    <input type="hidden" name="id" id="journal_id">

                    <div class="modal-header">
                        <h5 class="modal-title" id="journalModalTitle">Add Journal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="acc-section mb-3">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label" for="journal_date">Date <span class="text-danger">*</span></label>
                                    <input type="date" name="journal_date" id="journal_date" class="form-control">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="reference_no">Reference / Voucher No</label>
                                    <input type="text" name="reference_no" id="reference_no" class="form-control" maxlength="100" placeholder="Reference or voucher number">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="remarks">Narration</label>
                                    <input type="text" name="remarks" id="remarks" class="form-control" maxlength="1000" placeholder="What this entry is for (optional)">
                                    <div class="text-danger small field-error"></div>
                                </div>
                            </div>
                        </div>

                        <div class="acc-section">
                            <div class="d-flex align-items-center mb-2">
                                <h6 class="mb-0">Journal Lines</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="addLineBtn">
                                    <i class="fas fa-plus"></i> Add Line
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 acc-lines" style="min-width: 960px">
                                    <thead>
                                        <tr>
                                            <th width="4%">#</th>
                                            <th width="22%">Group</th>
                                            <th width="22%">Account</th>
                                            <th>Description</th>
                                            <th width="13%" class="text-end">Debit</th>
                                            <th width="13%" class="text-end">Credit</th>
                                            <th width="4%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="linesBody"></tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-end fw-semibold pt-3">Total</td>
                                            <td class="pt-2"><input type="text" id="total_debit" class="form-control text-end acc-grand" readonly value="0.00" placeholder="0.00"></td>
                                            <td class="pt-2"><input type="text" id="total_credit" class="form-control text-end acc-grand" readonly value="0.00" placeholder="0.00"></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="7" class="text-end small" id="balanceNote"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="text-danger small mt-1" id="lines_error"></div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="saveBtn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- View modal --}}
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalTitle">Journal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        let journalTable, journalModal, viewModal;
        let lineIndex = 0;

        const groups = @json($groups);
        const listUrl = "{{ route('admin.journal.list') }}";

        /* ---------- lines ---------- */
        function groupOptions() {
            const byType = {};
            groups.forEach(function(g) {
                (byType[g.account_type] = byType[g.account_type] || []).push(g);
            });

            return Object.keys(byType).map(function(type) {
                return '<optgroup label="' + Voucher.esc(type) + '">' + byType[type].map(function(g) {
                    return '<option value="' + g.id + '">' + Voucher.esc(g.account_code + ' - ' + g.account_name) + '</option>';
                }).join('') + '</optgroup>';
            }).join('');
        }

        function lineHtml(i) {
            return '<tr>' +
                '<td class="text-muted line-no pt-2"></td>' +
                '<td><select name="details[' + i + '][account_group_id]" class="form-select form-select-sm line-group">' +
                    '<option value="">Select group</option>' + groupOptions() + '</select>' +
                    '<div class="text-danger small field-error"></div></td>' +
                '<td><select name="details[' + i + '][account_id]" class="form-select form-select-sm line-account" disabled>' +
                    '<option value="">Select group first</option></select>' +
                    '<div class="text-danger small field-error"></div></td>' +
                '<td><input type="text" name="details[' + i + '][description]" class="form-control form-control-sm line-description" maxlength="255" placeholder="Description (optional)">' +
                    '<div class="text-danger small field-error"></div></td>' +
                '<td><input type="number" step="0.01" min="0" name="details[' + i + '][debit]" class="form-control form-control-sm text-end line-debit" placeholder="0.00">' +
                    '<div class="text-danger small field-error"></div></td>' +
                '<td><input type="number" step="0.01" min="0" name="details[' + i + '][credit]" class="form-control form-control-sm text-end line-credit" placeholder="0.00">' +
                    '<div class="text-danger small field-error"></div></td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-danger remove-line" title="Remove line"><i class="fas fa-times"></i></button></td>' +
                '</tr>';
        }

        function renumberLines() {
            $('#linesBody tr').each(function(n) {
                $(this).find('.line-no').text(n + 1);
            });
        }

        function calcTotals() {
            let debit = 0, credit = 0;

            $('#linesBody .line-debit').each(function() { debit += parseFloat(this.value) || 0; });
            $('#linesBody .line-credit').each(function() { credit += parseFloat(this.value) || 0; });

            $('#total_debit').val(debit.toFixed(2));
            $('#total_credit').val(credit.toFixed(2));

            const diff = Math.round((debit - credit) * 100) / 100;

            if (debit === 0 && credit === 0) {
                $('#balanceNote').html('');
            } else if (diff === 0) {
                $('#balanceNote').html('<span class="text-success"><i class="fas fa-check-circle"></i> Balanced</span>');
            } else {
                $('#balanceNote').html('<span class="text-danger"><i class="fas fa-exclamation-circle"></i> Difference: ' + Voucher.money(Math.abs(diff)) + (diff > 0 ? ' (debit is higher)' : ' (credit is higher)') + '</span>');
            }
        }

        // data (optional) = a saved line, used by the edit modal.
        function addLine(data) {
            const i = lineIndex++;
            const $tr = $(lineHtml(i));

            $('#linesBody').append($tr);
            Voucher.initSelect2($tr.find('select'), $('#journalModal'));
            renumberLines();

            if (!data) {
                return $.Deferred().resolve().promise();
            }

            Voucher.setValue($tr.find('.line-group'), data.account_group_id, data.group_label);
            $tr.find('.line-description').val(data.description || '');
            $tr.find('.line-debit').val(data.debit);
            $tr.find('.line-credit').val(data.credit);

            return Voucher.loadAccounts($tr.find('.line-account'), data.account_group_id, data.account_id, data.account_label);
        }

        /* ---------- add / edit ---------- */
        function resetForm() {
            $('#journalForm')[0].reset();
            Voucher.clearErrors($('#journalForm'));
            $('#lines_error').text('');
            $('#journal_id').val('');

            Voucher.destroySelect2($('#linesBody').find('select'));
            $('#linesBody').empty();
            lineIndex = 0;
        }

        function addJournal() {
            resetForm();

            $('#journal_date').val(Voucher.todayLocal());
            $('#journalModalTitle').text('Add Journal');
            $('#saveBtn').prop('disabled', false).text('Save');

            addLine();
            addLine();
            calcTotals();
            journalModal.show();
        }

        function editJournal(id) {
            $.ajax({
                url: "{{ route('admin.journal.show') }}",
                type: 'GET',
                data: { id: id },
                success: function(res) {
                    if (!res.status) {
                        toastr.error(res.message || 'Unable to load journal.');
                        return;
                    }

                    const d = res.data;
                    resetForm();

                    $('#journal_id').val(d.id);
                    $('#journal_date').val(d.journal_date);
                    $('#reference_no').val(d.reference_no || '');
                    $('#remarks').val(d.remarks || '');

                    const jobs = (d.details || []).map(function(line) { return addLine(line); });

                    $.when.apply($, jobs).always(function() {
                        calcTotals();
                        $('#journalModalTitle').text('Edit Journal ' + d.journal_no);
                        $('#saveBtn').prop('disabled', false).text('Update');
                        journalModal.show();
                    });
                },
                error: function(xhr) {
                    Voucher.ajaxError(xhr, 'Unable to load journal.');
                }
            });
        }

        /* ---------- view ---------- */
        function viewJournal(id) {
            $.ajax({
                url: "{{ route('admin.journal.show') }}",
                type: 'GET',
                data: { id: id },
                success: function(res) {
                    if (!res.status) {
                        toastr.error(res.message || 'Unable to load journal.');
                        return;
                    }

                    const d = res.data;
                    const rows = (d.details || []).map(function(l, n) {
                        return '<tr>' +
                            '<td>' + (n + 1) + '</td>' +
                            '<td>' + Voucher.esc(l.account_label) + '<div class="text-muted small">' + Voucher.esc(l.group_label) + '</div></td>' +
                            '<td>' + Voucher.esc(l.description || '-') + '</td>' +
                            '<td class="text-end">' + (l.debit ? Voucher.money(l.debit) : '') + '</td>' +
                            '<td class="text-end">' + (l.credit ? Voucher.money(l.credit) : '') + '</td>' +
                            '</tr>';
                    }).join('');

                    $('#viewModalTitle').text('Journal ' + d.journal_no);
                    $('#viewBody').html(
                        '<dl class="row acc-view mb-3">' +
                            '<dt class="col-sm-3">Date</dt><dd class="col-sm-9">' + Voucher.esc(d.journal_date) + '</dd>' +
                            '<dt class="col-sm-3">Reference</dt><dd class="col-sm-9">' + Voucher.esc(d.reference_no || '-') + '</dd>' +
                            '<dt class="col-sm-3">Narration</dt><dd class="col-sm-9">' + Voucher.esc(d.remarks || '-') + '</dd>' +
                        '</dl>' +
                        '<div class="table-responsive"><table class="table table-sm acc-table">' +
                            '<thead><tr><th>#</th><th>Account</th><th>Description</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>' +
                            '<tbody>' + rows + '</tbody>' +
                            '<tfoot><tr class="fw-semibold"><td colspan="3" class="text-end">Total</td>' +
                                '<td class="text-end">' + Voucher.money(d.total_debit) + '</td>' +
                                '<td class="text-end">' + Voucher.money(d.total_credit) + '</td></tr></tfoot>' +
                        '</table></div>'
                    );
                    viewModal.show();
                },
                error: function(xhr) {
                    Voucher.ajaxError(xhr, 'Unable to load journal.');
                }
            });
        }

        /* ---------- delete ---------- */
        function deleteJournal(id) {
            Voucher.confirmDelete(
                'Delete this journal?',
                'Its amounts will be taken back out of the account balances.',
                "{{ route('admin.journal.delete') }}",
                id,
                function() { journalTable.ajax.reload(null, false); }
            );
        }

        /* ---------- ready ---------- */
        $(document).ready(function() {
            journalModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('journalModal'));
            viewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal'));

            Voucher.initSelect2($('#filterGroup, #filterAccount'));

            $('#journalTable').on('xhr.dt', function(e, settings, json) {
                if (!json || !json.summary) return;

                $('#sumFilteredTotal').text(json.summary.filtered_total);
                $('#sumFilteredCount').text(json.summary.filtered_count);
                $('#sumMonthTotal').text(json.summary.month_total);
                $('#sumTodayTotal').text(json.summary.today_total);
            });

            Voucher.keepSerial($('#journalTable'));

            journalTable = $('#journalTable').DataTable({
                ajax: listUrl,
                processing: true
            });

            /* ----- filters ----- */
            $('#filterGroup').on('change', function() {
                Voucher.loadAccounts($('#filterAccount'), this.value, '', '', 'Select group first').then(function() {
                    $('#filterAccount option:first').text('All accounts');
                    Voucher.refresh($('#filterAccount'));
                });
            });

            $('#applyFilters').on('click', function() {
                const params = $.param({
                    from: $('#filterFrom').val(),
                    to: $('#filterTo').val(),
                    account_id: $('#filterAccount').val() || ''
                });

                journalTable.ajax.url(listUrl + '?' + params).load();
            });

            $('#resetFilters').on('click', function() {
                $('#filterFrom, #filterTo').val('');
                $('#filterGroup').val('').trigger('change');
                journalTable.ajax.url(listUrl).load();
            });

            $('#refreshBtn').on('click', function() {
                journalTable.ajax.reload(null, false);
            });

            /* ----- lines ----- */
            $('#addLineBtn').on('click', function() { addLine(); });

            $('#linesBody').on('change', '.line-group', function() {
                Voucher.loadAccounts($(this).closest('tr').find('.line-account'), this.value);
            });

            // A line is either a debit or a credit: typing in one clears the other.
            $('#linesBody').on('input', '.line-debit, .line-credit', function() {
                if (parseFloat(this.value) > 0) {
                    const other = $(this).hasClass('line-debit') ? '.line-credit' : '.line-debit';
                    $(this).closest('tr').find(other).val('');
                }
                calcTotals();
            });

            $('#linesBody').on('click', '.remove-line', function() {
                const $tr = $(this).closest('tr');

                if ($('#linesBody tr').length <= 2) {
                    toastr.warning('A journal needs at least two lines.');
                    return;
                }

                Voucher.destroySelect2($tr.find('select'));
                $tr.remove();
                renumberLines();
                calcTotals();
            });

            /* ----- save (create or update) ----- */
            $('#journalForm').on('submit', function(e) {
                e.preventDefault();

                const $form = $(this);
                const $button = $('#saveBtn');
                const label = $('#journal_id').val() ? 'Update' : 'Save';

                Voucher.clearErrors($form);
                $('#lines_error').text('');

                $.ajax({
                    url: "{{ route('admin.journal.store') }}",
                    type: 'POST',
                    data: $form.serialize(),
                    beforeSend: function() {
                        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
                    },
                    success: function(res) {
                        if (res.status) {
                            journalModal.hide();
                            toastr.success(res.message);
                            journalTable.ajax.reload(null, false);
                        } else {
                            toastr.error(res.message || 'Unable to save journal.');
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($form, xhr.responseJSON.errors, $('#lines_error'));
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save journal.');
                        }
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(label);
                    }
                });
            });
        });
    </script>
@endpush
