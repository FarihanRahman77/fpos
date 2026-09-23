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

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    $setting = Setting::where('deleted', 'No')
        ->where('status', 'Active')
        ->first();
    return view('admin.auth.login', compact('setting'));
});

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
});

require __DIR__ . '/auth.php';
