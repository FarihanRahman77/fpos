{{-- Send-by-email dialog + the Quote actions shared by the list, view and customer-log pages. --}}
<div class="modal fade" id="sendModal" tabindex="-1" aria-labelledby="sendTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="sendForm" autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title" id="sendTitle">Send Quotation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="send_to">To <span class="text-danger">*</span></label>
                            <input type="email" name="to" id="send_to" class="form-control" placeholder="customer@example.com">
                            <div class="text-danger small field-error"></div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="send_cc">Cc</label>
                            <input type="email" name="cc" id="send_cc" class="form-control">
                            <div class="text-danger small field-error"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="send_subject">Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" id="send_subject" class="form-control" maxlength="200">
                            <div class="text-danger small field-error"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="send_message">Message <span class="text-danger">*</span></label>
                            <textarea name="message" id="send_message" class="form-control" rows="8" maxlength="5000"></textarea>
                            <div class="form-text">The quotation PDF (current revision) is attached.</div>
                            <div class="text-danger small field-error"></div>
                        </div>
                    </div>
                    <div class="text-danger small mt-2" id="send_error"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-success me-auto" id="sendWhatsapp"><i class="fab fa-whatsapp"></i> WhatsApp instead</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="sendSubmit"><i class="fas fa-paper-plane"></i> Send Email</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        const Quote = {
            base: "{{ url('admin/quotations') }}",
            table: null,
            current: null,
            reload: function() { location.reload(); },

            send: function(id) {
                Quote.current = id;
                $.ajax({
                    url: Quote.base + '/' + id + '/send-info',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const $f = $('#sendForm');
                        $f[0].reset();
                        Voucher.clearErrors($f);
                        $('#send_error').text('');
                        $('#sendTitle').text('Send ' + response.no);
                        $('#send_to').val(response.to || '');
                        $('#send_subject').val(response.subject);
                        $('#send_message').val(response.message);
                        Quote.info = response;
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('sendModal')).show();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load the quotation.');
                    }
                });
            },

            // Opens WhatsApp with a summary and logs the share. The PDF is downloaded so it can be attached.
            share: function(id, info) {
                const go = function(res) {
                    const url = 'https://wa.me/' + (res.phone || '') + '?text=' + encodeURIComponent(res.whatsapp);
                    window.open(url, '_blank');
                    window.open(Quote.base + '/' + id + '/pdf?download=1', '_blank');
                    $.ajax({
                        url: Quote.base + '/' + id + '/share',
                        type: 'POST',
                        dataType: 'json',
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            toastr.success('Shared on WhatsApp — attach the downloaded PDF in the chat.');
                            Quote.reload();
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                        }
                    });
                };
                if (info) return go(info);
                $.ajax({
                    url: Quote.base + '/' + id + '/send-info',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        go(response);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load the quotation.');
                    }
                });
            },

            duplicate: function(id) {
                Swal.fire({ title: 'Copy this quotation?', text: 'A new draft is created with the same products and prices (today\'s date).',
                    icon: 'question', showCancelButton: true, confirmButtonText: 'Copy', reverseButtons: true })
                    .then(function(r) {
                        if (!r.isConfirmed) return;
                        $.ajax({
                            url: Quote.base + '/' + id + '/duplicate',
                            type: 'POST',
                            dataType: 'json',
                            success: function(response) {
                                // alert(JSON.stringify(response));
                                toastr.success(response.message);
                                window.location.href = response.redirect;
                            },
                            error: function(xhr) {
                                // alert(JSON.stringify(xhr));
                                Voucher.ajaxError(xhr, 'Unable to copy.');
                            }
                        });
                    });
            },

            setStatus: function(id, status) {
                Swal.fire({ title: 'Mark as ' + status + '?', input: 'text', inputPlaceholder: 'Note (optional)', showCancelButton: true,
                    confirmButtonText: 'Mark ' + status, reverseButtons: true })
                    .then(function(r) {
                        if (!r.isConfirmed) return;
                        $.ajax({
                            url: Quote.base + '/' + id + '/status',
                            type: 'POST',
                            dataType: 'json',
                            data: { quotation_status: status, note: r.value || '' },
                            success: function(response) {
                                // alert(JSON.stringify(response));
                                toastr.success(response.message);
                                Quote.reload();
                            },
                            error: function(xhr) {
                                // alert(JSON.stringify(xhr));
                                Voucher.ajaxError(xhr, 'Unable to update.');
                            }
                        });
                    });
            },

            remove: function(id) {
                Swal.fire({ title: 'Delete this quotation?', text: 'Its history stays in the customer log.', icon: 'warning', showCancelButton: true,
                    confirmButtonColor: 'var(--color-danger)', confirmButtonText: 'Delete', reverseButtons: true })
                    .then(function(r) {
                        if (!r.isConfirmed) return;
                        $.ajax({
                            url: Quote.base + '/' + id + '/delete',
                            type: 'POST',
                            dataType: 'json',
                            success: function(response) {
                                // alert(JSON.stringify(response));
                                toastr.success(response.message);
                                Quote.reload();
                            },
                            error: function(xhr) {
                                // alert(JSON.stringify(xhr));
                                Voucher.ajaxError(xhr, 'Unable to delete.');
                            }
                        });
                    });
            }
        };

        $(document).ready(function() {
            $('#sendWhatsapp').on('click', function() {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('sendModal')).hide();
                Quote.share(Quote.current, Quote.info);
            });

            $('#sendForm').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#sendSubmit').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Sending...');
                $.ajax({
                    url: Quote.base + '/' + Quote.current + '/send',
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('sendModal')).hide();
                        Quote.reload();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($('#sendForm'), xhr.responseJSON.errors, $('#send_error'));
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            $('#send_error').text(xhr.responseJSON.message);
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to send.');
                        }
                    },
                    complete: function() { $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send Email'); }
                });
            });
        });
    </script>
@endpush
