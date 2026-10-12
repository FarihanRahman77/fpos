<x-guest-layout variant="login" :brand-logo="$setting->logo ?? null">
    <div class="loom-form-heading">
        <p class="loom-eyebrow loom-form-eyebrow">YOUR BUSINESS, IN GOOD HANDS</p>
        <h2>Welcome back<span>.</span></h2>
        <p>Sign in to pick up right where you left off.</p>
    </div>

    @if (session('status'))
        <div class="loom-status" role="status" aria-live="polite">
            <x-auth-session-status :status="session('status')" />
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="loom-login-form" data-login-form>
        @csrf

        <div class="loom-field-group">
            <label class="loom-field-label" for="email">{{ __('Email address') }}</label>
            <div class="loom-input-wrap">
                <svg class="loom-input-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <rect x="2.5" y="4" width="15" height="12" rx="2.5" stroke="currentColor" stroke-width="1.4"/>
                    <path d="m3.5 5.5 6.5 5 6.5-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <input class="loom-input" id="email" type="email" name="email" value="{{ old('email') }}"
                    required autofocus autocomplete="username" placeholder="you@company.com"
                    @if ($errors->get('email')) aria-invalid="true" aria-describedby="email-errors" @endif>
            </div>
            @if ($errors->get('email'))
                <ul class="loom-field-errors" id="email-errors" role="alert">
                    @foreach ($errors->get('email') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="loom-field-group">
            <label class="loom-field-label" for="password">{{ __('Password') }}</label>
            <div class="loom-input-wrap">
                <svg class="loom-input-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <rect x="3.5" y="8.5" width="13" height="9" rx="2" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M6.5 8.5V6a3.5 3.5 0 1 1 7 0v2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                    <circle cx="10" cy="12.8" r="1" fill="currentColor"/>
                </svg>
                <input class="loom-input loom-password-input" id="password" type="password" name="password"
                    required autocomplete="current-password" placeholder="Enter your password"
                    @if ($errors->get('password')) aria-invalid="true" aria-describedby="password-errors" @endif>
                <button class="loom-password-toggle" type="button" data-password-toggle
                    aria-label="{{ __('Show password') }}" aria-pressed="false">
                    <svg data-eye-open viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M2.5 10s2.7-4.5 7.5-4.5 7.5 4.5 7.5 4.5-2.7 4.5-7.5 4.5S2.5 10 2.5 10Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                        <circle cx="10" cy="10" r="2" stroke="currentColor" stroke-width="1.4"/>
                    </svg>
                    <svg data-eye-closed viewBox="0 0 20 20" fill="none" aria-hidden="true" hidden>
                        <path d="m3 3 14 14M8.6 5.7c.5-.1.9-.2 1.4-.2 4.8 0 7.5 4.5 7.5 4.5a12 12 0 0 1-2.2 2.6M6.1 6.5C3.8 7.7 2.5 10 2.5 10s2.7 4.5 7.5 4.5c.7 0 1.3-.1 1.9-.3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            @if ($errors->get('password'))
                <ul class="loom-field-errors" id="password-errors" role="alert">
                    @foreach ($errors->get('password') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="loom-form-options">
            <label class="loom-remember" for="remember_me">
                <input id="remember_me" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span>{{ __('Remember me') }}</span>
            </label>
            @if (Route::has('password.request'))
                <a class="loom-text-link" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <button class="loom-submit-button" type="submit" data-submit-button>
            <span data-submit-label>{{ __('Sign in') }}</span>
            <svg data-submit-spinner viewBox="0 0 20 20" fill="none" aria-hidden="true" hidden>
                <circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-opacity=".25" stroke-width="2"/>
                <path d="M17.5 10A7.5 7.5 0 0 0 10 2.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <svg class="loom-submit-arrow" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M4 10h11m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
        <p class="loom-submit-status" data-submit-status role="status" aria-live="polite"></p>
    </form>

    @if (Route::has('register'))
        <p class="loom-signup-link">
            New to LOOM? <a href="{{ route('register') }}">{{ __('Create an account') }}</a>
        </p>
    @endif

    <script>
        (function () {
            var passwordInput = document.getElementById('password');
            var passwordToggle = document.querySelector('[data-password-toggle]');
            var loginForm = document.querySelector('[data-login-form]');
            var submitButton = document.querySelector('[data-submit-button]');

            if (passwordInput && passwordToggle) {
                passwordToggle.addEventListener('click', function () {
                    var reveal = passwordInput.type === 'password';
                    passwordInput.type = reveal ? 'text' : 'password';
                    passwordToggle.setAttribute('aria-pressed', String(reveal));
                    passwordToggle.setAttribute('aria-label', reveal ? @json(__('Hide password')) : @json(__('Show password')));
                    passwordToggle.querySelector('[data-eye-open]').hidden = reveal;
                    passwordToggle.querySelector('[data-eye-closed]').hidden = !reveal;
                });
            }

            if (loginForm && submitButton) {
                loginForm.addEventListener('submit', function (event) {
                    if (loginForm.dataset.submitting === 'true') {
                        event.preventDefault();
                        return;
                    }

                    loginForm.dataset.submitting = 'true';
                    submitButton.disabled = true;
                    submitButton.querySelector('[data-submit-label]').textContent = @json(__('Signing in...'));
                    submitButton.querySelector('[data-submit-spinner]').hidden = false;
                    submitButton.querySelector('.loom-submit-arrow').hidden = true;
                    loginForm.querySelector('[data-submit-status]').textContent = @json(__('Signing in...'));
                });
            }
        })();
    </script>
</x-guest-layout>
