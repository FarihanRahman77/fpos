{{-- Shared styles + JS helpers for the Journal, Bill and Amount Transfer pages. --}}
@push('styles')
    <style>
        .acc-stat, .acc-card { border: 0; border-radius: 14px; box-shadow: 0 2px 12px rgba(30, 41, 59, .07); }
        .acc-stat .icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; }
        .acc-stat .i1 { background: #e0e7ff; color: #4338ca; }
        .acc-stat .i2 { background: #fee2e2; color: #b91c1c; }
        .acc-stat .i3 { background: #fef3c7; color: #b45309; }
        .acc-stat .i4 { background: #dcfce7; color: #15803d; }
        .acc-table thead th { background: #f8fafc; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; white-space: nowrap; }
        .acc-table tbody td { vertical-align: middle; }
        .coa-code { font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: .8rem; background: #f1f5f9; padding: 1px 6px; border-radius: 6px; }
        .acc-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; }
        .acc-lines thead th { font-size: .75rem; text-transform: uppercase; color: #64748b; }
        .acc-lines td { vertical-align: top; }
        .acc-grand { font-size: 1.05rem; font-weight: 700; }
        .acc-view dt { color: #64748b; font-weight: 500; }

        .select2-container { width: 100% !important; }
        .acc-lines .select2-container { min-width: 160px; }
        .select2-container--open .select2-dropdown, .select2-dropdown { z-index: 1065; }
    </style>
@endpush

@push('scripts')
    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': "{{ csrf_token() }}",
                'Accept': 'application/json'
            }
        });

        const Voucher = {
            ledgersUrl: "{{ route('admin.coa.ledgers') }}",
            cache: {},

            /* ---------- formatting ---------- */
            esc: function(text) {
                return $('<div>').text(text == null ? '' : String(text)).html();
            },

            money: function(value) {
                return (parseFloat(value) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            todayLocal: function() {
                const d = new Date();
                d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
                return d.toISOString().slice(0, 10);
            },

            ajaxError: function(xhr, fallback) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.message) || fallback || 'Something went wrong.');
            },

            /* ---------- select2 ---------- */
            // $parent = the modal, so the dropdown is not clipped and its search works.
            initSelect2: function($els, $parent) {
                $els.each(function() {
                    const $s = $(this);
                    if ($s.hasClass('select2-hidden-accessible')) return;

                    const opts = { theme: 'bootstrap-5', width: '100%' };
                    if ($parent) opts.dropdownParent = $parent;

                    $s.select2(opts);
                });
            },

            // 'change.select2' redraws Select2 without firing our own 'change' handlers.
            refresh: function($s) {
                $s.trigger('change.select2');
            },

            destroySelect2: function($els) {
                $els.each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
                });
            },

            /* ---------- validation errors ---------- */
            clearErrors: function($form) {
                $form.find('.field-error').text('');
                $form.find('.is-invalid').removeClass('is-invalid');
            },

            // key "details.0.debit" -> input name "details[0][debit]"; unmatched keys go to $fallback.
            showErrors: function($form, errors, $fallback) {
                Voucher.clearErrors($form);

                Object.keys(errors || {}).forEach(function(key) {
                    const parts = key.split('.');
                    const name = parts[0] + parts.slice(1).map(function(p) { return '[' + p + ']'; }).join('');
                    const $input = $form.find('[name="' + name + '"]');
                    const message = errors[key][0];

                    if ($input.length) {
                        $input.addClass('is-invalid');
                        // every field sits in its own wrapper together with its .field-error
                        $input.parent().find('.field-error').first().text(message);
                    } else if ($fallback) {
                        $fallback.text(message);
                    }
                });
            },

            /* ---------- group -> account dropdowns ---------- */
            ensureOption: function($select, id, label) {
                if (id && label && $select.find('option[value="' + id + '"]').length === 0) {
                    $select.append(new Option(label + ' (inactive)', id));
                }
            },

            setValue: function($select, id, label) {
                Voucher.ensureOption($select, id, label);
                $select.val(id ? String(id) : '');
                Voucher.refresh($select);
            },

            loadChildren: function(parentId) {
                if (Voucher.cache[parentId]) {
                    return $.Deferred().resolve(Voucher.cache[parentId]).promise();
                }

                const deferred = $.Deferred();

                $.ajax({
                    url: Voucher.ledgersUrl,
                    type: 'GET',
                    data: { parent_id: parentId },
                    success: function(res) {
                        Voucher.cache[parentId] = res.data || [];
                        deferred.resolve(Voucher.cache[parentId]);
                    },
                    error: function(xhr) {
                        Voucher.ajaxError(xhr, 'Unable to load accounts.');
                        deferred.resolve([]);
                    }
                });

                return deferred.promise();
            },

            // Fills $select with the 3rd layer accounts of groupId.
            loadAccounts: function($select, groupId, selectedId, fallbackLabel, placeholder) {
                if (!groupId) {
                    $select.empty().append(new Option(placeholder || 'Select group first', '')).prop('disabled', true);
                    Voucher.refresh($select);
                    return $.Deferred().resolve().promise();
                }

                $select.prop('disabled', true).empty().append(new Option('Loading...', ''));
                Voucher.refresh($select);

                return Voucher.loadChildren(groupId).then(function(items) {
                    $select.empty().append(new Option('Select account', ''));

                    (items || []).forEach(function(a) {
                        $select.append(new Option(a.account_code + ' - ' + a.account_name, a.id));
                    });

                    Voucher.ensureOption($select, selectedId, fallbackLabel);
                    $select.val(selectedId ? String(selectedId) : '').prop('disabled', false);
                    Voucher.refresh($select);
                });
            },

            /* ---------- confirm + delete ---------- */
            confirmDelete: function(title, text, url, id, onDone) {
                const run = function() {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: { id: id },
                        success: function(res) {
                            if (res.status) {
                                toastr.success(res.message);
                                if (onDone) onDone(res);
                            } else {
                                toastr.error(res.message || 'Unable to delete.');
                            }
                        },
                        error: function(xhr) {
                            Voucher.ajaxError(xhr, 'Unable to delete.');
                        }
                    });
                };

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: title,
                        text: text,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: 'Yes, delete',
                        reverseButtons: true
                    }).then(function(result) {
                        if (result.isConfirmed) run();
                    });
                } else if (confirm(title + ' ' + text)) {
                    run();
                }
            },

            /* ---------- DataTable helpers ---------- */
            // Keeps SL. sequential after sorting / paging.
            keepSerial: function($table) {
                $table.on('draw.dt', function(e, settings) {
                    const api = new $.fn.dataTable.Api(settings);
                    const start = api.page.info().start;

                    api.column(0, { page: 'current' }).nodes().each(function(cell, i) {
                        cell.innerHTML = start + i + 1;
                    });
                });
            }
        };

        $(document).on('select2:open', function() {
            const search = document.querySelector('.select2-container--open .select2-search__field');
            if (search) search.focus();
        });
    </script>
@endpush
