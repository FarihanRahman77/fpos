@extends('admin.master')
@section('title', 'PDF too large')

@section('content')
    <div class="container-fluid px-3 px-lg-4 py-5">
        <div class="card acc-card mx-auto" style="max-width: 560px;">
            <div class="card-body text-center py-5">
                <i class="bi bi-file-earmark-pdf fs-1 text-muted" aria-hidden="true"></i>
                <h4 class="mt-3 mb-2">This PDF is too large</h4>
                <p class="text-muted mb-4">{{ $message }}</p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="{{ url()->previous() }}" class="btn btn-primary">Go back</a>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Dashboard</a>
                </div>
            </div>
        </div>
    </div>
@endsection
