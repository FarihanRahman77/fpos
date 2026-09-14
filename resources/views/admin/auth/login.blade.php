<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="adminHMD authentication page">
    <title>Login | adminHMD</title>

    <link rel="stylesheet" href="{{ asset('backend/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/vendors/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/css/style.css') }}">
</head>

<body class="auth-body">
    <button class="icon-button theme-toggle auth-theme-toggle" type="button" data-theme-toggle
        aria-label="Switch color theme" title="Switch color theme">
        <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
    </button>
    <main class="auth-page">
        <section class="auth-card">
            
            <div class="auth-visual"><img src="../assets/images/png/dasher-ui-bootstrap-5.jpg"
                    alt="adminHMD dashboard interface"></div>
            <form method="POST" action="{{ route('login') }}" class="needs-validation" novalidate>
                @csrf

                <div class="mb-3">
                    <label class="form-label" for="loginEmail">Email</label>
                    <input class="form-control" id="email" type="email" name="email" :value="old('email')"
                        required autofocus autocomplete="username">
                    <div class="invalid-feedback">$errors->get('email')</div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <label class="form-label" for="loginPassword">Password</label>

                    </div>
                    <input class="form-control" id="password" type="password" name="password" required
                        autocomplete="current-password">
                    <div class="invalid-feedback">$errors->get('password')</div>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" id="remember_me" type="checkbox" name="remember">
                    <label class="form-check-label" for="rememberMe">{{ __('Remember me') }}</label>
                </div>
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Sign In
                </button>
            </form>
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
            <div class="auth-footer">New here? <a href="{{ route('register') }}">Create an account</a></div>

        </section>
    </main>

    <script src="{{ asset('backend/js/bootstrap.bundle.min.js')}}"></script>
    <script src="{{ asset('backend/js/main.js')}}"></script>
</body>

</html>
