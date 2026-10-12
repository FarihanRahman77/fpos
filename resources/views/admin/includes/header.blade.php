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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('backend/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/vendors/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/css/poppins.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet"  href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    @stack('styles')
</head>
