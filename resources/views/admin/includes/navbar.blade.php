@php
    $alerts = \App\Http\Controllers\Admin\DashboardController::notifications();
@endphp
<nav class="navbar admin-navbar navbar-expand">
    <div class="container-fluid px-3 px-lg-4">
        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="false"
            aria-label="Toggle sidebar">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <form class="d-none d-md-flex ms-3 flex-grow-1" role="search">
            <input class="form-control search-input" type="search" placeholder="Search users, orders, reports"
                aria-label="Search">
        </form>

        <div class="navbar-actions ms-auto">
            @isset($loggedInBranch)
                {{-- Working branch (new records, stock, cash drawer, changes); "+N" = other branches whose data is also shown. --}}
                @php($navVisible = \App\Http\Controllers\Admin\BranchController::visibleNames())
                @php($navOthers = collect($navVisible)->except($loggedInBranch->id))
                <span class="branch-switch"
                    title="{{ $navOthers->isEmpty()
                        ? 'You are signed in to this branch. Sign out to use another branch.'
                        : 'Working in ' . $loggedInBranch->name . ' (new records, stock and cash). Also showing data of: ' . $navOthers->implode(', ') . '. To change their records, sign in to that branch.' }}">
                    <i class="bi bi-building" aria-hidden="true"></i>
                    <span class="d-none d-md-inline">{{ $loggedInBranch->name }}</span>
                    @if ($navOthers->isNotEmpty())
                        <span class="badge rounded-pill bg-secondary ms-1">+{{ $navOthers->count() }}</span>
                    @endif
                </span>
            @endisset

            @perm('pos.view')
            <a class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 px-3 fw-semibold {{ request()->routeIs('sales.pos') ? 'active' : '' }}"
                href="{{ route('sales.pos') }}" title="Point of Sale">
                <i class="bi bi-shop" aria-hidden="true"></i>
                <span class="d-none d-sm-inline">POS</span>
            </a>
            @endperm
            <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme"
                title="Switch color theme">
                <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
            </button>
            <div class="dropdown">
                <button class="icon-button" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                    aria-label="Notifications{{ $alerts ? ' (' . count($alerts) . ')' : '' }}">
                    @if ($alerts)<span class="notification-dot"></span>@endif
                    <i class="bi bi-bell" aria-hidden="true"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-menu">
                    <div class="dropdown-header fw-bold text-body">Notifications</div>
                    @forelse ($alerts as $a)
                        <a class="dropdown-item" href="{{ $a['url'] }}">
                            <span class="notification-title"><i class="bi {{ $a['icon'] }} me-1" aria-hidden="true"></i>{{ $a['title'] }}</span>
                            <span class="notification-time">{{ $a['text'] }}</span>
                        </a>
                    @empty
                        <div class="dropdown-item-text text-muted small">You're all caught up.</div>
                    @endforelse
                </div>
            </div>

            <div class="dropdown">
                <button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown"
                    aria-expanded="false">
                    @include('admin.includes.avatar', ['user' => Auth::user(), 'class' => 'avatar-sm'])
                    <span class="profile-name d-none d-sm-inline">{{ Auth::user()->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('admin.users.profile', Auth::user()->id) }}">Profile</a></li>
                    @perm('settings.view')
                    <li><a class="dropdown-item" href="{{ route('admin.settings.index') }}">Settings</a></li>
                    @endperm
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); this.closest('form').submit();">Sign out</a>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
