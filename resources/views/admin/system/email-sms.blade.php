@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Email & SMS
@endsection

@push('styles')
    <style>
        .sys-hint { font-size: var(--text-caption); color: var(--color-text-muted); }
        .sys-chip {
            display: inline-block; margin: 0 var(--space-xxs) var(--space-xxs) 0; padding: var(--space-xxs) var(--space-sm);
            border-radius: var(--radius-pill); background: var(--color-accent-soft); color: var(--color-accent-ink);
            font-family: var(--font-mono); font-size: var(--text-micro); cursor: pointer; border: 0;
        }
        .sys-preview {
            padding: var(--space-md); border-radius: var(--radius-md); background: var(--color-surface-soft);
            border: 1px solid var(--color-border); font-size: var(--text-caption); white-space: pre-wrap;
        }
        .sys-result { font-size: var(--text-caption); }
        .sys-result.ok { color: var(--color-success); }
        .sys-result.bad { color: var(--color-danger); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Email &amp; SMS</h4>
                <small class="text-muted">Outgoing email (SMTP) used for quotations and other mail, and the BulkSMSBD gateway for invoice SMS and due reminders.</small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <a href="{{ route('admin.system.sms.due') }}" class="btn btn-outline-secondary"><i class="fas fa-bell"></i> Due Reminders</a>
                <a href="{{ route('admin.system.sms.log') }}" class="btn btn-outline-secondary"><i class="fas fa-list"></i> SMS Log</a>
            </div>
        </div>

        <div class="row g-3">
            {{-- Email --}}
            <div class="col-xl-6">
                <div class="card acc-card h-100">
                    <div class="card-body">
                        <form id="mailForm" autocomplete="off" novalidate>
                            <div class="d-flex align-items-center mb-3">
                                <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Email (SMTP)</h5>
                                <div class="form-check form-switch ms-auto mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="mail_enabled" name="mail_enabled" value="1" @checked($s['mail_enabled'] === '1')>
                                    <label class="form-check-label" for="mail_enabled">Use these settings</label>
                                </div>
                            </div>
                            <p class="sys-hint">When this is off, the server's .env mail settings are used. Gmail: host smtp.gmail.com, port 587, TLS, and an App Password (not your normal password).</p>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label" for="mail_host">SMTP Host</label>
                                    <input type="text" name="mail_host" id="mail_host" class="form-control" value="{{ $s['mail_host'] }}" placeholder="smtp.gmail.com">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="mail_port">Port</label>
                                    <input type="number" name="mail_port" id="mail_port" class="form-control" value="{{ $s['mail_port'] }}" min="1" max="65535">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="mail_username">Username</label>
                                    <input type="text" name="mail_username" id="mail_username" class="form-control" value="{{ $s['mail_username'] }}" autocomplete="off">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="mail_password">Password</label>
                                    <input type="password" name="mail_password" id="mail_password" class="form-control" autocomplete="new-password"
                                        placeholder="{{ $s['mail_password_set'] ? 'Saved — leave blank to keep' : 'Not set' }}">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="mail_encryption">Encryption</label>
                                    <select name="mail_encryption" id="mail_encryption" class="form-select">
                                        <option value="tls" @selected($s['mail_encryption'] === 'tls')>TLS (587)</option>
                                        <option value="ssl" @selected($s['mail_encryption'] === 'ssl')>SSL (465)</option>
                                        <option value="none" @selected($s['mail_encryption'] === 'none')>None</option>
                                    </select>
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="mail_from_address">From Address</label>
                                    <input type="email" name="mail_from_address" id="mail_from_address" class="form-control" value="{{ $s['mail_from_address'] }}" placeholder="sales@yourshop.com">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="mail_from_name">From Name</label>
                                    <input type="text" name="mail_from_name" id="mail_from_name" class="form-control" value="{{ $s['mail_from_name'] }}" placeholder="{{ $generalSetting['company_name'] ?? '' }}">
                                    <div class="text-danger small field-error"></div>
                                </div>
                            </div>
                            <div class="text-danger small mt-2" id="mail_error"></div>
                            <div class="d-flex mt-3">
                                <button type="submit" class="btn btn-primary ms-auto" id="mailSave">Save Email Settings</button>
                            </div>

                            <hr>
                            <label class="form-label" for="test_to">Send a test email</label>
                            <div class="input-group">
                                <input type="email" name="test_to" id="test_to" class="form-control" placeholder="you@example.com" value="{{ auth()->user()->email ?? '' }}">
                                <button type="button" class="btn btn-outline-secondary" id="mailTest"><i class="fas fa-paper-plane"></i> Send Test</button>
                            </div>
                            <div class="sys-hint mt-1">Uses the values in the form above (saved or not).</div>
                            <div class="sys-result mt-2" id="mailTestResult"></div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- SMS --}}
            <div class="col-xl-6">
                <div class="card acc-card h-100">
                    <div class="card-body">
                        <form id="smsForm" autocomplete="off" novalidate>
                            <div class="d-flex align-items-center mb-3">
                                <h5 class="mb-0"><i class="fas fa-sms me-2"></i>SMS (BulkSMSBD)</h5>
                                <div class="form-check form-switch ms-auto mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="sms_enabled" name="sms_enabled" value="1" @checked($s['sms_enabled'] === '1')>
                                    <label class="form-check-label" for="sms_enabled">SMS on</label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label class="form-label" for="sms_api_key">API Key</label>
                                    <input type="password" name="sms_api_key" id="sms_api_key" class="form-control" autocomplete="new-password"
                                        placeholder="{{ $s['sms_api_key_set'] ? 'Saved — leave blank to keep' : 'From bulksmsbd.net > API' }}">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="sms_sender_id">Sender ID</label>
                                    <input type="text" name="sms_sender_id" id="sms_sender_id" class="form-control" value="{{ $s['sms_sender_id'] }}" maxlength="30" placeholder="8809617…">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="smsBalance"><i class="fas fa-wallet"></i> Check Balance</button>
                                    <span class="sys-result" id="balanceResult"></span>
                                </div>

                                @foreach (\App\Http\Controllers\Admin\SmsController::TEMPLATES as $key => $tpl)
                                    <div class="col-12">
                                        <div class="d-flex align-items-center">
                                            <label class="form-label mb-1" for="sms_tpl_{{ $key }}">{{ $tpl['label'] }} template</label>
                                            <div class="form-check form-switch ms-auto mb-1">
                                                <input class="form-check-input" type="checkbox" role="switch" id="sms_{{ $key }}_enabled" name="sms_{{ $key }}_enabled" value="1" @checked($s['sms_' . $key . '_enabled'] === '1')>
                                                <label class="form-check-label small" for="sms_{{ $key }}_enabled">{{ $key === 'invoice_sms' ? 'Send after each sale' : 'Allow reminders' }}</label>
                                            </div>
                                        </div>
                                        <textarea name="sms_tpl_{{ $key }}" id="sms_tpl_{{ $key }}" class="form-control sms-tpl" rows="3" maxlength="600">{{ $s['sms_tpl_' . $key] }}</textarea>
                                        <div class="text-danger small field-error"></div>
                                        <div class="mt-1">
                                            @foreach ($tpl['placeholders'] as $p)
                                                <button type="button" class="sys-chip" data-target="#sms_tpl_{{ $key }}" data-insert="{{ '{' . $p . '}' }}">{{ '{' . $p . '}' }}</button>
                                            @endforeach
                                            <span class="sys-hint ms-1" data-count="#sms_tpl_{{ $key }}"></span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="text-danger small mt-2" id="sms_error"></div>
                            <div class="d-flex mt-3">
                                <button type="submit" class="btn btn-primary ms-auto" id="smsSave">Save SMS Settings</button>
                            </div>
                        </form>

                        <hr>
                        <form id="smsTestForm" autocomplete="off" novalidate>
                            <label class="form-label" for="test_phone">Send a test SMS (uses the saved settings)</label>
                            <div class="row g-2">
                                <div class="col-md-4"><input type="text" name="phone" id="test_phone" class="form-control" placeholder="01XXXXXXXXX"></div>
                                <div class="col-md-8"><input type="text" name="message" id="test_message" class="form-control" maxlength="600" value="Test SMS from {{ $generalSetting['company_name'] ?? 'FPOS' }}"></div>
                            </div>
                            <div class="d-flex align-items-center mt-2">
                                <span class="sys-result" id="smsTestResult"></span>
                                <button type="submit" class="btn btn-outline-secondary ms-auto" id="smsTest"><i class="fas fa-paper-plane"></i> Send Test SMS</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            function result($el, ok, text) { $el.removeClass('ok bad').addClass(ok ? 'ok' : 'bad').text(text); }

            function save($form, url, $btn, label, $err) {
                $btn.prop('disabled', true).text('Saving...');
                Voucher.clearErrors($form);
                $err.text('');
                $.ajax({
                    url: url,
                    type: 'POST',
                    dataType: 'json',
                    data: $form.serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        $form.find('input[type=password]').val('');
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($form, xhr.responseJSON.errors, $err);
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save.');
                        }
                    },
                    complete: function() { $btn.prop('disabled', false).text(label); }
                });
            }

            $('#mailForm').on('submit', function(e) {
                e.preventDefault();
                save($(this), "{{ route('admin.system.email-sms.mail') }}", $('#mailSave'), 'Save Email Settings', $('#mail_error'));
            });
            $('#mailTest').on('click', function() {
                const $btn = $(this).prop('disabled', true);
                result($('#mailTestResult'), true, 'Sending… (up to 15 seconds)');
                $('#mailTestResult').removeClass('ok');
                $.ajax({
                    url: "{{ route('admin.system.email-sms.mail.test') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $('#mailForm').serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        result($('#mailTestResult'), true, response.message);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        result($('#mailTestResult'), false, (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to send the test email.');
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });

            $('#smsForm').on('submit', function(e) {
                e.preventDefault();
                save($(this), "{{ route('admin.system.email-sms.sms') }}", $('#smsSave'), 'Save SMS Settings', $('#sms_error'));
            });
            $('#smsBalance').on('click', function() {
                const $btn = $(this).prop('disabled', true);
                $('#balanceResult').removeClass('ok bad').text('Checking…');
                $.ajax({
                    url: "{{ route('admin.system.sms.balance') }}",
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        result($('#balanceResult'), true, response.message);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        result($('#balanceResult'), false, (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to check the balance.');
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });
            $('#smsTestForm').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#smsTest').prop('disabled', true);
                $('#smsTestResult').removeClass('ok bad').text('Sending…');
                $.ajax({
                    url: "{{ route('admin.system.email-sms.sms.test') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        result($('#smsTestResult'), true, response.message);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        result($('#smsTestResult'), false, (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to send the SMS.');
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });

            // placeholder chips insert at the cursor
            $('.sys-chip').on('click', function() {
                const el = document.querySelector($(this).data('target')), text = $(this).data('insert');
                const at = el.selectionStart ?? el.value.length;
                el.value = el.value.slice(0, at) + text + el.value.slice(el.selectionEnd ?? at);
                el.focus();
                el.selectionStart = el.selectionEnd = at + text.length;
                $(el).trigger('input');
            });
            // character / SMS part counter (160 per part, 70 when the text has Bangla / unicode)
            function count() {
                $('[data-count]').each(function() {
                    const v = $($(this).data('count')).val() || '';
                    const unicode = /[^\x00-\x7F]/.test(v), per = unicode ? 70 : 160;
                    $(this).text(v.length + ' chars (before filling placeholders) · ~' + Math.max(1, Math.ceil(v.length / per)) + ' SMS' + (unicode ? ' (unicode)' : ''));
                });
            }
            $('.sms-tpl').on('input', count);
            count();
        });
    </script>
@endpush
