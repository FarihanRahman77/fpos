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

        return view('admin.inventory.products.index', compact(
            'brands',
            'categories',
            'units',
            'attributeTypes'
        ));
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT LIST
    |--------------------------------------------------------------------------
    */

    public function list()
    {
        try {

            // Current stock (only active variants are counted).
            $stockSubQuery = DB::table('stocks as s')
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

            // Opening stock.
            $openingStockSubQuery = DB::table('stocks as os')
                ->leftJoin('product_variants as opv', function ($join) {
                    $join->on('opv.id', '=', 'os.product_variant_id')
                        ->where('opv.deleted', 'No');
                })
                ->select(
                    'os.product_id',
                    DB::raw('SUM(os.in_qty) as opening_stock')
                )
                ->where('os.deleted', 'No')
                ->where('os.reference_type', 'Opening Stock')
                ->where(function ($query) {
                    $query->whereNull('os.product_variant_id')
                        ->orWhereNotNull('opv.id');
                })
                ->groupBy('os.product_id');

            $products = DB::table('products as p')
                ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
                ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
                ->leftJoinSub($stockSubQuery, 'stock_data', function ($join) {
                    $join->on('stock_data.product_id', '=', 'p.id');
                })
                ->leftJoinSub($openingStockSubQuery, 'opening_data', function ($join) {
                    $join->on('opening_data.product_id', '=', 'p.id');
                })
                ->select(
                    'p.*',
                    'b.name as brand_name',
                    'c.name as category_name',
                    DB::raw('COALESCE(stock_data.current_stock, 0) as current_stock'),
                    DB::raw('COALESCE(opening_data.opening_stock, 0) as opening_stock')
                )
                ->where('p.deleted', 'No')
                ->orderByDesc('p.id')
                ->get();

            return response()->json([
                'status' => true,
                'data'   => $products
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
            $currentStock = DB::table('stocks as s')
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
            $product->stock_alert    = $request->input('opening_stock') ?? 0;

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


            // Check stock transactions.
            $hasTransactions = DB::table('stocks')
                ->where('product_id', $id)
                ->where('deleted', 'No')
                ->where(function ($query) {
                    $query->whereNotNull('purchase_id')
                        ->orWhereNotNull('sale_id')
                        ->orWhereNotNull('damage_id')
                        ->orWhereNotNull('purchase_return_id')
                        ->orWhereNotNull('sale_return_id');
                })
                ->exists();

            if ($hasTransactions) {
                DB::rollBack();

                return response()->json([
                    'status'  => false,
                    'message' => 'This product has stock transactions and cannot be deleted.'
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

        ProductVariant::where('product_id', $productId)
            ->where('deleted', 'No')
            ->whereNotIn('id', $keptVariantIds)
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
    | Product Name + Category + Brand
    | e.g. SAMSUNG-GALAXY-A08-MOBILE-SAMSUNG
    |
    */

    private function generateProductSku(
        $productName,
        $categoryName = '',
        $brandName = '',
        $ignoreProductId = null
    ) {

        $parts = array_filter([$productName, $categoryName, $brandName]);

        $base = Str::upper(Str::slug(implode('-', $parts), '-'));

        if ($base === '') {
            $base = 'PRODUCT';
        }

        $base = substr($base, 0, 80);

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

        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

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
        return response()->json([
            'status'  => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
