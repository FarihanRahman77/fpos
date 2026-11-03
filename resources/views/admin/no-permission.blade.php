@extends('admin.master')
@section('title', 'No permission')

@section('content')
    <div class="container-fluid px-3 px-lg-4 py-5">
        <div class="card acc-card mx-auto" style="max-width: 560px;">
            <div class="card-body text-center py-5">
                <i class="bi bi-shield-lock fs-1 text-muted" aria-hidden="true"></i>
                <h4 class="mt-3 mb-2">You don't have access to this</h4>
                <p class="text-muted mb-4">{{ $message }}</p>
                <p class="small text-muted mb-4">Your role is <b>{{ \Illuminate\Support\Facades\DB::table('roles')->where('id', Auth::user()->role_id)->value('name') ?? '-' }}</b>. An administrator can change what it may do under User Management &gt; Roles.</p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="{{ $homeUrl }}" class="btn btn-outline-secondary">Back</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
