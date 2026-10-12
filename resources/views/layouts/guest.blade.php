@props(['variant' => 'default', 'brandLogo' => null])

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
            <link rel="stylesheet" href="{{ asset('backend/vendors/bootstrap-icons/bootstrap-icons.css') }}">
            <link rel="stylesheet" href="{{ asset('backend/css/loom-auth.css') }}">
        @endif
    </head>
    @if ($variant === 'login')
        <body class="loom-auth-body">
            <div class="loom-auth-shell">
                <section class="loom-brand-panel" aria-label="LOOM inventory and point-of-sale">
                    <a class="loom-brand-lockup" href="/" aria-label="LOOM home">
                        @if ($brandLogo)
                            <img class="loom-brand-symbol" src="{{ asset($brandLogo) }}" alt="">
                        @endif
                        <span class="loom-wordmark">LOOM</span>
                        <span class="loom-brand-divider" aria-hidden="true"></span>
                        <span class="loom-brand-category">INVENTORY + POS</span>
                    </a>

                    <div class="loom-brand-message">
                        <p class="loom-eyebrow"><span></span> A clearer view of your business</p>
                        <h1>Everything<br>in its <em>right place.</em></h1>
                        <p class="loom-brand-description">
                            Stock, sales, purchases and reports&mdash;connected in one considered workspace.
                        </p>
                    </div>

                    <div class="loom-weave-scene" aria-hidden="true">
                        <svg class="loom-weave" viewBox="0 0 720 410" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path class="loom-thread loom-thread-one" d="M-42 292C88 234 152 354 278 296C406 236 434 87 571 105C646 115 681 170 762 153" />
                            <path class="loom-thread loom-thread-two" d="M-35 335C98 270 176 387 303 325C432 261 467 136 592 148C664 155 705 209 769 190" />
                            <path class="loom-thread loom-thread-three" d="M-22 249C96 198 152 313 260 261C372 207 407 52 550 65C638 72 689 128 757 111" />
                            <path class="loom-thread loom-thread-four" d="M156 438C203 335 177 257 266 175C350 97 469 115 519 32" />
                            <path class="loom-thread loom-thread-five" d="M226 439C266 346 237 277 323 201C402 132 522 152 574 55" />
                            <path class="loom-thread loom-thread-six" d="M296 435C328 357 302 297 378 229C453 162 563 190 628 86" />
                            <circle cx="486" cy="184" r="4" fill="currentColor" />
                            <circle cx="318" cy="272" r="3" fill="currentColor" />
                        </svg>
                        <div class="loom-product-note loom-product-note-sales">
                            <span class="loom-note-icon" aria-hidden="true">↗</span>
                            <span><strong>Sales</strong><small>Everyday, in view</small></span>
                        </div>
                        <div class="loom-product-note loom-product-note-stock">
                            <span class="loom-note-icon loom-note-icon-grid" aria-hidden="true">
                                <i></i><i></i><i></i><i></i>
                            </span>
                            <span><strong>Stock</strong><small>In sync, always</small></span>
                        </div>
                        <div class="loom-scene-caption">A THREAD THROUGH EVERY PART OF YOUR BUSINESS</div>
                    </div>

                    <p class="loom-brand-footer">Thoughtfully made for the way you work.</p>
                </section>

                <section class="loom-form-panel">
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
