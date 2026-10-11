<style>
    .sidebar-nav ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .sidebar-nav .submenu {
        padding-left: 0;
    }

    .sidebar-nav .submenu .nav-link {
        padding-left: 50px;
        font-size: 14px;
    }

    .sidebar-nav .submenu .nav-icon {
        width: 22px;
    }

    .sidebar-nav .submenu-arrow {
        transition: transform 0.2s ease;
    }

    .sidebar-nav .nav-link[aria-expanded="true"] .submenu-arrow {
        transform: rotate(180deg);
    }
</style>
<aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
    <div class="sidebar-header">
        <a class="brand-mark" href="{{ route('dashboard') }}" aria-label="adminHMD dashboard">
            @if (!empty($generalSetting->logo))
                <img src="{{ asset($generalSetting->logo) }}" alt="{{ $generalSetting->company_name }}"
                    style="max-height: 30px;">
            @endif
        </a>
    </div>

    {{-- <nav class="sidebar-nav">
                <a class="nav-link active" href="{{ route('dashboard') }}" aria-current="page">
                    <span class="nav-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a class="nav-link" href="{{ route('admin.users.index') }}">
                    <span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                    <span class="nav-text">Users</span>
                </a>
                <a class="nav-link" href="add-user.html">
                    <span class="nav-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
                    <span class="nav-text">Add User</span>
                </a>
                <a class="nav-link" href="profile.html">
                    <span class="nav-icon"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a class="nav-link" href="charts.html">
                    <span class="nav-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
                    <span class="nav-text">Charts</span>
                </a>
                <a class="nav-link" href="tables.html">
                    <span class="nav-icon"><i class="bi bi-table" aria-hidden="true"></i></span>
                    <span class="nav-text">Tables</span>
                </a>
                <a class="nav-link" href="forms.html">
                    <span class="nav-icon"><i class="bi bi-ui-checks-grid" aria-hidden="true"></i></span>
                    <span class="nav-text">Forms</span>
                </a>
                <a class="nav-link" href="components.html">
                    <span class="nav-icon"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i></span>
                    <span class="nav-text">Components</span>
                </a>
                <a class="nav-link" href="alerts.html">
                    <span class="nav-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
                    <span class="nav-text">Alerts</span>
                </a>
                <a class="nav-link" href="modals.html">
                    <span class="nav-icon"><i class="bi bi-window-stack" aria-hidden="true"></i></span>
                    <span class="nav-text">Modals</span>
                </a>
                <a class="nav-link" href="{{ route('admin.settings.index') }}">
                    <span class="nav-icon"><i class="bi bi-gear" aria-hidden="true"></i></span>
                    <span class="nav-text">Settings</span>
                </a>
                <a class="nav-link" href="blank.html">
                    <span class="nav-icon"><i class="bi bi-file-earmark" aria-hidden="true"></i></span>
                    <span class="nav-text">Blank Page</span>
                </a>
            </nav> --}}

    <nav class="sidebar-nav">

        <ul class="nav flex-column">

            {{-- Dashboard --}}
            <li class="nav-item">
                <a class="nav-link active" href="{{ route('dashboard') }}" aria-current="page">

                    <span class="nav-icon">
                        <i class="bi bi-speedometer2"></i>
                    </span>

                    <span class="nav-text">Dashboard</span>
                </a>
            </li>

            <li class="nav-item">

                <a class="nav-link" href="#inventorySubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
                    aria-controls="inventorySubmenu">

                    <span class="nav-icon">
                        <i class="bi bi-people"></i>
                    </span>

                    <span class="nav-text">Inventory</span>

                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>

                </a>


                {{-- inventory Sub Menu --}}
                <ul class="collapse nav flex-column submenu" id="inventorySubmenu">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('products.index') }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Products</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.category.index') }}">
                            <span class="nav-icon"><i class="bi bi-person-badge"></i></span>
                            <span class="nav-text">Category</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.brand.index') }}">
                            <span class="nav-icon"><i class="bi bi-shield-check"></i></span>
                            <span class="nav-text">Brands</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.unit.index') }}">
                            <span class="nav-icon"><i class="bi bi-shield-check"></i></span>
                            <span class="nav-text">Units</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.barcode.index') }}">
                            <span class="nav-icon"><i class="bi bi-shield-check"></i></span>
                            <span class="nav-text">Barcodes</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.attribute_types.index') }}">
                            <span class="nav-icon"><i class="bi bi-shield-check"></i></span>
                            <span class="nav-text">Attribute Type</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.attribute.index') }}">
                            <span class="nav-icon"><i class="bi bi-shield-check"></i></span>
                            <span class="nav-text">Attributes</span>
                        </a>
                    </li>

                </ul>

            </li>


            <li class="nav-item">
                <a class="nav-link" href="#purchaseSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
                    aria-controls="purchaseSubmenu">
                    <span class="nav-icon">
                        <i class="bi bi-people"></i>
                    </span>
                    <span class="nav-text">Purchase</span>
                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>
                </a>
                <ul class="collapse nav flex-column submenu" id="purchaseSubmenu">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('purchases.index') }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Purchase List</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('purchases.return.index') }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Purchase Return List</span>
                        </a>
                    </li>
                </ul>

            </li>
            <li class="nav-item">
                <a class="nav-link" href="#saleSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
                    aria-controls="saleSubmenu">
                    <span class="nav-icon">
                        <i class="bi bi-people"></i>
                    </span>
                    <span class="nav-text">Sales</span>
                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>
                </a>
                <ul class="collapse nav flex-column submenu" id="saleSubmenu">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.index', ['type' => 'Walk-In']) }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Walk-In Sale</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.returns.index', ['type' => 'Walk-In']) }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Walk-In Sale Return</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.index', ['type' => 'Wholesale']) }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Whole Sale</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.returns.index', ['type' => 'Wholesale']) }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Whole Sale Return</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.orders.index') }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Sale Order</span>
                        </a>
                    </li>
                </ul>

            </li>
            <li class="nav-item">
                <a class="nav-link" href="#CRMSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
                    aria-controls="CRMSubmenu">
                    <span class="nav-icon">
                        <i class="bi bi-people"></i>
                    </span>
                    <span class="nav-text">CRM</span>
                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>
                </a>
                <ul class="collapse nav flex-column submenu" id="CRMSubmenu">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('party.index','Customer') }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Customer </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('party.index','Walk-In Customer') }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Walk-In Customer </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('party.index','Supplier') }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Supplier </span>
                        </a>
                    </li>

                </ul>

            </li>

            {{-- Users --}}
            <li class="nav-item">

                <a class="nav-link" href="#usersSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
                    aria-controls="usersSubmenu">

                    <span class="nav-icon">
                        <i class="bi bi-people"></i>
                    </span>

                    <span class="nav-text">User Management</span>

                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>

                </a>


                {{-- Users Sub Menu --}}
                <ul class="collapse nav flex-column submenu" id="usersSubmenu">

                    <li class="nav-item">

                        <a class="nav-link" href="{{ route('admin.users.index') }}">

                            <span class="nav-icon">
                                <i class="bi bi-list-ul"></i>
                            </span>

                            <span class="nav-text">Users</span>

                        </a>

                    </li>
                    <li class="nav-item">

                        <a class="nav-link" href="{{ route('admin.roles.index') }}">

                            <span class="nav-icon">
                                <i class="bi bi-person-badge"></i>
                            </span>

                            <span class="nav-text">Roles</span>

                        </a>

                    </li>


                    <li class="nav-item">

                        <a class="nav-link" href="{{ route('admin.permissions.index') }}">

                            <span class="nav-icon">
                                <i class="bi bi-shield-check"></i>
                            </span>

                            <span class="nav-text">Permissions</span>

                        </a>

                    </li>

                </ul>

            </li>

            {{-- Payments --}}
            <li class="nav-item">

                <a class="nav-link" href="#paymentSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
                    aria-controls="paymentSubmenu">

                    <span class="nav-icon">
                        <i class="bi bi-people"></i>
                    </span>

                    <span class="nav-text">Payments</span>

                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>

                </a>


                {{-- payment Sub Menu --}}
                <ul class="collapse nav flex-column submenu" id="paymentSubmenu">

                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.payment.index','Payment') }}">
                            <span class="nav-icon">
                                <i class="bi bi-list-ul"></i>
                            </span>
                            <span class="nav-text">Payment</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.payment.index','Payment Receive') }}">
                            <span class="nav-icon">
                                <i class="bi bi-list-ul"></i>
                            </span>
                            <span class="nav-text">Payment Receive</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.payment.index','Discount') }}">
                            <span class="nav-icon">
                                <i class="bi bi-list-ul"></i>
                            </span>
                            <span class="nav-text">Discount</span>
                        </a>
                    </li>

                </ul>

            </li>

            <li class="nav-item">
                <a class="nav-link" href="#accountsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
                    aria-controls="accountsSubmenu">
                    <span class="nav-icon">
                        <i class="bi bi-people"></i>
                    </span>
                    <span class="nav-text">Accounts</span>
                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>
                </a>
                <ul class="collapse nav flex-column submenu" id="accountsSubmenu">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.chart-of-accounts.page') }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Chart Of Accounts</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.expense.index') }}">
                            <span class="nav-icon"><i class="bi bi-list-ul"></i></span>
                            <span class="nav-text">Expense</span>
                        </a>
                    </li>

                </ul>

            </li>


            {{-- Profile --}}
            <li class="nav-item">

                <a class="nav-link" href="profile.html">

                    <span class="nav-icon">
                        <i class="bi bi-person-badge"></i>
                    </span>

                    <span class="nav-text">Profile</span>

                </a>

            </li>


            {{-- Charts --}}
            <li class="nav-item">

                <a class="nav-link" href="charts.html">

                    <span class="nav-icon">
                        <i class="bi bi-bar-chart-line"></i>
                    </span>

                    <span class="nav-text">Charts</span>

                </a>

            </li>


            {{-- Tables --}}
            <li class="nav-item">

                <a class="nav-link" href="tables.html">

                    <span class="nav-icon">
                        <i class="bi bi-table"></i>
                    </span>

                    <span class="nav-text">Tables</span>

                </a>

            </li>


            {{-- Forms --}}
            <li class="nav-item">

                <a class="nav-link" href="forms.html">

                    <span class="nav-icon">
                        <i class="bi bi-ui-checks-grid"></i>
                    </span>

                    <span class="nav-text">Forms</span>

                </a>

            </li>


            {{-- Components --}}
            <li class="nav-item">

                <a class="nav-link" href="components.html">

                    <span class="nav-icon">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </span>

                    <span class="nav-text">Components</span>

                </a>

            </li>


            {{-- Alerts --}}
            <li class="nav-item">

                <a class="nav-link" href="alerts.html">

                    <span class="nav-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </span>

                    <span class="nav-text">Alerts</span>

                </a>

            </li>


            {{-- Modals --}}
            <li class="nav-item">

                <a class="nav-link" href="modals.html">

                    <span class="nav-icon">
                        <i class="bi bi-window-stack"></i>
                    </span>

                    <span class="nav-text">Modals</span>

                </a>

            </li>


            {{-- Settings --}}
            <li class="nav-item">

                <a class="nav-link" href="{{ route('admin.settings.index') }}">

                    <span class="nav-icon">
                        <i class="bi bi-gear"></i>
                    </span>

                    <span class="nav-text">Settings</span>

                </a>

            </li>


            {{-- Blank Page --}}
            <li class="nav-item">

                <a class="nav-link" href="blank.html">

                    <span class="nav-icon">
                        <i class="bi bi-file-earmark"></i>
                    </span>

                    <span class="nav-text">Blank Page</span>

                </a>

            </li>

        </ul>

    </nav>


    <div class="sidebar-user">
        <img class="avatar-img avatar-md sidebar-user-avatar" src="{{ asset(Auth::user()->image) }}"
            alt="{{ Auth::user()->name }}">
        <strong>{{ Auth::user()->name }}</strong>
        <small>Active Workspace</small>
    </div>

    <div class="sidebar-footer">
        <span class="status-dot"></span>
        <span class="sidebar-footer-text">System running smoothly</span>
    </div>
</aside>
