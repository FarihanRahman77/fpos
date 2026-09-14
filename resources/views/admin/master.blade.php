<!DOCTYPE html>
<html lang="en">

@include('admin.includes.header')

<body>
    <div class="admin-shell">
        <div class="sidebar-backdrop" data-sidebar-close></div>

        @include('admin.includes.sidebar')

        <div class="admin-main">
            @include('admin.includes.navbar')

            <main class="dashboard-content">
                @yield('content')
            </main>

            @include('admin.includes.footer')
        </div>
    </div>

    @include('admin.includes.script')
</body>

</html>
