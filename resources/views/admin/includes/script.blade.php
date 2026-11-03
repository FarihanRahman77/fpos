<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
</script>

<script src="{{ asset('backend/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('backend/js/main.js') }}?v={{ filemtime(public_path('backend/js/main.js')) }}"></script>

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    // Messages often carry stored names (products, employees, parties, batches): show them as text, never as HTML.
    if (window.toastr) { toastr.options.escapeHtml = true; }
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<script>
    // Signed out (session expired, or no branch chosen at login): say why once and go to the login page,
    // instead of every screen showing its own "could not load" error.
    (function () {
        var leaving = false;
        $(document).ajaxError(function (event, xhr) {
            if (leaving || (xhr.status !== 401 && xhr.status !== 419)) return;
            leaving = true;
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Your session has ended. Please sign in again.';
            if (window.toastr) toastr.warning(msg + ' Taking you to the login page…');
            setTimeout(function () { window.location.href = "{{ route('login') }}"; }, 1800);
        });
    })();
</script>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    // Tables: keep short values (dates, invoice numbers, amounts) on one line instead of breaking at hyphens,
    // and let a wide list scroll sideways inside its card instead of squeezing columns.
    // Messages a page passes on a redirect (->with('error', ...)) show as toasts.
    $(function () {
        if (!window.toastr) return;
        @foreach (['success', 'error', 'warning'] as $flashType)
            @if (session()->has($flashType))
                toastr.{{ $flashType }}(@json(session($flashType)));
            @endif
        @endforeach
    });

    (function () {
        if ($.fn.dataTable) {
            $.extend(true, $.fn.dataTable.defaults, {
                language: {
                    emptyTable: 'Nothing here yet.',
                    zeroRecords: 'No records match your search.',
                    info: 'Showing _START_–_END_ of _TOTAL_',
                    infoEmpty: 'No records',
                    infoFiltered: '(filtered from _MAX_)',
                    lengthMenu: 'Show _MENU_',
                    search: '',
                    searchPlaceholder: 'Search…',
                    processing: 'Loading…',
                    paginate: { previous: '‹ Previous', next: 'Next ›' }
                }
            });
        }
        function tidy(root) {
            $(root).find('table.table td').each(function () {
                var text = (this.innerText || '').trim();
                if (text && text.split('\n').every(function (l) { return l.trim().length <= 24; })) {
                    this.classList.add('cell-nowrap');
                }
            });
        }
        $(document).on('init.dt', function (e, settings) {
            $(settings.nTableWrapper).find('.dataTables_filter input').attr('aria-label', 'Search this list');
            var $t = $(settings.nTable);
            if (!$t.closest('.table-responsive').length && !$t.parent().hasClass('dt-scroll')) $t.wrap('<div class="dt-scroll"></div>');
        });
        $(document).on('draw.dt', function (e, settings) { tidy(settings.nTable); });
        $(function () { tidy(document); });
        $(document).ajaxComplete(function () { setTimeout(function () { tidy(document); }, 0); });
    })();
</script>
@stack('scripts')
