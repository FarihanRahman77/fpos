<aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
    <div class="sidebar-header">
        <a class="brand-mark" href="{{ route('dashboard') }}" aria-label="LOOM dashboard">
            @if (!empty($generalSetting->logo))
                <img class="brand-logo" src="{{ asset($generalSetting->logo) }}" alt="{{ $generalSetting->company_name }}">
            @else
                <span class="brand-wordmark">LOOM</span>
            @endif
        </a>
        <button class="sidebar-collapse-toggle" type="button" data-sidebar-collapse-toggle
            aria-controls="adminSidebar" aria-expanded="true" aria-label="Collapse sidebar">
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </button>
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
                <a class="nav-link" href="{{ route('dashboard') }}" aria-label="Dashboard">

                    <span class="nav-icon">
                        <i class="bi bi-speedometer2"></i>
                    </span>

                    <span class="nav-text">Dashboard</span>
                </a>
            </li>

            <li class="nav-item nav-group">

                <button class="nav-link" type="button" data-sidebar-submenu-toggle aria-expanded="false"
                    aria-controls="inventorySubmenu">

                    <span class="nav-icon">
                        <i class="bi bi-boxes"></i>
                    </span>

                    <span class="nav-text">Inventory</span>

                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>

                </button>


                {{-- inventory Sub Menu --}}
                <ul class="nav flex-column submenu" id="inventorySubmenu" aria-hidden="true" inert>
                    <li class="nav-item nav-group">
                        <a class="nav-link" href="{{ route('products.index') }}">
                            <span class="nav-icon"><i class="bi bi-box-seam"></i></span>
                            <span class="nav-text">Products</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.category.index') }}">
                            <span class="nav-icon"><i class="bi bi-folder2-open"></i></span>
                            <span class="nav-text">Category</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.brand.index') }}">
                            <span class="nav-icon"><i class="bi bi-tags"></i></span>
                            <span class="nav-text">Brands</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.unit.index') }}">
                            <span class="nav-icon"><i class="bi bi-rulers"></i></span>
                            <span class="nav-text">Units</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.barcode.index') }}">
                            <span class="nav-icon"><i class="bi bi-upc-scan"></i></span>
                            <span class="nav-text">Barcodes</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.attribute_types.index') }}">
                            <span class="nav-icon"><i class="bi bi-sliders"></i></span>
                            <span class="nav-text">Attribute Type</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.attribute.index') }}">
                            <span class="nav-icon"><i class="bi bi-list-check"></i></span>
                            <span class="nav-text">Attributes</span>
                        </a>
                    </li>

                </ul>

            </li>


            <li class="nav-item nav-group">
                <button class="nav-link" type="button" data-sidebar-submenu-toggle aria-expanded="false"
                    aria-controls="purchaseSubmenu">
                    <span class="nav-icon">
                        <i class="bi bi-cart-check"></i>
                    </span>
                    <span class="nav-text">Purchase</span>
                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>
                </button>
                <ul class="nav flex-column submenu" id="purchaseSubmenu" aria-hidden="true" inert>
                    <li class="nav-item nav-group">
                        <a class="nav-link" href="{{ route('purchases.index') }}">
                            <span class="nav-icon"><i class="bi bi-clipboard-check"></i></span>
                            <span class="nav-text">Purchase List</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('purchases.return.index') }}">
                            <span class="nav-icon"><i class="bi bi-arrow-counterclockwise"></i></span>
                            <span class="nav-text">Purchase Return List</span>
                        </a>
                    </li>
                </ul>

            </li>
            <li class="nav-item nav-group">
                <button class="nav-link" type="button" data-sidebar-submenu-toggle aria-expanded="false"
                    aria-controls="saleSubmenu">
                    <span class="nav-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </span>
                    <span class="nav-text">Sales</span>
                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>
                </button>
                <ul class="nav flex-column submenu" id="saleSubmenu" aria-hidden="true" inert>
                    <li class="nav-item nav-group">
                        <a class="nav-link" href="{{ route('sales.index', ['type' => 'Walk-In']) }}">
                            <span class="nav-icon"><i class="bi bi-shop"></i></span>
                            <span class="nav-text">Walk-In Sale</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.returns.index', ['type' => 'Walk-In']) }}">
                            <span class="nav-icon"><i class="bi bi-arrow-return-left"></i></span>
                            <span class="nav-text">Walk-In Sale Return</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.index', ['type' => 'Wholesale']) }}">
                            <span class="nav-icon"><i class="bi bi-truck"></i></span>
                            <span class="nav-text">Whole Sale</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.returns.index', ['type' => 'Wholesale']) }}">
                            <span class="nav-icon"><i class="bi bi-arrow-90deg-left"></i></span>
                            <span class="nav-text">Whole Sale Return</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.orders.index') }}">
                            <span class="nav-icon"><i class="bi bi-file-earmark-check"></i></span>
                            <span class="nav-text">Sale Order</span>
                        </a>
                    </li>
                </ul>

            </li>
            <li class="nav-item nav-group">
                <button class="nav-link" type="button" data-sidebar-submenu-toggle aria-expanded="false"
                    aria-controls="CRMSubmenu">
                    <span class="nav-icon">
                        <i class="bi bi-person-lines-fill"></i>
                    </span>
                    <span class="nav-text">CRM</span>
                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>
                </button>
                <ul class="nav flex-column submenu" id="CRMSubmenu" aria-hidden="true" inert>
                    <li class="nav-item nav-group">
                        <a class="nav-link" href="{{ route('party.index','Customer') }}">
                            <span class="nav-icon"><i class="bi bi-person"></i></span>
                            <span class="nav-text">Customer </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('party.index','Walk-In Customer') }}">
                            <span class="nav-icon"><i class="bi bi-person-vcard"></i></span>
                            <span class="nav-text">Walk-In Customer </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('party.index','Supplier') }}">
                            <span class="nav-icon"><i class="bi bi-truck-front"></i></span>
                            <span class="nav-text">Supplier </span>
                        </a>
                    </li>

                </ul>

            </li>

            {{-- Users --}}
            <li class="nav-item nav-group">

                <button class="nav-link" type="button" data-sidebar-submenu-toggle aria-expanded="false"
                    aria-controls="usersSubmenu">

                    <span class="nav-icon">
                        <i class="bi bi-person-gear"></i>
                    </span>

                    <span class="nav-text">User Management</span>

                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>

                </button>


                {{-- Users Sub Menu --}}
                <ul class="nav flex-column submenu" id="usersSubmenu" aria-hidden="true" inert>

                    <li class="nav-item nav-group">

                        <a class="nav-link" href="{{ route('admin.users.index') }}">

                            <span class="nav-icon">
                                <i class="bi bi-people"></i>
                            </span>

                            <span class="nav-text">Users</span>

                        </a>

                    </li>
                    <li class="nav-item">

                        <a class="nav-link" href="{{ route('admin.roles.index') }}">

                            <span class="nav-icon">
                                <i class="bi bi-shield-check"></i>
                            </span>

                            <span class="nav-text">Roles</span>

                        </a>

                    </li>


                    <li class="nav-item">

                        <a class="nav-link" href="{{ route('admin.permissions.index') }}">

                            <span class="nav-icon">
                                <i class="bi bi-key"></i>
                            </span>

                            <span class="nav-text">Permissions</span>

                        </a>

                    </li>

                </ul>

            </li>

            {{-- Payments --}}
            <li class="nav-item nav-group">

                <button class="nav-link" type="button" data-sidebar-submenu-toggle aria-expanded="false"
                    aria-controls="paymentSubmenu">

                    <span class="nav-icon">
                        <i class="bi bi-wallet2"></i>
                    </span>

                    <span class="nav-text">Payments</span>

                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>

                </button>


                {{-- payment Sub Menu --}}
                <ul class="nav flex-column submenu" id="paymentSubmenu" aria-hidden="true" inert>

                    <li class="nav-item nav-group">
                        <a class="nav-link" href="{{ route('admin.payment.index','Payment') }}">
                            <span class="nav-icon">
                                <i class="bi bi-cash-coin"></i>
                            </span>
                            <span class="nav-text">Payment</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.payment.index','Payment Receive') }}">
                            <span class="nav-icon">
                                <i class="bi bi-cash-stack"></i>
                            </span>
                            <span class="nav-text">Payment Receive</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.payment.index','Discount') }}">
                            <span class="nav-icon">
                                <i class="bi bi-percent"></i>
                            </span>
                            <span class="nav-text">Discount</span>
                        </a>
                    </li>

                </ul>

            </li>

            <li class="nav-item nav-group">
                <button class="nav-link" type="button" data-sidebar-submenu-toggle aria-expanded="false"
                    aria-controls="accountsSubmenu">
                    <span class="nav-icon">
                        <i class="bi bi-bank"></i>
                    </span>
                    <span class="nav-text">Accounts</span>
                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>
                </button>
                <ul class="nav flex-column submenu" id="accountsSubmenu" aria-hidden="true" inert>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.chart-of-accounts.page') }}">
                            <span class="nav-icon"><i class="bi bi-journal-bookmark"></i></span>
                            <span class="nav-text">Chart Of Accounts</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.expense.index') }}">
                            <span class="nav-icon"><i class="bi bi-receipt"></i></span>
                            <span class="nav-text">Expense</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.bill.index') }}">
                            <span class="nav-icon"><i class="bi bi-file-earmark-text"></i></span>
                            <span class="nav-text">Bill</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.journal.index') }}">
                            <span class="nav-icon"><i class="bi bi-journal-text"></i></span>
                            <span class="nav-text">Journal</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.transfer.index') }}">
                            <span class="nav-icon"><i class="bi bi-arrow-left-right"></i></span>
                            <span class="nav-text">Amount Transfer</span>
                        </a>
                    </li>

                </ul>

            </li>

            <li class="nav-item nav-group">
                <button class="nav-link" type="button" data-sidebar-submenu-toggle aria-expanded="false"
                    aria-controls="payrollSubmenu">
                    <span class="nav-icon">
                        <i class="bi bi-people"></i>
                    </span>
                    <span class="nav-text">Payroll</span>
                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>
                </button>
                <ul class="nav flex-column submenu" id="payrollSubmenu" aria-hidden="true" inert>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.employee.index') }}">
                            <span class="nav-icon"><i class="bi bi-person-badge"></i></span>
                            <span class="nav-text">Employees</span>
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item nav-group">
                <button class="nav-link" type="button" data-sidebar-submenu-toggle aria-expanded="false"
                    aria-controls="reportsSubmenu">
                    <span class="nav-icon">
                        <i class="bi bi-bar-chart-line"></i>
                    </span>
                    <span class="nav-text">Reports</span>
                    <span class="ms-auto">
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </span>
                </button>
                <ul class="nav flex-column submenu" id="reportsSubmenu" aria-hidden="true" inert>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.reports.stock-ledger') }}">
                            <span class="nav-icon"><i class="bi bi-journal-text"></i></span>
                            <span class="nav-text">Stock Ledger</span>
                        </a>
                    </li>
                </ul>
            </li>


            {{-- Profile --}}
            <li class="nav-item">

                <a class="nav-link" href="profile.html">

                    <span class="nav-icon">
                        <i class="bi bi-person-circle"></i>
                    </span>

                    <span class="nav-text">Profile</span>

                </a>

            </li>


            {{-- Charts --}}
            <li class="nav-item">

                <a class="nav-link" href="charts.html">

                    <span class="nav-icon">
                        <i class="bi bi-bar-chart"></i>
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
                        <i class="bi bi-file-earmark-text"></i>
                    </span>

                    <span class="nav-text">Forms</span>

                </a>

            </li>


            {{-- Components --}}
            <li class="nav-item">

                <a class="nav-link" href="components.html">

                    <span class="nav-icon">
                        <i class="bi bi-puzzle"></i>
                    </span>

                    <span class="nav-text">Components</span>

                </a>

            </li>


            {{-- Alerts --}}
            <li class="nav-item">

                <a class="nav-link" href="alerts.html">

                    <span class="nav-icon">
                        <i class="bi bi-bell"></i>
                    </span>

                    <span class="nav-text">Alerts</span>

                </a>

            </li>


            {{-- Modals --}}
            <li class="nav-item">

                <a class="nav-link" href="modals.html">

                    <span class="nav-icon">
                        <i class="bi bi-window"></i>
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
