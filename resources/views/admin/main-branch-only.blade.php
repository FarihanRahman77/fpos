@extends('admin.master')
@section('title', 'Head office only')

@section('content')
    <div class="container-fluid px-3 px-lg-4 py-5">
        <div class="card acc-card mx-auto" style="max-width: 560px;">
            <div class="card-body text-center py-5">
                <i class="bi bi-building-lock fs-1 text-muted" aria-hidden="true"></i>
                <h4 class="mt-3 mb-2">Head office only</h4>
                <p class="text-muted mb-4">{{ $message }}</p>
                <p class="small text-muted mb-4">You are signed in to <b>{{ $loggedInBranch->name ?? '-' }}</b>. To open this, sign out and sign in to {{ $mainBranch }}.</p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Back to dashboard</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
