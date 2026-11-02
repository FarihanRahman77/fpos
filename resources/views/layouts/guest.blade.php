
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $variant === 'login' ? 'LOOM | Sign in' : config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('backend/css/poppins.css') }}">
        @if ($variant === 'login')
            {{-- Design system: Inter + tokens, and the colors saved in Settings > Theme Colors. --}}
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="{{ asset('backend/css/tokens.css') }}?v={{ filemtime(public_path('backend/css/tokens.css')) }}">
            <link rel="stylesheet" href="{{ asset('backend/vendors/bootstrap-icons/bootstrap-icons.css') }}">
            <link rel="stylesheet" href="{{ asset('backend/css/loom-auth.css') }}?v={{ filemtime(public_path('backend/css/loom-auth.css')) }}">
            @if ($themeCss)
                <style id="theme-colors">{!! $themeCss !!}</style>
            @endif
        @endif
    </head>
    @if ($variant === 'login')
        <body class="loom-auth-body">
            <div class="loom-auth-shell loom-split">
                <section class="loom-form-panel">
                    <a class="loom-brand-lockup {{ $brandLogo ? 'has-logo' : '' }}" href="/" aria-label="LOOM home">
                        @if ($brandLogo)
                            <img class="loom-brand-symbol" src="{{ asset($brandLogo) }}" alt="">
                        @endif
                        <span class="loom-wordmark">LOOM</span>
                        <span class="loom-brand-divider" aria-hidden="true"></span>
                        <span class="loom-brand-category">INVENTORY + POS</span>
                    </a>
                    <button class="loom-theme-toggle" type="button" data-theme-toggle
                        aria-label="Switch to dark mode" title="Switch to dark mode">
                        <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
                    </button>
                    <main class="loom-form-content">
                        {{ $slot }}
                    </main>
                    <p class="loom-privacy-note">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M10 2.5 3.5 5v4.3c0 4.1 2.8 6.9 6.5 8.2 3.7-1.3 6.5-4.1 6.5-8.2V5L10 2.5Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
                            <path d="m7.5 9.8 1.7 1.7 3.4-3.6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Your workspace, securely kept.
                    </p>
                </section>

                @php $bgFile = $brandBackground && is_file(public_path($brandBackground)) ? asset($brandBackground) : null; @endphp
                <section class="loom-brand-panel {{ $bgFile ? 'has-image' : '' }}" aria-label="LOOM inventory and point-of-sale"
                    @if ($bgFile) style="--loom-bg-image: url('{{ $bgFile }}'); --loom-bg-overlay: {{ max(0, min(95, (int) $brandOverlay)) }}%;" @endif>
                    <div class="loom-squares loom-squares-top" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                    <div class="loom-squares loom-squares-bottom" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>

                    <div class="loom-showcase" aria-hidden="true">
                        <div class="loom-chart-card">
                            <div class="loom-chart-head">
                                <span>Today's sales</span>
                                <span class="loom-tabs"><b>Weekly</b><i>Monthly</i><i>Yearly</i></span>
                            </div>
                            <div class="loom-chart-value">1,24,500 <small><i class="bi bi-arrow-up-right"></i> 12%</small></div>
                            <svg class="loom-chart" viewBox="0 0 300 110" preserveAspectRatio="none">
                                <line x1="0" y1="27" x2="300" y2="27" /><line x1="0" y1="55" x2="300" y2="55" /><line x1="0" y1="83" x2="300" y2="83" />
                                <path class="area" d="M0,92 L45,70 L90,30 L135,58 L180,44 L225,52 L270,26 L300,34 L300,110 L0,110 Z" />
                                <path class="line" d="M0,92 L45,70 L90,30 L135,58 L180,44 L225,52 L270,26 L300,34" />
                                <path class="line alt" d="M0,80 L45,86 L90,62 L135,40 L180,66 L225,58 L270,48 L300,58" />
                            </svg>
                            <div class="loom-chart-days"><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span></div>
                        </div>
                        <div class="loom-donut-card">
                            <div class="loom-donut"><span><small>In stock</small><b>82%</b></span></div>
                            <div class="loom-donut-note"><i class="bi bi-box-seam"></i> 3 items low</div>
                        </div>
                    </div>

                    <div class="loom-brand-message">
                        <p class="loom-eyebrow"><span></span> A clearer view of your business</p>
                        <h1>Everything in its <em>right place.</em></h1>
                        <p class="loom-brand-description">
                            Stock, sales, purchases and reports&mdash;connected in one considered workspace.
                        </p>
                        <ul class="loom-modules" aria-label="Included modules">
                            <li><i class="bi bi-shop" aria-hidden="true"></i> POS</li>
                            <li><i class="bi bi-boxes" aria-hidden="true"></i> Inventory</li>
                            <li><i class="bi bi-journal-text" aria-hidden="true"></i> Accounts</li>
                            <li><i class="bi bi-people" aria-hidden="true"></i> Payroll</li>
                            <li><i class="bi bi-bar-chart" aria-hidden="true"></i> Reports</li>
                        </ul>
                    </div>

                    <p class="loom-brand-footer">Thoughtfully made for the way you work.</p>
                </section>
            </div>
            <script>
                (function () {
                    var storageKey = 'adminHMD.colorTheme';
                    var themeToggle = document.querySelector('[data-theme-toggle]');
                    var themeIcon = document.querySelector('[data-theme-icon]');
                    var theme = '';
                    var storageAvailable = true;

                    try {
                        theme = window.localStorage.getItem(storageKey) || '';
                    } catch (error) {
                        storageAvailable = false;
                    }

                    if (theme !== 'dark' && theme !== 'light') {
                        theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
                            ? 'dark'
                            : 'light';
                    }

                    function applyTheme(nextTheme) {
                        document.documentElement.setAttribute('data-theme', nextTheme);
                        document.documentElement.setAttribute('data-bs-theme', nextTheme);

                        if (storageAvailable) {
                            try {
                                window.localStorage.setItem(storageKey, nextTheme);
                            } catch (error) {
                                storageAvailable = false;
                            }
                        }

                        if (themeToggle) {
                            var nextMode = nextTheme === 'dark' ? 'light' : 'dark';
                            themeToggle.setAttribute('aria-label', 'Switch to ' + nextMode + ' mode');
                            themeToggle.setAttribute('title', 'Switch to ' + nextMode + ' mode');
                        }

                        if (themeIcon) {
                            themeIcon.className = nextTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
                        }
                    }

                    applyTheme(theme);

                    if (themeToggle) {
                        themeToggle.addEventListener('click', function () {
                            var currentTheme = document.documentElement.getAttribute('data-theme');
                            applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
                        });
                    }
                })();
            </script>
        </body>
    @else
        <body class="font-sans text-gray-900 antialiased">
            <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
                <div>
                    <a href="/">
                        <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
                    </a>
                </div>

                <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                    {{ $slot }}
                </div>
            </div>
        </body>
    @endif
</html>
