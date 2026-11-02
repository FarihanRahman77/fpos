@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - My Profile
@endsection

@push('styles')
    <style>
        .pf-head { display: flex; align-items: center; gap: var(--space-lg); }
        .pf-avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 1px solid var(--color-border); background: var(--color-surface-soft); }
        .pf-kv { display: grid; grid-template-columns: 140px 1fr; gap: var(--space-sm) var(--space-lg); margin: 0; font-size: var(--text-body-tabular); }
        .pf-kv dt { font-weight: var(--font-weight-regular); color: var(--color-text-muted); }
        .pf-kv dd { margin: 0; }
        .pf-pill {
            display: inline-block;
            margin: 0 var(--space-xs) var(--space-xs) 0;
            padding: 0 var(--space-sm);
            border-radius: var(--radius-pill);
            background: var(--color-accent-soft);
            color: var(--color-accent-ink);
            font-size: var(--text-caption);
        }
        .pf-pill.current { background: var(--color-button); color: var(--color-button-text); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <h4 class="mb-3">My Profile</h4>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card acc-card h-100">
                    <div class="card-body">
                        <div class="pf-head mb-4">
                            @include('admin.includes.avatar', ['user' => $user, 'class' => 'pf-avatar'])
                            <div>
                                <div class="fs-5">{{ $user->name }}</div>
                                <div class="text-muted small">{{ $user->designation ?: ($user->role_name ?: 'User') }}</div>
                            </div>
                        </div>
                        <dl class="pf-kv">
                            <dt>Email</dt><dd>{{ $user->email }}</dd>
                            <dt>Role</dt><dd>{{ $user->role_name ?: '-' }}</dd>
                            <dt>Home branch</dt><dd>{{ $user->home_branch ?: '-' }}</dd>
                            <dt>Signed in to</dt><dd>{{ $loggedInBranch->name ?? '-' }}</dd>
                            <dt>Can sign in to</dt>
                            <dd>
                                @forelse ($branches as $b)
                                    <span class="pf-pill {{ ($loggedInBranch->name ?? null) === $b ? 'current' : '' }}">{{ $b }}</span>
                                @empty
                                    <span class="text-muted">No branch assigned — ask an administrator.</span>
                                @endforelse
                            </dd>
                        </dl>
                        <p class="small text-muted mt-3 mb-0">To work in another branch, sign out and choose it on the login page. Branch access is set by an administrator in User Management.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card acc-card h-100">
                    <div class="card-body">
                        <h6 class="mb-3">Change password</h6>
                        @if (session('status') === 'password-updated')
                            <div class="alert alert-success py-2">Your password has been changed.</div>
                        @endif
                        <form method="POST" action="{{ route('password.update') }}" autocomplete="off">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label" for="current_password">Current password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
                                @foreach ($errors->updatePassword->get('current_password') as $m)<div class="text-danger small mt-1">{{ $m }}</div>@endforeach
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password">New password</label>
                                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
                                @foreach ($errors->updatePassword->get('password') as $m)<div class="text-danger small mt-1">{{ $m }}</div>@endforeach
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password_confirmation">Repeat new password</label>
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Change password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
