@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Backups
@endsection

@push('styles')
    <style>
        .bk-hint { font-size: var(--text-caption); color: var(--color-text-muted); }
        .bk-path { font-family: var(--font-mono); font-size: var(--text-micro); background: var(--color-surface-soft); padding: var(--space-xxs) var(--space-sm); border-radius: var(--radius-xs); word-break: break-all; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Backups</h4>
                <small class="text-muted">Database dumps (and optionally the uploaded files) saved on this server in storage/app/backups.</small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary" id="runBtn"><i class="fas fa-database"></i> Back Up Now</button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card acc-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="backupTable" class="table table-hover align-middle w-100 acc-table" data-order="[]">
                                <thead>
                                    <tr>
                                        <th data-orderable="false" width="5%">SL.</th>
                                        <th>File</th>
                                        <th>Type</th>
                                        <th class="text-end">Size</th>
                                        <th>Created</th>
                                        <th data-orderable="false" width="6%">Action</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <div class="bk-hint mt-2" id="totalSize"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card acc-card">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="fas fa-cog me-2"></i>Settings</h5>
                        <form id="settingsForm" autocomplete="off" novalidate>
                            <div class="mb-3">
                                <label class="form-label" for="backup_keep">Number of backups to keep</label>
                                <input type="number" name="backup_keep" id="backup_keep" class="form-control" min="1" max="365" value="{{ $settings['backup_keep'] }}">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="backup_time">Daily backup time</label>
                                <input type="time" name="backup_time" id="backup_time" class="form-control" value="{{ $settings['backup_time'] }}">
                                <div class="text-danger small field-error"></div>
                                <div class="bk-hint mt-1">Runs only when the Laravel scheduler is set up on the server (Windows Task Scheduler running <span class="bk-path">php artisan schedule:run</span> every minute).</div>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="backup_include_uploads" name="backup_include_uploads" value="1" @checked($settings['backup_include_uploads'])>
                                <label class="form-check-label" for="backup_include_uploads">Also zip public/uploads (logos, product images)</label>
                            </div>
                            <div class="text-danger small" id="settings_error"></div>
                            <button type="submit" class="btn btn-primary w-100" id="settingsSave">Save Settings</button>
                        </form>
                        <hr>
                        <div class="bk-hint">mysqldump: <span class="bk-path">{{ $mysqldump }}</span></div>
                        <div class="bk-hint mt-2">Copy backups off this computer (USB / cloud drive) regularly — a backup on the same disk does not survive a disk failure.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const Backup = {
            table: null,
            reload: function() { Backup.table.ajax.reload(null, false); },
            remove: function(name) {
                Swal.fire({ title: 'Delete this backup?', text: name, icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', reverseButtons: true })
                    .then(function(r) {
                        if (!r.isConfirmed) return;
                        $.ajax({
                            url: "{{ url('admin/system/backups') }}/" + encodeURIComponent(name) + '/delete',
                            type: 'POST',
                            dataType: 'json',
                            success: function(response) {
                                // alert(JSON.stringify(response));
                                toastr.success(response.message);
                                Backup.reload();
                            },
                            error: function(xhr) {
                                // alert(JSON.stringify(xhr));
                                Voucher.ajaxError(xhr, 'Unable to delete.');
                            }
                        });
                    });
            }
        };

        $(function() {
            Backup.table = $('#backupTable').DataTable({
                processing: true, pageLength: 25, autoWidth: false,
                ajax: { url: "{{ route('admin.system.backups.list') }}", dataSrc: function(json) { $('#totalSize').text(json.data.length ? 'Total: ' + json.total : ''); return json.data; } }
            });

            $('#runBtn').on('click', function() {
                const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Backing up…');
                $.ajax({
                    url: "{{ route('admin.system.backups.run') }}",
                    type: 'POST',
                    dataType: 'json',
                    timeout: 600000,
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        Backup.reload();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Backup failed.');
                    },
                    complete: function() { $btn.prop('disabled', false).html('<i class="fas fa-database"></i> Back Up Now'); }
                });
            });

            $('#settingsForm').on('submit', function(e) {
                e.preventDefault();
                const $f = $(this), $btn = $('#settingsSave').prop('disabled', true);
                Voucher.clearErrors($f);
                $.ajax({
                    url: "{{ route('admin.system.backups.settings') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $f.serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) Voucher.showErrors($f, xhr.responseJSON.errors, $('#settings_error'));
                        else Voucher.ajaxError(xhr, 'Unable to save.');
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });
        });
    </script>
@endpush
