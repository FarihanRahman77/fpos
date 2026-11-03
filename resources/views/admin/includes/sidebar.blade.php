<aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
    <div class="sidebar-header">
        <a class="brand-mark" href="{{ route('dashboard') }}" aria-label="LOOM dashboard">
            @if (!empty($generalSetting->logo))
                <img class="brand-logo" src="{{ asset($generalSetting->logo) }}" alt="{{ $generalSetting->company_name }}">
            @else
                <span class="brand-wordmark">LOOM</span>
            @endif
        </a>
        <button class="sidebar-collapse-toggle" type="button" data-sidebar-collapse-toggle aria-controls="adminSidebar"
            aria-expanded="true" aria-label="Collapse sidebar">
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </button>
    </div>



    <nav class="sidebar-nav">

        <ul class="nav flex-column">

            {{-- Dashboard --}}
            @perm('dashboard.view')
            <li class="nav-item">
                <a class="nav-link" href="{{ route('dashboard') }}" aria-label="Dashboard">

                    <span class="nav-icon">
                        <i class="bi bi-speedometer2"></i>
                    </span>

                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            @endperm

            @perm(['products.view', 'stock.view'])
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
                    @perm('products.view')
                    <li class="nav-item nav-group">
                        <a class="nav-link" href="{{ route('products.index') }}">
                            <span class="nav-icon"><i class="bi bi-box-seam"></i></span>
                            <span class="nav-text">Products</span>
                        </a>
                    </li>
                    @endperm
                    @perm('products.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.category.index') }}">
                            <span class="nav-icon"><i class="bi bi-folder2-open"></i></span>
                            <span class="nav-text">Category</span>
                        </a>
                    </li>
                    @endperm
                    @perm('products.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.brand.index') }}">
                            <span class="nav-icon"><i class="bi bi-tags"></i></span>
                            <span class="nav-text">Brands</span>
                        </a>
                    </li>
                    @endperm
                    @perm('products.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.unit.index') }}">
                            <span class="nav-icon"><i class="bi bi-rulers"></i></span>
                            <span class="nav-text">Units</span>
                        </a>
                    </li>
                    @endperm
                    @perm('products.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.barcode.index') }}">
                            <span class="nav-icon"><i class="bi bi-upc-scan"></i></span>
                            <span class="nav-text">Barcodes</span>
                        </a>
                    </li>
                    @endperm
                    @perm('products.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.attribute_types.index') }}">
                            <span class="nav-icon"><i class="bi bi-sliders"></i></span>
                            <span class="nav-text">Attribute Type</span>
                        </a>
                    </li>
                    @endperm
                    @perm('products.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.attribute.index') }}">
                            <span class="nav-icon"><i class="bi bi-list-check"></i></span>
                            <span class="nav-text">Attributes</span>
                        </a>
                    </li>
                    @endperm

                    @perm('stock.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.stock.transfers') }}">
                            <span class="nav-icon"><i class="bi bi-arrow-left-right"></i></span>
                            <span class="nav-text">Stock Transfers</span>
                        </a>
                    </li>
                    @endperm
                    @perm('stock.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.stock.adjustments') }}">
                            <span class="nav-icon"><i class="bi bi-clipboard-check"></i></span>
                            <span class="nav-text">Stock Count &amp; Adjustments</span>
                        </a>
                    </li>
                    @endperm
                    @perm('stock.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.stock.batches') }}">
                            <span class="nav-icon"><i class="bi bi-hourglass-split"></i></span>
                            <span class="nav-text">Batches &amp; Expiry</span>
                        </a>
                    </li>
                    @endperm
                </ul>

            </li>
            @endperm


            @perm(['purchases.view', 'purchase_returns.view'])
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
                    @perm('purchases.view')
                    <li class="nav-item nav-group">
                        <a class="nav-link" href="{{ route('purchases.index') }}">
                            <span class="nav-icon"><i class="bi bi-clipboard-check"></i></span>
                            <span class="nav-text">Purchase List</span>
                        </a>
                    </li>
                    @endperm
                    @perm('purchase_returns.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('purchases.return.index') }}">
                            <span class="nav-icon"><i class="bi bi-arrow-counterclockwise"></i></span>
                            <span class="nav-text">Purchase Return List</span>
                        </a>
                    </li>
                    @endperm
                </ul>

            </li>
            @endperm
            @perm(['sales.view', 'sale_returns.view', 'sale_orders.view', 'quotations.view'])
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
                    @perm('sales.view')
                    <li class="nav-item nav-group">
                        <a class="nav-link" href="{{ route('sales.index', ['type' => 'Walk-In']) }}">
                            <span class="nav-icon"><i class="bi bi-shop"></i></span>
                            <span class="nav-text">Walk-In Sale</span>
                        </a>
                    </li>
                    @endperm
                    @perm('sale_returns.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.returns.index', ['type' => 'Walk-In']) }}">
                            <span class="nav-icon"><i class="bi bi-arrow-return-left"></i></span>
                            <span class="nav-text">Walk-In Sale Return</span>
                        </a>
                    </li>
                    @endperm
                    @perm('sales.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.index', ['type' => 'Wholesale']) }}">
                            <span class="nav-icon"><i class="bi bi-truck"></i></span>
                            <span class="nav-text">Whole Sale</span>
                        </a>
                    </li>
                    @endperm
                    @perm('sale_returns.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.returns.index', ['type' => 'Wholesale']) }}">
                            <span class="nav-icon"><i class="bi bi-arrow-90deg-left"></i></span>
                            <span class="nav-text">Whole Sale Return</span>
                        </a>
                    </li>
                    @endperm
                    @perm('sale_orders.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sales.orders.index') }}">
                            <span class="nav-icon"><i class="bi bi-file-earmark-check"></i></span>
                            <span class="nav-text">Sale Order</span>
                        </a>
                    </li>
                    @endperm
                    @perm('quotations.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.quotations.index') }}">
                            <span class="nav-icon"><i class="bi bi-file-earmark-text"></i></span>
                            <span class="nav-text">Quotations</span>
                        </a>
                    </li>
                    @endperm
                </ul>

            </li>
            @endperm
            @perm(['parties.view', 'loyalty_vat.view', 'investors.view'])
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
                    @perm('parties.view')
                    <li class="nav-item nav-group">
                        <a class="nav-link" href="{{ route('party.index', 'Customer') }}">
                            <span class="nav-icon"><i class="bi bi-person"></i></span>
                            <span class="nav-text">Customer </span>
                        </a>
                    </li>
                    @endperm
                    @perm('parties.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('party.index', 'Walk-In Customer') }}">
                            <span class="nav-icon"><i class="bi bi-person-vcard"></i></span>
                            <span class="nav-text">Walk-In Customer </span>
                        </a>
                    </li>
                    @endperm
                    @perm('parties.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('party.index', 'Supplier') }}">
                            <span class="nav-icon"><i class="bi bi-truck-front"></i></span>
                            <span class="nav-text">Supplier </span>
                        </a>
                    </li>
                    @endperm
                    @perm('investors.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('party.index', 'Investor') }}">
                            <span class="nav-icon"><i class="bi bi-briefcase"></i></span>
                            <span class="nav-text">Investors</span>
                        </a>
                    </li>
                    @endperm

                    @perm('loyalty_vat.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.customers.loyalty') }}">
                            <span class="nav-icon"><i class="bi bi-stars"></i></span>
                            <span class="nav-text">Customer Points &amp; Credit</span>
                        </a>
                    </li>
                    @endperm
                    @perm('parties.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.system.sms.due') }}">
                            <span class="nav-icon"><i class="bi bi-chat-dots"></i></span>
                            <span class="nav-text">Due Reminders (SMS)</span>
                        </a>
                    </li>
                    @endperm
                </ul>

            </li>
            @endperm

            {{-- Users --}}
            @perm(['users.view', 'roles.view'])
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

                    @perm('users.view')
                    <li class="nav-item nav-group">

                        <a class="nav-link" href="{{ route('admin.users.index') }}">

                            <span class="nav-icon">
                                <i class="bi bi-people"></i>
                            </span>

                            <span class="nav-text">Users</span>

                        </a>

                    </li>
                    @endperm
                    @perm('roles.view')
                    <li class="nav-item">

                        <a class="nav-link" href="{{ route('admin.roles.index') }}">

                            <span class="nav-icon">
                                <i class="bi bi-shield-check"></i>
                            </span>

                            <span class="nav-text">Roles</span>

                        </a>

                    </li>
                    @endperm


                    @perm('roles.view')
                    <li class="nav-item">

                        <a class="nav-link" href="{{ route('admin.permissions.index') }}">

                            <span class="nav-icon">
                                <i class="bi bi-key"></i>
                            </span>

                            <span class="nav-text">Permissions</span>

                        </a>

                    </li>
                    @endperm

                </ul>

            </li>
            @endperm

            {{-- Payments --}}
            @perm(['payments.view', 'payment_receive.view'])
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

                    @perm('payments.view')
                    <li class="nav-item nav-group">
                        <a class="nav-link" href="{{ route('admin.payment.index', 'Payment') }}">
                            <span class="nav-icon">
                                <i class="bi bi-cash-coin"></i>
                            </span>
                            <span class="nav-text">Payment</span>
                        </a>
                    </li>
                    @endperm
                    @perm('payment_receive.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.payment.index', 'Payment Receive') }}">
                            <span class="nav-icon">
                                <i class="bi bi-cash-stack"></i>
                            </span>
                            <span class="nav-text">Payment Receive</span>
                        </a>
                    </li>
                    @endperm
                    @perm('payments.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.payment.index', 'Discount') }}">
                            <span class="nav-icon">
                                <i class="bi bi-percent"></i>
                            </span>
                            <span class="nav-text">Discount</span>
                        </a>
                    </li>
                    @endperm

                </ul>

            </li>
            @endperm

            @perm(['chart_of_accounts.view', 'expenses.view', 'bills.view', 'journals.view', 'transfers.view', 'suspense.view'])
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
                    @perm('chart_of_accounts.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.chart-of-accounts.page') }}">
                            <span class="nav-icon"><i class="bi bi-journal-bookmark"></i></span>
                            <span class="nav-text">Chart Of Accounts</span>
                        </a>
                    </li>
                    @endperm
                    @perm('expenses.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.expense.index') }}">
                            <span class="nav-icon"><i class="bi bi-receipt"></i></span>
                            <span class="nav-text">Expense</span>
                        </a>
                    </li>
                    @endperm
                    @perm('bills.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.bill.index') }}">
                            <span class="nav-icon"><i class="bi bi-file-earmark-text"></i></span>
                            <span class="nav-text">Bill</span>
                        </a>
                    </li>
                    @endperm
                    @perm('journals.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.journal.index') }}">
                            <span class="nav-icon"><i class="bi bi-journal-text"></i></span>
                            <span class="nav-text">Journal</span>
                        </a>
                    </li>
                    @endperm
                    @perm('transfers.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.transfer.index') }}">
                            <span class="nav-icon"><i class="bi bi-arrow-left-right"></i></span>
                            <span class="nav-text">Amount Transfer</span>
                        </a>
                    </li>
                    @endperm
                    @perm('suspense.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.suspense.index') }}">
                            <span class="nav-icon"><i class="bi bi-wallet2"></i></span>
                            <span class="nav-text">Suspense</span>
                        </a>
                    </li>
                    @endperm

                </ul>

            </li>
            @endperm

            @perm(['employees.view', 'payroll_setup.view', 'payroll_adjustments.view', 'loans.view', 'payroll.view', 'leave.view', 'holidays.view', 'attendance.view'])
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
                    @perm('employees.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.employee.index') }}">
                            <span class="nav-icon"><i class="bi bi-person-badge"></i></span>
                            <span class="nav-text">Employees</span>
                        </a>
                    </li>
                    @endperm
                    @perm('payroll_setup.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.payroll-setup.index') }}">
                            <span class="nav-icon"><i class="bi bi-diagram-3"></i></span>
                            <span class="nav-text">Payroll Setup</span>
                        </a>
                    </li>
                    @endperm
                    @perm('payroll_adjustments.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.payroll-adjustments.index') }}">
                            <span class="nav-icon"><i class="bi bi-gift"></i></span>
                            <span class="nav-text">Bonus &amp; Adjustments</span>
                        </a>
                    </li>
                    @endperm
                    @perm('loans.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.employee-loans.index') }}">
                            <span class="nav-icon"><i class="bi bi-piggy-bank"></i></span>
                            <span class="nav-text">Loans &amp; Advances</span>
                        </a>
                    </li>
                    @endperm
                    @perm('payroll.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.reports.salary-sheet') }}">
                            <span class="nav-icon"><i class="bi bi-cash-stack"></i></span>
                            <span class="nav-text">Salary Sheet</span>
                        </a>
                    </li>
                    @endperm
                    @perm('payroll.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.salary-payments.index') }}">
                            <span class="nav-icon"><i class="bi bi-wallet2"></i></span>
                            <span class="nav-text">Salary Payments</span>
                        </a>
                    </li>
                    @endperm
                    @perm('payroll.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.reports.pf-ledger') }}">
                            <span class="nav-icon"><i class="bi bi-piggy-bank"></i></span>
                            <span class="nav-text">PF Ledger</span>
                        </a>
                    </li>
                    @endperm
                    @perm('leave.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.leave.index') }}">
                            <span class="nav-icon"><i class="bi bi-calendar-x"></i></span>
                            <span class="nav-text">Leave Applications</span>
                        </a>
                    </li>
                    @endperm
                    @perm('leave.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.leave.ledger') }}">
                            <span class="nav-icon"><i class="bi bi-journal-text"></i></span>
                            <span class="nav-text">Leave Ledger</span>
                        </a>
                    </li>
                    @endperm
                    @perm('holidays.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.leave.calendar.index') }}">
                            <span class="nav-icon"><i class="bi bi-calendar3"></i></span>
                            <span class="nav-text">Holiday Calendar</span>
                        </a>
                    </li>
                    @endperm
                    @perm('leave.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.leave.setup.index') }}">
                            <span class="nav-icon"><i class="bi bi-sliders"></i></span>
                            <span class="nav-text">Leave Setup</span>
                        </a>
                    </li>
                    @endperm
                    @perm('attendance.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.attendance.daily') }}">
                            <span class="nav-icon"><i class="bi bi-calendar-check"></i></span>
                            <span class="nav-text">Daily Attendance</span>
                        </a>
                    </li>
                    @endperm
                    @perm('attendance.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.attendance.monthly') }}">
                            <span class="nav-icon"><i class="bi bi-calendar3"></i></span>
                            <span class="nav-text">Attendance Report</span>
                        </a>
                    </li>
                    @endperm
                    @perm('attendance.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.attendance.import') }}">
                            <span class="nav-icon"><i class="bi bi-file-earmark-arrow-up"></i></span>
                            <span class="nav-text">Import Attendance</span>
                        </a>
                    </li>
                    @endperm
                    @perm('attendance.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.attendance.setup') }}">
                            <span class="nav-icon"><i class="bi bi-fingerprint"></i></span>
                            <span class="nav-text">Attendance Setup</span>
                        </a>
                    </li>
                    @endperm
                    @perm('payroll.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.payslips.index') }}">
                            <span class="nav-icon"><i class="bi bi-receipt"></i></span>
                            <span class="nav-text">Payslips</span>
                        </a>
                    </li>
                    @endperm
                </ul>
            </li>
            @endperm

            @perm(['reports.view', 'cash_closing.view', 'statements.view', 'year_end.view', 'loyalty_vat.view'])
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
                    @perm(['reports.view', 'cash_closing.view'])
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.business-reports.index') }}">
                            <span class="nav-icon"><i class="bi bi-grid-1x2"></i></span>
                            <span class="nav-text">Reports Center</span>
                        </a>
                    </li>
                    @endperm
                    @perm(['statements.view', 'year_end.view'])
                    <li class="submenu-label" aria-hidden="true">Accounting</li>
                    @endperm
                    @if (\App\Http\Controllers\Admin\BranchController::isMainBranch())
                    @perm('statements.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.accounting.trial-balance') }}">
                            <span class="nav-icon"><i class="bi bi-journal-check"></i></span>
                            <span class="nav-text">Trial Balance</span>
                        </a>
                    </li>
                    @endperm
                    @endif
                    @perm('statements.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.accounting.profit-loss') }}">
                            <span class="nav-icon"><i class="bi bi-graph-up-arrow"></i></span>
                            <span class="nav-text">Profit &amp; Loss</span>
                        </a>
                    </li>
                    @endperm
                    @if (\App\Http\Controllers\Admin\BranchController::isMainBranch())
                    @perm('statements.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.accounting.balance-sheet') }}">
                            <span class="nav-icon"><i class="bi bi-bank"></i></span>
                            <span class="nav-text">Balance Sheet</span>
                        </a>
                    </li>
                    @endperm
                    @endif
                    @if (\App\Http\Controllers\Admin\BranchController::isMainBranch())
                    @perm('year_end.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.accounting.fiscal-years') }}">
                            <span class="nav-icon"><i class="bi bi-calendar2-check"></i></span>
                            <span class="nav-text">Year-end Closing</span>
                        </a>
                    </li>
                    @endperm
                    @endif
                    @foreach (collect(\App\Http\Controllers\Admin\BusinessReportController::REPORTS)->filter(fn ($r, $k) => \App\Http\Controllers\Admin\PermissionController::allows($k === 'cash-closing' ? 'cash_closing.view' : 'reports.view'))->groupBy(fn ($r) => $r[1], true) as $reportGroup => $groupReports)
                        <li class="submenu-label" aria-hidden="true">{{ $reportGroup }}</li>
                        @foreach ($groupReports as $reportKey => $r)
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.business-reports.show', $reportKey) }}">
                                    <span class="nav-icon"><i class="bi {{ $r[2] }}"></i></span>
                                    <span class="nav-text">{{ $r[0] }}</span>
                                </a>
                            </li>
                        @endforeach
                    @endforeach
                    @perm(['reports.view', 'loyalty_vat.view'])
                    <li class="submenu-label" aria-hidden="true">Ledgers &amp; books</li>
                    @endperm
                    @perm('reports.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.reports.stock-ledger') }}">
                            <span class="nav-icon"><i class="bi bi-journal-text"></i></span>
                            <span class="nav-text">Stock Ledger</span>
                        </a>
                    </li>
                    @endperm
                    @perm('reports.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.reports.party-ledger', 'customer') }}">
                            <span class="nav-icon"><i class="bi bi-person-lines-fill"></i></span>
                            <span class="nav-text">Customer Ledger</span>
                        </a>
                    </li>
                    @endperm
                    @perm('reports.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.reports.party-ledger', 'supplier') }}">
                            <span class="nav-icon"><i class="bi bi-truck"></i></span>
                            <span class="nav-text">Supplier Ledger</span>
                        </a>
                    </li>
                    @endperm
                    @perm('reports.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.reports.transaction-ledger') }}">
                            <span class="nav-icon"><i class="bi bi-arrow-left-right"></i></span>
                            <span class="nav-text">Transactions Ledger</span>
                        </a>
                    </li>
                    @endperm
                    @perm('reports.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.reports.liquid-asset') }}">
                            <span class="nav-icon"><i class="bi bi-bank"></i></span>
                            <span class="nav-text">Cash &amp; Bank Book</span>
                        </a>
                    </li>
                    @endperm
                    @perm('loyalty_vat.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.customers.vat') }}">
                            <span class="nav-icon"><i class="bi bi-percent"></i></span>
                            <span class="nav-text">VAT Report</span>
                        </a>
                    </li>
                    @endperm
                </ul>
            </li>
            @endperm





            {{-- System --}}
            @perm(['branches.view', 'loyalty_vat.view', 'system.view'])
            <li class="nav-item nav-group">
                <button class="nav-link" type="button" data-sidebar-submenu-toggle aria-expanded="false"
                    aria-controls="systemSubmenu">
                    <span class="nav-icon"><i class="bi bi-gear-wide-connected"></i></span>
                    <span class="nav-text">System</span>
                    <span class="ms-auto"><i class="bi bi-chevron-down submenu-arrow"></i></span>
                </button>
                <ul class="nav flex-column submenu" id="systemSubmenu" aria-hidden="true" inert>
                    @perm('branches.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.branches.index') }}">
                            <span class="nav-icon"><i class="bi bi-building"></i></span>
                            <span class="nav-text">Branches</span>
                        </a>
                    </li>
                    @endperm
                    @perm('loyalty_vat.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.customers.settings') }}">
                            <span class="nav-icon"><i class="bi bi-sliders"></i></span>
                            <span class="nav-text">VAT, Loyalty &amp; Credit</span>
                        </a>
                    </li>
                    @endperm
                    @perm('system.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.system.email-sms') }}">
                            <span class="nav-icon"><i class="bi bi-envelope-at"></i></span>
                            <span class="nav-text">Email &amp; SMS</span>
                        </a>
                    </li>
                    @endperm
                    @perm('system.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.system.sms.log') }}">
                            <span class="nav-icon"><i class="bi bi-chat-left-text"></i></span>
                            <span class="nav-text">SMS Log</span>
                        </a>
                    </li>
                    @endperm
                    @perm('system.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.system.backups') }}">
                            <span class="nav-icon"><i class="bi bi-database-down"></i></span>
                            <span class="nav-text">Backups</span>
                        </a>
                    </li>
                    @endperm
                    @perm('system.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.system.activity') }}">
                            <span class="nav-icon"><i class="bi bi-clock-history"></i></span>
                            <span class="nav-text">Activity Log</span>
                        </a>
                    </li>
                    @endperm
                </ul>
            </li>
            @endperm

            {{-- Settings --}}
            @perm('settings.view')
            @if (\App\Http\Controllers\Admin\BranchController::isMainBranch())
            <li class="nav-item nav-group">
                <button class="nav-link" type="button" data-sidebar-submenu-toggle aria-expanded="false"
                    aria-controls="settingsSubmenu">
                    <span class="nav-icon"><i class="bi bi-gear"></i></span>
                    <span class="nav-text">Settings</span>
                    <span class="ms-auto"><i class="bi bi-chevron-down submenu-arrow"></i></span>
                </button>
                <ul class="nav flex-column submenu" id="settingsSubmenu" aria-hidden="true" inert>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.settings.index') }}">
                            <span class="nav-icon"><i class="bi bi-sliders2"></i></span>
                            <span class="nav-text">General Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.settings.theme') }}">
                            <span class="nav-icon"><i class="bi bi-palette"></i></span>
                            <span class="nav-text">Theme Settings</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif
            @endperm




        </ul>

    </nav>


    <div class="sidebar-user">
        @include('admin.includes.avatar', ['user' => Auth::user(), 'class' => 'avatar-md sidebar-user-avatar'])
        <strong>{{ Auth::user()->name }}</strong>
        <small>Active Workspace</small>
    </div>

    <div class="sidebar-footer">
        <span class="status-dot"></span>
        <span class="sidebar-footer-text">System running smoothly</span>
    </div>
</aside>
