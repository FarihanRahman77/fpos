{{-- A user's photo, or their initials when there is no photo on disk. Params: $user, $class. --}}
@php
    $hasPhoto = !empty($user->image) && is_file(public_path($user->image));
    $initials = collect(preg_split('/\s+/', trim((string) $user->name)))->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
@endphp
@if ($hasPhoto)
    <img class="avatar-img {{ $class ?? '' }}" src="{{ asset($user->image) }}" alt="{{ $user->name }}">
@else
    <span class="avatar-img avatar-initials {{ $class ?? '' }}" role="img" aria-label="{{ $user->name }}">{{ $initials }}</span>
@endif
