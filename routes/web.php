<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use Illuminate\Support\Facades\Route;
use App\Models\Admin\Setting;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\AttributeTypeController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\BarcodeController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\PartyController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\ChartOfAccountController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\BillController;
use App\Http\Controllers\Admin\AmountTransferController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ReportController;



Route::get('/', function () {
    $setting = Setting::where('deleted', 'No')
        ->where('status', 'Active')
        ->first();
    return view('admin.auth.login', compact('setting'));
})->name('customLogin');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware(['auth', 'verified']);

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/general-settings', [SettingController::class, 'index'])->name('admin.settings.index');
    Route::post('/settings/update', [SettingController::class, 'update'])->name('admin.settings.update');

    Route::prefix('users')->name('admin.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/list', [UserController::class, 'list'])->name('list');
        Route::post('/store', [UserController::class, 'store'])->name('store');
        Route::get('/edit/{id}', [UserController::class, 'edit'])->name('edit');
        Route::post('/update/{id}', [UserController::class, 'update'])->name('update');
        Route::post('/delete/{id}', [UserController::class, 'destroy'])->name('delete');
        Route::get('/profile/{id}', [UserController::class, 'profile'])->name('profile');
    });
    Route::prefix('roles')->name('admin.roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('/list', [RoleController::class, 'list'])->name('list');
        Route::post('/store', [RoleController::class, 'store'])->name('store');
        Route::get('/edit/{id}', [RoleController::class, 'edit'])->name('edit');
        Route::post('/update/{id}', [RoleController::class, 'update'])->name('update');
        Route::post('/delete/{id}', [RoleController::class, 'destroy'])->name('delete');
        Route::get('/permissions/{id}', [RoleController::class, 'permissions'])->name('permissions');
        Route::post('/permissions/{id}', [RoleController::class, 'updatePermissions'])->name('permissions.update');
    });
    /* |-------------------------------------------------------------------------- | Permissions |-------------------------------------------------------------------------- */
    Route::prefix('permissions')->name('admin.permissions.')->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->name('index');
        Route::get('/list', [PermissionController::class, 'list'])->name('list');
        Route::post('/store', [PermissionController::class, 'store'])->name('store');
        Route::get('/edit/{id}', [PermissionController::class, 'edit'])->name('edit');
        Route::post('/update/{id}', [PermissionController::class, 'update'])->name('update');
        Route::post('/delete/{id}', [PermissionController::class, 'destroy'])->name('delete');
    });



    /*
|--------------------------------------------------------------------------
| Category
|--------------------------------------------------------------------------
*/

    Route::prefix('admin/category')->name('admin.category.')->group(function () {

        Route::get('/', [CategoryController::class, 'index'])
            ->name('index');

        Route::get('/list', [CategoryController::class, 'getCategories'])
            ->name('list');

        Route::post('/save', [CategoryController::class, 'save'])
            ->name('save');

        Route::get('/edit/{id}', [CategoryController::class, 'edit'])
            ->name('edit');

        Route::post('/delete', [CategoryController::class, 'delete'])
            ->name('delete');

        Route::post('/status', [CategoryController::class, 'status'])
            ->name('status');
    });


    /*
|--------------------------------------------------------------------------
| Brand
|--------------------------------------------------------------------------
*/

    Route::prefix('admin/brand')->name('admin.brand.')->group(function () {

        Route::get('/', [BrandController::class, 'index'])
            ->name('index');

        Route::get('/list', [BrandController::class, 'getBrands'])
            ->name('list');

        Route::post('/save', [BrandController::class, 'save'])
            ->name('save');

        Route::get('/edit/{id}', [BrandController::class, 'edit'])
            ->name('edit');

        Route::post('/delete/{id}', [BrandController::class, 'delete'])
            ->name('delete');

        Route::post('/status/{id}', [BrandController::class, 'status'])
            ->name('status');
    });


    /*
|--------------------------------------------------------------------------
| Unit
|--------------------------------------------------------------------------
*/

    Route::prefix('admin/unit')->name('admin.unit.')->group(function () {

        Route::get('/', [UnitController::class, 'index'])
            ->name('index');

        Route::get('/list', [UnitController::class, 'getUnits'])
            ->name('list');

        Route::post('/save', [UnitController::class, 'save'])
            ->name('save');

        Route::get('/edit/{id}', [UnitController::class, 'edit'])
            ->name('edit');

        Route::post('/delete/{id}', [UnitController::class, 'delete'])
            ->name('delete');

        Route::post('/status/{id}', [UnitController::class, 'status'])
            ->name('status');
    });

    Route::prefix('admin/attribute_types')->name('admin.attribute_types.')->group(function () {
        Route::get('/index', [AttributeTypeController::class, 'index'])->name('index');
        Route::get('/list', [AttributeTypeController::class, 'getAttributeTypes'])->name('list');
        Route::post('/store', [AttributeTypeController::class, 'store'])->name('store');
        Route::get('/edit/{id}', [AttributeTypeController::class, 'edit'])->name('edit');
        Route::post('/update/{id}', [AttributeTypeController::class, 'update'])->name('update');
        Route::post('/delete/{id}', [AttributeTypeController::class, 'destroy'])->name('delete');
        Route::post('/status/{id}', [AttributeTypeController::class, 'changeStatus'])->name('status');
    });
    /*
|--------------------------------------------------------------------------
| Attribute
|--------------------------------------------------------------------------
*/

    Route::prefix('admin/attribute')->name('admin.attribute.')->group(function () {

        Route::get('/', [AttributeController::class, 'index'])
            ->name('index');

        Route::get('/list', [AttributeController::class, 'getAttributes'])
            ->name('list');

        Route::post('/save', [AttributeController::class, 'save'])
            ->name('save');

        Route::get('/edit/{id}', [AttributeController::class, 'edit'])
            ->name('edit');

        Route::post('/delete/{id}', [AttributeController::class, 'delete'])
            ->name('delete');

        Route::post('/status/{id}', [AttributeController::class, 'status'])
            ->name('status');
    });


    /*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

    Route::prefix('products')->group(function () {

        Route::get('/', [ProductController::class, 'index'])
            ->name('products.index');

        Route::get('/list', [ProductController::class, 'list'])
            ->name('products.list');

        Route::post('/store', [ProductController::class, 'store'])
            ->name('products.store');

        Route::get('/edit/{id}', [ProductController::class, 'edit'])
            ->name('products.edit');

        Route::post('/update/{id}', [ProductController::class, 'update'])
            ->name('products.update');

        Route::match(['post', 'delete'], '/delete/{id}', [ProductController::class, 'destroy'])
            ->name('products.delete');

        Route::post('/status/{id}', [ProductController::class, 'status'])
            ->name('products.status');

        Route::get('/attributes/{attributeTypeId}', [ProductController::class, 'attributes'])
            ->name('products.attributes');

        Route::get('/data', [ProductController::class, 'data'])->name('products.data');

        Route::get('/generate/codes', [ProductController::class, 'generateCodes'])->name('products.generate.codes');
        Route::get('products/generate-variant-barcodes', [ProductController::class, 'generateVariantBarcodes'])->name('products.generate.variant.barcodes');
    });

    Route::get('barcode', [BarcodeController::class, 'index'])->name('admin.barcode.index');
    Route::get('barcode/search', [BarcodeController::class, 'search'])->name('admin.barcode.search');
    Route::get('barcode/variants/{product}', [BarcodeController::class, 'variants'])->name('admin.barcode.variants');
    Route::post('barcode/print', [BarcodeController::class, 'print'])->name('admin.barcode.print');



    Route::prefix('admin')->middleware(['auth'])->group(function () {
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/list', [PurchaseController::class, 'list'])->name('purchases.list');
        Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/purchases/store', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/edit/{id}', [PurchaseController::class, 'edit'])->name('purchases.edit');
        Route::post('/purchases/update/{id}', [PurchaseController::class, 'update'])->name('purchases.update');
        Route::post('/purchases/delete/{id}', [PurchaseController::class, 'delete'])->name('purchases.delete');
        Route::post('/purchases/status/{id}', [PurchaseController::class, 'status'])->name('purchases.status');
        Route::get('/purchases/supplier-due/{supplierId}', [PurchaseController::class, 'supplierDue'])->name('purchases.supplier.due');
        Route::get('/purchases/products/search', [PurchaseController::class, 'productSearch'])->name('purchases.products.search');
        Route::get('/purchases/products/variants/{productId}', [PurchaseController::class, 'variants'])->name('purchases.products.variants');
        Route::get('/purchases/invoice-pdf/{id}', [PurchaseController::class, 'invoicePdf'])->name('purchases.invoice.pdf');

        Route::get('/purchases/return/{id}', [PurchaseController::class, 'returnPage'])->name('purchases.return.page');
        Route::get('/purchases/return/data/{id}', [PurchaseController::class, 'returnData'])->name('purchases.return.data');
        Route::post('/purchases/return/store', [PurchaseController::class, 'storeReturn'])->name('purchases.return.store');
        Route::get('/purchases/returns', [PurchaseController::class, 'returnIndex'])->name('purchases.return.index');
        Route::get('/purchases/returns/list', [PurchaseController::class, 'returnList'])->name('purchases.return.list');
        Route::get('/purchases/returns/view/{id}', [PurchaseController::class, 'returnView'])->name('purchases.return.view');
        Route::post('/purchases/returns/delete/{id}', [PurchaseController::class, 'returnDelete'])->name('purchases.return.delete');
    });


    Route::prefix('admin/parties')->name('party.')->group(function () {
        Route::get('list/{type}', [PartyController::class, 'list'])->name('list');
        Route::get('show', [PartyController::class, 'show'])->name('show');
        Route::post('store', [PartyController::class, 'store'])->name('store');
        Route::post('update', [PartyController::class, 'update'])->name('update');
        Route::post('toggle-status', [PartyController::class, 'toggleStatus'])->name('toggle_status');
        Route::post('delete', [PartyController::class, 'destroy'])->name('delete');

        // keep this last
        Route::get('{type}', [PartyController::class, 'index'])->name('index');
    });


    Route::prefix('admin')->middleware(['auth'])->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Sales
        |--------------------------------------------------------------------------
        */

        Route::get('/sales', [SaleController::class, 'index'])
            ->name('sales.index');

        Route::get('/sales/create', [SaleController::class, 'create'])
            ->name('sales.create');

        Route::get('/sales/list', [SaleController::class, 'list'])
            ->name('sales.list');

        Route::get('/sales/customer-by-phone/{phone}', [SaleController::class, 'customerByPhone'])
            ->name('sales.customer.by.phone');

        Route::get('/sales/customer-due/{customerId}', [SaleController::class, 'customerDue'])
            ->name('sales.customer.due');

        Route::get('/sales/products/search', [SaleController::class, 'productSearch'])
            ->name('sales.products.search');

        Route::get('/sales/products/variants/{productId}', [SaleController::class, 'variants'])
            ->name('sales.products.variants');

        Route::post('/sales/store', [SaleController::class, 'store'])
            ->name('sales.store');

        Route::get('/sales/invoice/{id}/pdf', [SaleController::class, 'invoicePdf'])
            ->name('sales.invoice.pdf');

        Route::post('/sales/delete/{id}', [SaleController::class, 'delete'])
            ->name('sales.delete');


        /*
        |--------------------------------------------------------------------------
        | Sale Returns
        |--------------------------------------------------------------------------
        */

        Route::get('/sales/returns', [SaleController::class, 'returnIndex'])
            ->name('sales.returns.index');

        Route::get('/sales/returns/list', [SaleController::class, 'returnList'])
            ->name('sales.returns.list');

        Route::get('/sales/return/{id}', [SaleController::class, 'returnPage'])
            ->name('sales.return.page');

        Route::get('/sales/return-data/{id}', [SaleController::class, 'returnData'])
            ->name('sales.return.data');

        Route::post('/sales/return/store', [SaleController::class, 'storeReturn'])
            ->name('sales.return.store');

        Route::get('/sales/return/view/{id}', [SaleController::class, 'returnView'])
            ->name('sales.return.view');

        Route::get('/sales/return/invoice/{id}/pdf', [SaleController::class, 'returnInvoicePdf'])
            ->name('sales.return.invoice.pdf');

        Route::post('/sales/return/delete/{id}', [SaleController::class, 'returnDelete'])
            ->name('sales.return.delete');


        /*
        |--------------------------------------------------------------------------
        | Sale Orders
        |--------------------------------------------------------------------------
        */

        Route::get('/sales/orders', [SaleController::class, 'orderIndex'])
            ->name('sales.orders.index');

        Route::get('/sales/orders/create', [SaleController::class, 'orderCreate'])
            ->name('sales.orders.create');

        Route::get('/sales/orders/list', [SaleController::class, 'orderList'])
            ->name('sales.orders.list');

        Route::post('/sales/orders/store', [SaleController::class, 'orderStore'])
            ->name('sales.orders.store');

        Route::get('/sales/orders/invoice/{id}/pdf', [SaleController::class, 'orderInvoicePdf'])
            ->name('sales.order.invoice.pdf');

        Route::post('/sales/orders/delete/{id}', [SaleController::class, 'orderDelete'])
            ->name('sales.orders.delete');

        Route::post('/sales/orders/deliver/{id}', [SaleController::class, 'orderDeliver'])->name('sales.orders.deliver');

        Route::post('/sales/orders/cancel/{id}', [SaleController::class, 'orderCancel'])->name('sales.orders.cancel');

        Route::get('/sales/orders/delivery/{id}', [SaleController::class, 'orderDeliveryPage'])->name('sales.orders.delivery_page');

        Route::post('/sales/orders/delivery/{id}', [SaleController::class, 'orderDelivery'])->name('sales.orders.delivery');

        Route::get('/payment/index/{type}', [PaymentController::class, 'index'])->name('admin.payment.index');
        Route::get('/payment/list/{type}', [PaymentController::class, 'list'])->name('admin.payment.list');
        Route::post('/payment/store/', [PaymentController::class, 'store'])->name('admin.payment.store');
        Route::get('/payment/delete', [PaymentController::class, 'delete'])->name('admin.payment.delete');
        Route::get('/payment/due', [PaymentController::class, 'party_due'])->name('admin.payment.due');

        Route::get('/chart-of-accounts', [ChartOfAccountController::class, 'page'])->name('admin.chart-of-accounts.page');
        Route::get('/chart-of-accounts/tree', [ChartOfAccountController::class, 'index'])->name('admin.chart-of-accounts.index');
        Route::get('/chart-of-accounts/show/{id}', [ChartOfAccountController::class, 'show'])->name('admin.chart-of-accounts.show');
        Route::post('/chart-of-accounts/store', [ChartOfAccountController::class, 'store'])->name('admin.chart-of-accounts.store');
        Route::post('/chart-of-accounts/update/{id}', [ChartOfAccountController::class, 'update'])->name('admin.chart-of-accounts.update');
        Route::post('/chart-of-accounts/destroy/{id}', [ChartOfAccountController::class, 'destroy'])->name('admin.chart-of-accounts.destroy');
        Route::post('/chart-of-accounts/status/{id}', [ChartOfAccountController::class, 'status'])->name('admin.chart-of-accounts.status');
        Route::get('/chart-of-accounts/next-code/{parentId}', [ChartOfAccountController::class, 'nextCode'])->name('admin.chart-of-accounts.next-code');




        Route::get('/expense/index/', [ExpenseController::class, 'index'])->name('admin.expense.index');
        Route::get('/expense/list/', [ExpenseController::class, 'list'])->name('admin.expense.list');
        Route::post('/expense/store/', [ExpenseController::class, 'store'])->name('admin.expense.store');
        Route::get('/expense/delete', [ExpenseController::class, 'delete'])->name('admin.expense.delete');
        Route::get('/expense/show/', [ExpenseController::class, 'show'])->name('admin.expense.show');
        Route::get('/expense/children/', [ExpenseController::class, 'children'])->name('admin.expense.children');
        Route::get('/expense/pdf/', [ExpenseController::class, 'pdf'])->name('admin.expense.pdf');

        // Journal
        Route::get('/journal/index', [JournalController::class, 'index'])->name('admin.journal.index');
        Route::get('/journal/list', [JournalController::class, 'list'])->name('admin.journal.list');
        Route::get('/journal/show', [JournalController::class, 'show'])->name('admin.journal.show');
        Route::post('/journal/store', [JournalController::class, 'store'])->name('admin.journal.store');
        Route::get('/journal/delete', [JournalController::class, 'delete'])->name('admin.journal.delete');

        // Bill + bill payments
        Route::get('/bill/index', [BillController::class, 'index'])->name('admin.bill.index');
        Route::get('/bill/list', [BillController::class, 'list'])->name('admin.bill.list');
        Route::get('/bill/show', [BillController::class, 'show'])->name('admin.bill.show');
        Route::post('/bill/store', [BillController::class, 'store'])->name('admin.bill.store');
        Route::get('/bill/delete', [BillController::class, 'delete'])->name('admin.bill.delete');
        Route::post('/bill/payment/store', [BillController::class, 'paymentStore'])->name('admin.bill.payment.store');
        Route::get('/bill/payment/delete', [BillController::class, 'paymentDelete'])->name('admin.bill.payment.delete');

        // Amount transfer (account to account)
        Route::get('/amount-transfer/index', [AmountTransferController::class, 'index'])->name('admin.transfer.index');
        Route::get('/amount-transfer/list', [AmountTransferController::class, 'list'])->name('admin.transfer.list');
        Route::get('/amount-transfer/show', [AmountTransferController::class, 'show'])->name('admin.transfer.show');
        Route::post('/amount-transfer/store', [AmountTransferController::class, 'store'])->name('admin.transfer.store');
        Route::get('/amount-transfer/delete', [AmountTransferController::class, 'delete'])->name('admin.transfer.delete');

        // Payroll: employees
        Route::get('/employee/index', [EmployeeController::class, 'index'])->name('admin.employee.index');
        Route::get('/employee/list', [EmployeeController::class, 'list'])->name('admin.employee.list');
        Route::get('/employee/show', [EmployeeController::class, 'show'])->name('admin.employee.show');
        Route::get('/employee/search', [EmployeeController::class, 'search'])->name('admin.employee.search');
        Route::post('/employee/store', [EmployeeController::class, 'store'])->name('admin.employee.store');
        Route::post('/employee/status', [EmployeeController::class, 'status'])->name('admin.employee.status');
        Route::get('/employee/delete', [EmployeeController::class, 'delete'])->name('admin.employee.delete');
        Route::get('/employee/id-card', [EmployeeController::class, 'idCard'])->name('admin.employee.id-card');
        Route::get('/employee/profile-pdf', [EmployeeController::class, 'profilePdf'])->name('admin.employee.profile-pdf');

        // Reports
        Route::get('/reports/stock-ledger', [ReportController::class, 'stockLedger'])->name('admin.reports.stock-ledger');
        Route::get('/reports/stock-ledger/data', [ReportController::class, 'stockLedgerData'])->name('admin.reports.stock-ledger.data');
        Route::get('/reports/stock-ledger/pdf', [ReportController::class, 'stockLedgerPdf'])->name('admin.reports.stock-ledger.pdf');
        Route::get('/reports/stock-ledger/excel', [ReportController::class, 'stockLedgerExcel'])->name('admin.reports.stock-ledger.excel');

        // 3rd layer accounts of a 2nd layer group (JSON, used by the account dropdowns)
        Route::get('/chart-of-accounts/ledgers', [ChartOfAccountController::class, 'ledgers'])->name('admin.coa.ledgers');





        //common routes
        Route::get('/getCoaChilds', [ChartOfAccountController::class, 'getCoaChilds'])->name('admin.getCoaChilds');

    });
});

require __DIR__ . '/auth.php';
