<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="adminHMD professional admin dashboard template">
    <title>@yield('title')</title>
    @if(!empty($generalSetting->favicon))
        <link rel="icon" type="image/png" href="{{ asset($generalSetting->favicon) }}">
    @endif
    <script>
        (function () {
            var root = document.documentElement;

            try {
                var theme = localStorage.getItem('adminHMD.colorTheme');
                root.setAttribute('data-theme', theme === 'dark' ? 'dark' : 'light');

                if (window.matchMedia('(min-width: 1024px)').matches && localStorage.getItem('adminHMD.sidebarMini') === 'true') {
                    root.classList.add('sidebar-mini');
                }
            } catch (error) {
                root.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Inter: DESIGN.md's open-source substitute for Sohne (300 display, 400 body, 500 emphasis). --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('backend/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/css/tokens.css') }}?v={{ filemtime(public_path('backend/css/tokens.css')) }}">
    <link rel="stylesheet" href="{{ asset('backend/vendors/bootstrap-icons/bootstrap-icons.css') }}">
    {{-- Vendor CSS first, so style.css can override it (e.g. Select2's white backgrounds in dark mode). --}}
    <link rel="stylesheet"  href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="{{ asset('backend/css/style.css') }}?v={{ filemtime(public_path('backend/css/style.css')) }}">
    <link rel="stylesheet" href="{{ asset('backend/css/components.css') }}?v={{ filemtime(public_path('backend/css/components.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    {{-- Colors customized in General Settings > Theme Colors (validated hex values only). --}}
    @if ($themeCss)
        <style id="theme-colors">{!! $themeCss !!}</style>
    @endif
    @stack('styles')
</head>
