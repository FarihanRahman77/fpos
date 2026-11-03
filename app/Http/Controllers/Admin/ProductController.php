<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Product;
use App\Models\Admin\ProductAttribute;
use App\Models\Admin\ProductVariant;
use App\Models\Admin\ProductSpec;
use App\Models\Admin\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $brands         = $this->activeList('brands');
        $categories     = $this->activeList('categories');
        $units          = $this->activeList('units');
        $attributeTypes = $this->activeList('attribute_types');
        // VAT on sales is switched on in Settings > VAT, Loyalty & Credit; the form says so when it is off.
        $vatEnabled     = CustomerProgramController::on('vat_enabled');
        $vatRate        = (float) CustomerProgramController::setting('vat_rate');
        // List filters also offer inactive brands / categories (older products may still use them).
        $filterBrands     = DB::table('brands')->where('deleted', 'No')->orderBy('name')->get(['id', 'name', 'status']);
        $filterCategories = DB::table('categories')->where('deleted', 'No')->orderBy('name')->get(['id', 'name', 'status']);

        return view('admin.inventory.products.index', compact(
            'filterBrands',
            'filterCategories',
            'brands',
            'categories',
            'units',
            'attributeTypes',
            'vatEnabled',
            'vatRate'
        ));
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT LIST
    |--------------------------------------------------------------------------
    |
    | One row per product, one value per column. Stock on hand is the sum over
    | the branches the user sees (BranchController::scope), active variants only.
    | Filters, search, ordering and paging run here (DataTables server-side), so
    | the page works with large catalogues. Without a `draw` parameter the
    | endpoint returns every matching row as { status, data, summary }.
    |
    | Stock status: Out = on hand <= 0; Low = on hand > 0 and <= alert qty;
    | In stock = above the alert qty.
    |
    */

    /** Sortable list columns => SQL expression (keys are the DataTables `name` of each column). */
    private const LIST_ORDER = [
        'name'           => 'p.name',
        'sku'            => 'p.sku',
        'barcode'        => 'p.barcode',
        'category_name'  => 'c.name',
        'brand_name'     => 'b.name',
        'unit_name'      => 'u.name',
        'variant_count'  => 'variant_count',
        'purchase_price' => 'p.purchase_price',
        'sale_price'     => 'p.sale_price',
        'old_sale_price' => 'p.old_sale_price',
        'discount'       => 'p.discount',
        'margin'         => 'margin',
        'current_stock'  => 'current_stock',
        'stock_alert'    => 'p.stock_alert',
        'stock_state'    => 'stock_state_rank',
        'track_batch'    => 'p.track_batch',
        'status'         => 'p.status',
        'id'             => 'p.id',
    ];

    /** Price filters: request key prefix => [column, label]. */
    private const PRICE_FILTERS = [
        'purchase_price' => ['p.purchase_price', 'purchase price'],
        'sale_price'     => ['p.sale_price', 'sale price'],
        'old_price'      => ['p.old_sale_price', 'old price'],
    ];

    public function list(Request $request)
    {
        $rules = [
            'category_id'  => 'nullable|integer|exists:categories,id',
            'brand_id'     => 'nullable|integer|exists:brands,id',
            'stock_status' => 'nullable|in:in,low,out',
            'status'       => 'nullable|in:Active,Inactive',
        ];
        $names = [];
        foreach (self::PRICE_FILTERS as $key => [, $label]) {
            $rules[$key . '_min'] = 'nullable|numeric|min:0';
            $rules[$key . '_max'] = 'nullable|numeric|min:0';
            $names[$key . '_min'] = 'minimum ' . $label;
            $names[$key . '_max'] = 'maximum ' . $label;
        }

        $validator = Validator::make($request->all(), $rules, [], $names);

        $validator->after(function ($v) use ($request) {
            foreach (self::PRICE_FILTERS as $key => [, $label]) {
                $min = $request->input($key . '_min');
                $max = $request->input($key . '_max');
                if (is_numeric($min) && is_numeric($max) && (float) $min > (float) $max) {
                    $v->errors()->add($key . '_max', 'The maximum ' . $label . ' must be at least the minimum.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {

            $stock = 'COALESCE(stock_data.current_stock, 0)';

            // Current stock in the visible branches (only active variants are counted).
            $stockSubQuery = BranchController::scope(DB::table('stocks as s'), 's.branch_id')
                ->leftJoin('product_variants as pv', function ($join) {
                    $join->on('pv.id', '=', 's.product_variant_id')
                        ->where('pv.deleted', 'No');
                })
                ->select(
                    's.product_id',
                    DB::raw('SUM(s.in_qty) - SUM(s.out_qty) as current_stock')
                )
                ->where('s.deleted', 'No')
                ->where(function ($query) {
                    $query->whereNull('s.product_variant_id')
                        ->orWhereNotNull('pv.id');
                })
                ->groupBy('s.product_id');

            $variantSubQuery = DB::table('product_variants')
                ->select('product_id', DB::raw('COUNT(*) as variant_count'))
                ->where('deleted', 'No')
                ->groupBy('product_id');

            $base = DB::table('products as p')
                ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
                ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
                ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
                ->leftJoinSub($stockSubQuery, 'stock_data', function ($join) {
                    $join->on('stock_data.product_id', '=', 'p.id');
                })
                ->leftJoinSub($variantSubQuery, 'variant_data', function ($join) {
                    $join->on('variant_data.product_id', '=', 'p.id');
                })
                ->where('p.deleted', 'No');

            $total = DB::table('products')->where('deleted', 'No')->count();

            // Filters.
            $base->when($request->filled('category_id'), fn ($q) => $q->where('p.category_id', (int) $request->input('category_id')))
                ->when($request->filled('brand_id'), fn ($q) => $q->where('p.brand_id', (int) $request->input('brand_id')))
                ->when($request->filled('status'), fn ($q) => $q->where('p.status', $request->input('status')));

            foreach (self::PRICE_FILTERS as $key => [$column]) {
                if ($request->filled($key . '_min')) {
                    $base->where($column, '>=', (float) $request->input($key . '_min'));
                }
                if ($request->filled($key . '_max')) {
                    $base->where($column, '<=', (float) $request->input($key . '_max'));
                }
            }

            switch ($request->input('stock_status')) {
                case 'out':
                    $base->whereRaw("$stock <= 0");
                    break;
                case 'low':
                    $base->whereRaw("$stock > 0 AND $stock <= p.stock_alert");
                    break;
                case 'in':
                    $base->whereRaw("$stock > 0 AND $stock > p.stock_alert");
                    break;
            }

            // DataTables search box: name, SKU, barcodes (product + variants), category, brand.
            $search = trim((string) data_get($request->input('search'), 'value', ''));
            if ($search !== '') {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
                $base->where(function ($q) use ($like) {
                    $q->where('p.name', 'like', $like)
                        ->orWhere('p.sku', 'like', $like)
                        ->orWhere('p.barcode', 'like', $like)
                        ->orWhere('c.name', 'like', $like)
                        ->orWhere('b.name', 'like', $like)
                        ->orWhereExists(function ($v) use ($like) {
                            $v->select(DB::raw(1))->from('product_variants as sv')
                                ->whereColumn('sv.product_id', 'p.id')
                                ->where('sv.deleted', 'No')
                                ->where('sv.barcode', 'like', $like);
                        });
                });
            }

            // Summary of the filtered set (cards above the table). Stock value at purchase price.
            $sum = (clone $base)->selectRaw(
                "COUNT(*) as products,
                 COALESCE(SUM(CASE WHEN $stock > 0 AND $stock > p.stock_alert THEN 1 ELSE 0 END), 0) as in_stock,
                 COALESCE(SUM(CASE WHEN $stock > 0 AND $stock <= p.stock_alert THEN 1 ELSE 0 END), 0) as low_stock,
                 COALESCE(SUM(CASE WHEN $stock <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock,
                 COALESCE(SUM(CASE WHEN $stock > 0 THEN $stock * p.purchase_price ELSE 0 END), 0) as stock_value"
            )->first();

            $summary = [
                'products'     => (int) $sum->products,
                'in_stock'     => (int) $sum->in_stock,
                'low_stock'    => (int) $sum->low_stock,
                'out_of_stock' => (int) $sum->out_of_stock,
                'stock_value'  => round((float) $sum->stock_value, 2),
                'total'        => $total,
            ];

            $rows = (clone $base)->select(
                'p.id',
                'p.name',
                'p.sku',
                'p.barcode',
                'p.image',
                'p.stock_alert',
                'p.track_batch',
                'p.purchase_price',
                'p.sale_price',
                'p.old_sale_price',
                'p.discount',
                'p.status',
                'b.name as brand_name',
                'c.name as category_name',
                'u.name as unit_name',
                DB::raw('COALESCE(variant_data.variant_count, 0) as variant_count'),
                DB::raw("$stock as current_stock"),
                DB::raw('CASE WHEN p.sale_price > 0 THEN (p.sale_price - p.purchase_price) / p.sale_price * 100 END as margin'),
                DB::raw("CASE WHEN $stock <= 0 THEN 'out' WHEN $stock <= p.stock_alert THEN 'low' ELSE 'in' END as stock_state"),
                DB::raw("CASE WHEN $stock <= 0 THEN 0 WHEN $stock <= p.stock_alert THEN 1 ELSE 2 END as stock_state_rank")
            );

            // Ordering: only columns whose DataTables `name` is in LIST_ORDER.
            foreach ((array) $request->input('order', []) as $order) {
                $key = data_get($request->input('columns'), (int) ($order['column'] ?? -1) . '.name');
                if (is_string($key) && isset(self::LIST_ORDER[$key])) {
                    $rows->orderBy(DB::raw(self::LIST_ORDER[$key]), strtolower($order['dir'] ?? '') === 'desc' ? 'desc' : 'asc');
                }
            }
            $rows->orderByDesc('p.id');

            if (!$request->has('draw')) {
                return response()->json([
                    'status'  => true,
                    'data'    => $rows->get(),
                    'summary' => $summary,
                ]);
            }

            $length = (int) $request->input('length', 25);
            if ($length > 0) {
                $rows->offset(max(0, (int) $request->input('start', 0)))->limit(min($length, 1000));
            }

            return response()->json([
                'status'          => true,
                'draw'            => (int) $request->input('draw'),
                'recordsTotal'    => $total,
                'recordsFiltered' => $summary['products'],
                'summary'         => $summary,
                'data'            => $rows->get(),
            ]);
        } catch (\Throwable $e) {
            return $this->serverError($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GET ATTRIBUTES BY ATTRIBUTE TYPE
    |--------------------------------------------------------------------------
    */

    public function attributes($attributeTypeId)
    {
        try {

            $attributes = DB::table('attributes')
                ->where('attribute_type_id', $attributeTypeId)
                ->where('deleted', 'No')
                ->where('status', 'Active')
                ->orderBy('name')
                ->get();

            return response()->json([
                'status' => true,
                'data'   => $attributes
            ]);
        } catch (\Throwable $e) {
            return $this->serverError($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT PRODUCT DATA
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        try {

            $product = Product::where('id', $id)
                ->where('deleted', 'No')
                ->first();

            if (!$product) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Product not found.'
                ], 404);
            }


            // Current stock (only active variants are included).
            $currentStock = BranchController::scope(DB::table('stocks as s'), 's.branch_id')
                ->leftJoin('product_variants as pv', function ($join) {
                    $join->on('pv.id', '=', 's.product_variant_id')
                        ->where('pv.deleted', 'No');
                })
                ->where('s.product_id', $id)
                ->where('s.deleted', 'No')
                ->where(function ($query) {
                    $query->whereNull('s.product_variant_id')
                        ->orWhereNotNull('pv.id');
                })
                ->selectRaw('COALESCE(SUM(s.in_qty),0) - COALESCE(SUM(s.out_qty),0) as current_stock')
                ->value('current_stock');

            $currentStock = $currentStock ?? 0;


            // Product attributes.
            $productAttributes = DB::table('product_attributes as pa')
                ->join('attributes as a', 'a.id', '=', 'pa.attribute_id')
                ->where('pa.product_id', $id)
                ->where('pa.deleted', 'No')
                ->where('pa.status', 'Active')
                ->where('a.deleted', 'No')
                ->where('a.status', 'Active')
                ->select(
                    'pa.attribute_type_id',
                    'pa.attribute_id',
                    'a.name as attribute_name'
                )
                ->orderBy('pa.id')
                ->get();


            // Active variants together with their attribute type (single query).
            $variants = DB::table('product_variants as pv')
                ->join('attributes as a', 'a.id', '=', 'pv.attribute_id')
                ->where('pv.product_id', $id)
                ->where('pv.deleted', 'No')
                ->where('pv.status', 'Active')
                ->select('pv.*', 'a.attribute_type_id')
                ->orderBy('pv.id')
                ->get();


            // Group attributes by attribute type.
            $attributes = [];

            foreach ($productAttributes as $item) {

                $typeId = (string) $item->attribute_type_id;

                if (!isset($attributes[$typeId])) {
                    $attributes[$typeId] = [
                        'attribute_type_id' => $item->attribute_type_id,
                        'attribute_ids'     => [],
                        'variants'          => []
                    ];
                }

                $already = array_map('strval', $attributes[$typeId]['attribute_ids']);

                if (!in_array((string) $item->attribute_id, $already, true)) {
                    $attributes[$typeId]['attribute_ids'][] = $item->attribute_id;
                }
            }


            // Put variants into their attribute type group.
            foreach ($variants as $variant) {

                $typeId = (string) $variant->attribute_type_id;

                if (!isset($attributes[$typeId])) {
                    $attributes[$typeId] = [
                        'attribute_type_id' => $variant->attribute_type_id,
                        'attribute_ids'     => [],
                        'variants'          => []
                    ];
                }

                $attributes[$typeId]['variants'][] = [
                    'id'             => $variant->id,
                    'attribute_id'   => $variant->attribute_id,
                    'barcode'        => $variant->barcode,
                    'sale_price'     => $variant->sale_price,
                    'old_sale_price' => $variant->old_sale_price,
                    'opening_stock'  => 0
                ];
            }

            $attributes = array_values($attributes);


            // Specifications.
            $specs = ProductSpec::where('product_id', $id)
                ->where('deleted', 'No')
                ->where('status', 'Active')
                ->orderBy('id')
                ->get();


            return response()->json([
                'status'        => true,
                'product'       => $product,
                'current_stock' => $currentStock,
                'attributes'    => $attributes,
                'specs'         => $specs
            ]);
        } catch (\Throwable $e) {
            return $this->serverError($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->productValidationRules());

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        DB::beginTransaction();

        try {

            [$brand, $category] = $this->resolveBrandAndCategory($request);

            $sku = $this->generateProductSku(
                $request->input('name'),
                $category ? $category->name : '',
                $brand ? $brand->name : ''
            );

            // Use the barcode shown in the form when it is valid and still unique.
            $productBarcode = $this->resolveProductBarcode($request->input('barcode'));

            $product = new Product();

            $product->name = $request->input('name');
            $product->slug = $this->generateUniqueSlug($request->input('name'));

            $product->brand_id    = $request->input('brand_id') ?: null;
            $product->category_id = $request->input('category_id') ?: null;
            $product->unit_id     = $request->input('unit_id') ?: null;

            $product->purchase_price = $request->input('purchase_price') ?? 0;
            $product->sale_price     = $request->input('sale_price') ?? 0;
            $product->old_sale_price = $request->input('old_sale_price') ?? 0;
            $product->discount       = $request->input('discount') ?? 0;
            $product->stock_alert    = $request->input('stock_alert') ?? 0;
            $product->track_batch    = $request->input('track_batch') === 'Yes' ? 'Yes' : 'No';
            $product->vat_percent    = $request->filled('vat_percent') ? min(100, max(0, (float) $request->input('vat_percent'))) : null;

            $product->sku     = $sku;
            $product->barcode = $productBarcode;

            $product->description = $request->input('description');

            $product->forceFill($this->createAudit());
            $product->created_date = now();

            if ($request->hasFile('image')) {
                $product->image = $this->uploadProductImage($request->file('image'));
            }

            $product->save();


            /*
             * IMPORTANT:
             * $request->attributes is Symfony's route-attributes bag,
             * NOT the form input. Always use $request->input('attributes').
             */

            $attributes         = $request->input('attributes', []);
            $attributesVariants = $request->input('attributes_variants', []);

            if ($this->productHasVariants($attributesVariants)) {

                $this->syncProductAttributes(
                    $product->id,
                    $attributes,
                    $attributesVariants
                );
            } else {

                // Normal product opening stock.
                $openingStock = (float) ($request->input('opening_stock') ?? 0);

                if ($openingStock > 0) {
                    $this->createOpeningStock($product->id, null, $openingStock);
                }
            }


            $this->saveProductSpecs($product->id, $request->input('specs', []));

            DB::commit();

            return response()->json([
                'status'     => true,
                'message'    => 'Product created successfully.',
                'product_id' => $product->id,
                'sku'        => $product->sku,
                'barcode'    => $product->barcode
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return $this->serverError($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), $this->productValidationRules($id));

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        DB::beginTransaction();

        try {

            $product = Product::where('id', $id)
                ->where('deleted', 'No')
                ->first();

            if (!$product) {
                throw new \Exception('Product not found.');
            }

            [$brand, $category] = $this->resolveBrandAndCategory($request);


            // Keep existing SKU. Generate only for old data without SKU.
            if (empty($product->sku)) {
                $product->sku = $this->generateProductSku(
                    $request->input('name'),
                    $category ? $category->name : '',
                    $brand ? $brand->name : '',
                    $product->id
                );
            }

            // Keep existing barcode. Generate only for old data without barcode.
            if (empty($product->barcode)) {
                $product->barcode = $this->generateUniqueBarcode();
            }

            // Keep existing slug unless the product name changed.
            if (
                strtolower(trim($product->name)) !==
                strtolower(trim($request->input('name')))
            ) {
                $product->slug = $this->generateUniqueSlug(
                    $request->input('name'),
                    $product->id
                );
            }

            $product->name = $request->input('name');

            $product->brand_id    = $request->input('brand_id') ?: null;
            $product->category_id = $request->input('category_id') ?: null;
            $product->unit_id     = $request->input('unit_id') ?: null;

            $product->purchase_price = $request->input('purchase_price') ?? 0;
            $product->sale_price     = $request->input('sale_price') ?? 0;
            $product->old_sale_price = $request->input('old_sale_price') ?? 0;
            $product->discount       = $request->input('discount') ?? 0;
            $product->stock_alert    = $request->input('stock_alert') ?? 0;
            $product->track_batch    = $request->input('track_batch') === 'Yes' ? 'Yes' : 'No';
            $product->vat_percent    = $request->filled('vat_percent') ? min(100, max(0, (float) $request->input('vat_percent'))) : null;

            $product->description = $request->input('description');

            $product->updated_date = now();
            $product->updated_by   = auth()->id();

            if ($request->hasFile('image')) {
                $product->image = $this->uploadProductImage($request->file('image'));
            }

            $product->save();


            // Sync attributes + variants (see note in store()).
            $this->syncProductAttributes(
                $product->id,
                $request->input('attributes', []),
                $request->input('attributes_variants', [])
            );


            // Replace specifications.
            $this->replaceProductSpecs($product->id, $request->input('specs', []));


            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Product updated successfully.',
                'sku'     => $product->sku,
                'barcode' => $product->barcode
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return $this->serverError($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    public function status($id)
    {
        try {

            $product = Product::where('id', $id)
                ->where('deleted', 'No')
                ->first();

            if (!$product) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Product not found.'
                ], 404);
            }

            $product->status = $product->status === 'Active' ? 'Inactive' : 'Active';

            $product->updated_date = now();
            $product->updated_by   = auth()->id();

            $product->save();

            return response()->json([
                'status'  => true,
                'message' => 'Product status updated successfully.'
            ]);
        } catch (\Throwable $e) {
            return $this->serverError($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        DB::beginTransaction();

        try {

            $product = Product::where('id', $id)
                ->where('deleted', 'No')
                ->first();

            if (!$product) {
                DB::rollBack();

                return response()->json([
                    'status'  => false,
                    'message' => 'Product not found.'
                ], 404);
            }


            // Any stock movement other than its own opening stock (purchase, sale, order, return,
            // transfer, adjustment ... in any branch) means the product is in use.
            $hasTransactions = DB::table('stocks')
                ->where('product_id', $id)
                ->where('deleted', 'No')
                ->where(function ($query) {
                    $query->whereNull('reference_type')
                        ->orWhere('reference_type', '<>', 'Opening Stock')
                        ->orWhereNotNull('purchase_id')
                        ->orWhereNotNull('sale_id')
                        ->orWhereNotNull('damage_id')
                        ->orWhereNotNull('purchase_return_id')
                        ->orWhereNotNull('sale_return_id')
                        ->orWhereNotNull('adjustment_id')
                        ->orWhereNotNull('order_id');
                })
                ->exists();

            // Documents that hold the product without moving stock yet (open orders, quotations ...).
            foreach (['purchase_items', 'sale_items', 'sale_order_items', 'sale_quotation_items'] as $table) {
                if ($hasTransactions) {
                    break;
                }
                $hasTransactions = DB::table($table)->where('product_id', $id)
                    ->when(\Illuminate\Support\Facades\Schema::hasColumn($table, 'deleted'), fn ($q) => $q->where('deleted', 'No'))
                    ->exists();
            }

            if ($hasTransactions) {
                DB::rollBack();

                return response()->json([
                    'status'  => false,
                    'message' => 'This product is used in purchases, sales, orders or stock movements, so it cannot be deleted. Make it Inactive instead.'
                ], 422);
            }


            $product->deleted      = 'Yes';
            $product->deleted_by   = auth()->id();
            $product->updated_date = now();
            $product->updated_by   = auth()->id();
            $product->save();

            $audit = $this->softDeleteAudit();

            ProductAttribute::where('product_id', $id)->where('deleted', 'No')->update($audit);
            ProductVariant::where('product_id', $id)->where('deleted', 'No')->update($audit);
            ProductSpec::where('product_id', $id)->where('deleted', 'No')->update($audit);
            Stock::where('product_id', $id)->where('deleted', 'No')->update($audit);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Product deleted successfully.'
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return $this->serverError($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE ALIAS
    |--------------------------------------------------------------------------
    */

    public function delete($id)
    {
        return $this->destroy($id);
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE PRODUCT SKU + BARCODE (used by the form on change)
    |--------------------------------------------------------------------------
    */

    public function generateCodes(Request $request)
    {
        $brandName    = '';
        $categoryName = '';

        if ($request->input('brand_id')) {
            $brand = DB::table('brands')
                ->where('id', $request->input('brand_id'))
                ->where('deleted', 'No')
                ->first();

            $brandName = $brand->name ?? '';
        }

        if ($request->input('category_id')) {
            $category = DB::table('categories')
                ->where('id', $request->input('category_id'))
                ->where('deleted', 'No')
                ->first();

            $categoryName = $category->name ?? '';
        }

        return response()->json([
            'status'  => true,
            'sku'     => $this->generateProductSku(
                $request->input('name', ''),
                $categoryName,
                $brandName
            ),
            'barcode' => $this->generateUniqueBarcode()
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE UNIQUE VARIANT BARCODES (Apply button)
    |--------------------------------------------------------------------------
    |
    | Returns "count" barcodes that are unique:
    |  - inside the returned batch
    |  - against the barcodes already on the form ("reserved")
    |  - against every product and variant in the database
    |    (soft-deleted rows included)
    |
    */

    public function generateVariantBarcodes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'count'      => ['required', 'integer', 'min:1', 'max:500'],
            'reserved'   => ['nullable', 'array'],
            'reserved.*' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {

            $count = (int) $request->input('count');

            $reserved = array_values(array_filter(
                array_map('trim', (array) $request->input('reserved', []))
            ));

            $barcodes    = [];
            $attempts    = 0;
            $maxAttempts = $count * 20;

            while (count($barcodes) < $count) {

                if (++$attempts > $maxAttempts) {
                    throw new \Exception('Unable to generate unique barcodes. Please try again.');
                }

                $barcode = $this->randomBarcode();

                if (
                    in_array($barcode, $barcodes, true) ||
                    in_array($barcode, $reserved, true)
                ) {
                    continue;
                }

                if ($this->barcodeExists($barcode)) {
                    continue;
                }

                $barcodes[] = $barcode;
            }

            return response()->json([
                'status'   => true,
                'barcodes' => $barcodes
            ]);
        } catch (\Throwable $e) {
            return $this->serverError($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SYNC PRODUCT ATTRIBUTES + VARIANTS
    |--------------------------------------------------------------------------
    |
    | One method for create AND update.
    |
    |  - attribute mappings are re-used (restored) instead of inserting
    |    new duplicate rows every save
    |  - existing variants keep their barcode and stock history
    |  - new variants use the barcode from the form (if valid + unique)
    |  - mappings / variants that are no longer submitted are soft-deleted
    |    at the END, so nothing is wiped by a wrong payload mid-way
    |
    */

    private function syncProductAttributes($productId, $attributes, $attributesVariants)
    {
        if (!is_array($attributes) || !is_array($attributesVariants)) {
            throw new \Exception('Invalid attributes payload.');
        }

        $existingVariants = DB::table('product_variants')
            ->where('product_id', $productId)
            ->where('deleted', 'No')
            ->get()
            ->keyBy('attribute_id');

        $keptMappingIds = [];
        $keptVariantIds = [];
        $processed      = [];

        foreach ($attributes as $groupIndex => $group) {

            if (!is_array($group)) {
                continue;
            }

            $attributeTypeId = $group['attribute_type_id'] ?? null;

            if (!$attributeTypeId) {
                continue;
            }

            $typeExists = DB::table('attribute_types')
                ->where('id', $attributeTypeId)
                ->where('deleted', 'No')
                ->where('status', 'Active')
                ->exists();

            if (!$typeExists) {
                throw new \Exception('Invalid attribute type selected.');
            }

            $attributeIds = array_unique(
                array_filter((array) ($group['attribute_ids'] ?? []))
            );

            $variantRows = (array) ($attributesVariants[$groupIndex] ?? []);

            foreach ($attributeIds as $attributeId) {

                $validAttribute = DB::table('attributes')
                    ->where('id', $attributeId)
                    ->where('attribute_type_id', $attributeTypeId)
                    ->where('deleted', 'No')
                    ->where('status', 'Active')
                    ->exists();

                if (!$validAttribute) {
                    throw new \Exception('Invalid attribute selected.');
                }


                /*
                 * Product attribute mapping (restore or create).
                 */

                $mapping = ProductAttribute::where('product_id', $productId)
                    ->where('attribute_id', $attributeId)
                    ->orderBy('deleted')      // 'No' first
                    ->orderByDesc('id')
                    ->first();

                if ($mapping) {

                    $mapping->forceFill(
                        ['attribute_type_id' => $attributeTypeId] + $this->restoreAudit()
                    )->save();
                } else {

                    $mapping = new ProductAttribute();

                    $mapping->forceFill(
                        [
                            'product_id'        => $productId,
                            'attribute_type_id' => $attributeTypeId,
                            'attribute_id'      => $attributeId,
                        ] + $this->createAudit()
                    )->save();
                }

                $keptMappingIds[] = $mapping->id;


                /*
                 * Variant.
                 */

                $attrKey = (string) $attributeId;

                if (isset($processed[$attrKey])) {
                    continue;
                }

                $submitted = null;

                foreach ($variantRows as $row) {

                    if (
                        is_array($row) &&
                        isset($row['attribute_id']) &&
                        (string) $row['attribute_id'] === $attrKey
                    ) {
                        $submitted = $row;
                        break;
                    }
                }

                $existing = $existingVariants->get($attributeId);

                if (!$submitted) {

                    // Never lose an existing variant because a row was missing.
                    if ($existing) {
                        $keptVariantIds[]  = $existing->id;
                        $processed[$attrKey] = true;
                    }

                    continue;
                }

                $processed[$attrKey] = true;

                $salePrice    = $this->num($submitted['sale_price'] ?? 0);
                $oldSalePrice = $this->num($submitted['old_sale_price'] ?? 0);
                $op_stock   = $this->num($submitted['opening_stock'] ?? 0);


                if ($existing) {

                    $variant = ProductVariant::where('id', $existing->id)
                        ->where('deleted', 'No')
                        ->first();

                    if (!$variant) {
                        continue;
                    }

                    // Keep old barcode (generate only if it is missing).
                    if (empty($variant->barcode)) {
                        $variant->barcode = $this->generateUniqueBarcode();
                    }

                    $variant->sale_price     = $salePrice;
                    $variant->old_sale_price = $oldSalePrice;
                    $variant->updated_date   = now();
                    $variant->updated_by     = auth()->id();

                    $variant->save();

                    $keptVariantIds[] = $variant->id;
                } else {

                    $variant = new ProductVariant();

                    $variant->product_id = $productId;
                    $variant->attribute_type_id = $attributeTypeId;
                    $variant->attribute_id = $attributeId;

                    $variant->barcode = $this->resolveVariantBarcode(
                        $submitted['barcode'] ?? null
                    );

                    $variant->sale_price     = $salePrice;
                    $variant->old_sale_price = $oldSalePrice;
                    $variant->opening_stock = $op_stock;

                    $variant->forceFill($this->createAudit());

                    $variant->save();

                    $keptVariantIds[] = $variant->id;

                    // Opening stock for a NEW variant only.
                    $openingStock = $this->num($submitted['opening_stock'] ?? 0);

                    if ($openingStock > 0) {
                        $this->createOpeningStock($productId, $variant->id, $openingStock);
                    }
                }
            }
        }


        /*
         * Soft-delete whatever was not submitted.
         *
         * Stock history is NOT deleted. Current-stock queries already
         * ignore deleted variants.
         */

        ProductAttribute::where('product_id', $productId)
            ->where('deleted', 'No')
            ->whereNotIn('id', $keptMappingIds)
            ->update($this->softDeleteAudit());

        $dropped = ProductVariant::where('product_id', $productId)
            ->where('deleted', 'No')
            ->whereNotIn('id', $keptVariantIds)
            ->pluck('id');

        // A variant that has been bought, sold or moved keeps its stock and history: it cannot be removed.
        if ($dropped->isNotEmpty()) {
            $used = DB::table('stocks')->whereIn('product_variant_id', $dropped)->where('deleted', 'No')
                ->where(fn ($q) => $q->whereNull('reference_type')->orWhere('reference_type', '<>', 'Opening Stock'))
                ->value('product_variant_id');
            if ($used) {
                $label = DB::table('product_variants as v')->leftJoin('attributes as a', 'a.id', '=', 'v.attribute_id')
                    ->where('v.id', $used)->value('a.name');
                throw new \Exception('Variant "' . ($label ?: '#' . $used) . '" has stock movements and cannot be removed. Make the product inactive instead.');
            }
        }

        ProductVariant::where('product_id', $productId)
            ->where('deleted', 'No')
            ->whereIn('id', $dropped)
            ->update($this->softDeleteAudit());
    }


    /*
    |--------------------------------------------------------------------------
    | SPECS
    |--------------------------------------------------------------------------
    */

    private function saveProductSpecs($productId, $specs)
    {
        foreach ((array) $specs as $specData) {

            if (!is_array($specData)) {
                continue;
            }

            $specName  = trim($specData['spec_name'] ?? '');
            $specValue = trim($specData['spec_value'] ?? '');

            if ($specName === '' && $specValue === '') {
                continue;
            }

            $spec = new ProductSpec();

            $spec->product_id = $productId;
            $spec->spec_name  = $specName;
            $spec->spec_value = $specValue;

            $spec->forceFill($this->createAudit());

            $spec->save();
        }
    }


    private function replaceProductSpecs($productId, $specs)
    {
        ProductSpec::where('product_id', $productId)
            ->where('deleted', 'No')
            ->update($this->softDeleteAudit());

        $this->saveProductSpecs($productId, $specs);
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT HAS VARIANTS
    |--------------------------------------------------------------------------
    */

    private function productHasVariants($attributesVariants)
    {
        if (!is_array($attributesVariants)) {
            return false;
        }

        foreach ($attributesVariants as $group) {

            if (!is_array($group)) {
                continue;
            }

            foreach ($group as $variant) {

                if (is_array($variant) && !empty($variant['attribute_id'])) {
                    return true;
                }
            }
        }

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | OPENING STOCK
    |--------------------------------------------------------------------------
    */

    private function createOpeningStock($productId, $variantId, $openingStock)
    {
        $stock = new Stock();

        $stock->product_id         = $productId;
        $stock->product_variant_id = $variantId;

        $stock->in_qty  = $openingStock;
        $stock->out_qty = 0;

        $stock->purchase_id        = null;
        $stock->sale_id            = null;
        $stock->damage_id          = null;
        $stock->purchase_return_id = null;
        $stock->sale_return_id     = null;

        $stock->date           = now();
        $stock->reference_type = 'Opening Stock';

        $stock->forceFill($this->createAudit());

        $stock->save();

        return $stock;
    }


    /*
    |--------------------------------------------------------------------------
    | SKU
    |--------------------------------------------------------------------------
    |
    | Short code: Category (3) - Brand (3) - Name (3 letters of up to 4 words; sizes like 400G kept)
    | e.g. "Nestle Cerelac Rice 400g", Baby Care, Nestle  ->  BAB-NES-NESCERRIC400G
    | A number is appended when the code is taken. Existing SKUs are never changed.
    |
    */

    private function generateProductSku(
        $productName,
        $categoryName = '',
        $brandName = '',
        $ignoreProductId = null
    ) {
        $words = fn ($text) => array_values(array_filter(explode('-', Str::upper(Str::slug((string) $text, '-')))));
        $short = fn ($text) => substr(implode('', $words($text)), 0, 3);

        $nameCode = '';
        foreach (array_slice($words($productName), 0, 4) as $word) {
            $nameCode .= preg_match('/\d/', $word) ? substr($word, 0, 6) : substr($word, 0, 3);
        }

        $base = implode('-', array_filter([$short($categoryName), $short($brandName), $nameCode]));

        if ($base === '') {
            $base = 'PRODUCT';
        }

        $base = substr($base, 0, 40);

        $sku     = $base;
        $counter = 1;

        while (true) {

            $query = Product::where('sku', $sku);

            if ($ignoreProductId) {
                $query->where('id', '!=', $ignoreProductId);
            }

            if (!$query->exists()) {
                break;
            }

            $counter++;

            $suffix = '-' . $counter;

            $sku = substr($base, 0, 100 - strlen($suffix)) . $suffix;
        }

        return $sku;
    }


    /*
    |--------------------------------------------------------------------------
    | BARCODES
    |--------------------------------------------------------------------------
    |
    | 13 digit numeric barcode, starts with 2 so it does not look like
    | a normal retail EAN prefix. Unique across products AND variants.
    |
    */

    private function randomBarcode(): string
    {
        return '2' . str_pad(
            (string) random_int(0, 999999999999),
            12,
            '0',
            STR_PAD_LEFT
        );
    }


    private function barcodeExists(string $barcode): bool
    {
        return Product::where('barcode', $barcode)->exists()
            || ProductVariant::where('barcode', $barcode)->exists();
    }


    private function generateUniqueBarcode(): string
    {
        do {
            $barcode = $this->randomBarcode();
        } while ($this->barcodeExists($barcode));

        return $barcode;
    }


    // Use the submitted barcode if valid and unique, otherwise generate one.
    private function resolveBarcode($submitted = null): string
    {
        $submitted = trim((string) $submitted);

        if (
            preg_match('/^\d{13}$/', $submitted) &&
            !$this->barcodeExists($submitted)
        ) {
            return $submitted;
        }

        return $this->generateUniqueBarcode();
    }


    private function resolveVariantBarcode($submitted = null): string
    {
        return $this->resolveBarcode($submitted);
    }


    private function resolveProductBarcode($submitted = null): string
    {
        return $this->resolveBarcode($submitted);
    }


    /*
    |--------------------------------------------------------------------------
    | SLUG
    |--------------------------------------------------------------------------
    */

    private function generateUniqueSlug($name, $ignoreProductId = null)
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'product';
        }

        $slug    = $baseSlug;
        $counter = 1;

        while (true) {

            $query = Product::where('slug', $slug);

            if ($ignoreProductId) {
                $query->where('id', '!=', $ignoreProductId);
            }

            if (!$query->exists()) {
                break;
            }

            $counter++;

            $slug = $baseSlug . '-' . $counter;
        }

        return $slug;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATION RULES
    |--------------------------------------------------------------------------
    */

    private function productValidationRules($productId = null)
    {
        return [

            'name'           => ['required', 'string', 'max:255'],

            'brand_id'       => ['nullable', 'integer'],
            'category_id'    => ['nullable', 'integer'],
            'unit_id'        => ['nullable', 'integer'],

            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price'     => ['required', 'numeric', 'min:0'],
            'old_sale_price' => ['nullable', 'numeric', 'min:0'],
            'discount'       => ['nullable', 'numeric', 'min:0'],
            'stock_alert'    => ['nullable', 'numeric', 'min:0'],
            'opening_stock'  => ['nullable', 'numeric', 'min:0'],

            'image'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],

            'attributes'                      => ['nullable', 'array'],
            'attributes.*.attribute_type_id'  => ['nullable', 'integer'],
            'attributes.*.attribute_ids'      => ['nullable', 'array'],
            'attributes.*.attribute_ids.*'    => ['nullable', 'integer'],

            'attributes_variants'                          => ['nullable', 'array'],
            'attributes_variants.*'                        => ['nullable', 'array'],
            'attributes_variants.*.*.attribute_id'         => ['nullable', 'integer'],
            'attributes_variants.*.*.sale_price'           => ['nullable', 'numeric', 'min:0'],
            'attributes_variants.*.*.old_sale_price'       => ['nullable', 'numeric', 'min:0'],
            'attributes_variants.*.*.opening_stock'        => ['nullable', 'numeric', 'min:0'],

            'specs'          => ['nullable', 'array'],
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD PRODUCT IMAGE
    |--------------------------------------------------------------------------
    */

    private function uploadProductImage($file)
    {
        $directory = public_path('uploads/products');

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = time() . '_' . Str::random(10) . '.' . $file->guessExtension();

        $file->move($directory, $filename);

        return $filename;
    }


    /*
    |--------------------------------------------------------------------------
    | SMALL HELPERS
    |--------------------------------------------------------------------------
    */

    private function activeList(string $table)
    {
        return DB::table($table)
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();
    }


    private function resolveBrandAndCategory(Request $request): array
    {
        $brand    = null;
        $category = null;

        if ($request->input('brand_id')) {

            $brand = DB::table('brands')
                ->where('id', $request->input('brand_id'))
                ->where('deleted', 'No')
                ->where('status', 'Active')
                ->first();

            if (!$brand) {
                throw new \Exception('Selected brand is invalid.');
            }
        }

        if ($request->input('category_id')) {

            $category = DB::table('categories')
                ->where('id', $request->input('category_id'))
                ->where('deleted', 'No')
                ->where('status', 'Active')
                ->first();

            if (!$category) {
                throw new \Exception('Selected category is invalid.');
            }
        }

        return [$brand, $category];
    }


    private function num($value): float
    {
        return is_numeric($value) ? (float) $value : 0;
    }


    private function createAudit(): array
    {
        return [
            'deleted'      => 'No',
            'deleted_by'   => null,
            'created_date' => now(),
            'created_by'   => auth()->id(),
            'updated_date' => now(),
            'updated_by'   => auth()->id(),
            'status'       => 'Active',
        ];
    }


    private function restoreAudit(): array
    {
        return [
            'deleted'      => 'No',
            'deleted_by'   => null,
            'updated_date' => now(),
            'updated_by'   => auth()->id(),
            'status'       => 'Active',
        ];
    }


    private function softDeleteAudit(): array
    {
        return [
            'deleted'      => 'Yes',
            'deleted_by'   => auth()->id(),
            'updated_date' => now(),
            'updated_by'   => auth()->id(),
        ];
    }


    private function validationError($validator)
    {
        return response()->json([
            'status' => false,
            'errors' => $validator->errors()
        ], 422);
    }


    private function serverError(\Throwable $e)
    {
        // Messages thrown on purpose here ('Selected brand is invalid.' ...) are for the user;
        // database / PHP errors are logged and replaced with a plain message.
        if (get_class($e) === \Exception::class) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 422);
        }

        \Illuminate\Support\Facades\Log::error('Product error: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

        return response()->json([
            'status'  => false,
            'message' => 'Something went wrong. Please try again.'
        ], 500);
    }
}
