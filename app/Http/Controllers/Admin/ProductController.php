<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Product;
use App\Models\Admin\ProductAttribute;
use App\Models\Admin\ProductSpec;
use App\Models\Admin\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index()
    {
        $brands = DB::table('brands')
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $categories = DB::table('categories')
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $units = DB::table('units')
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $attributeTypes = DB::table('attribute_types')
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        return view('admin.inventory.products.index', compact(
            'brands',
            'categories',
            'units',
            'attributeTypes'
        ));
    }


    public function list(Request $request)
    {
        $products = DB::table('products')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->where('products.deleted', 'No')
            ->select(
                'products.*',
                'brands.name as brand_name',
                'categories.name as category_name',
                'units.name as unit_name'
            )
            ->orderByDesc('products.id')
            ->get();

        $output = [];

        foreach ($products as $product) {

            $currentStock = DB::table('stocks')
                ->where('product_id', $product->id)
                ->where('deleted', 'No')
                ->sum(DB::raw('in_qty - out_qty'));


            /*
         * ATTRIBUTES
         */

            $attributes = DB::table('product_attributes')
                ->join(
                    'attribute_types',
                    'attribute_types.id',
                    '=',
                    'product_attributes.attribute_type_id'
                )
                ->join(
                    'attributes',
                    'attributes.id',
                    '=',
                    'product_attributes.attribute_id'
                )
                ->where(
                    'product_attributes.product_id',
                    $product->id
                )
                ->where(
                    'product_attributes.deleted',
                    'No'
                )
                ->select(
                    'attribute_types.name as type_name',
                    'attributes.name as attribute_name'
                )
                ->get();


            $attributesHtml = '';

            foreach ($attributes as $attribute) {

                $attributesHtml .=
                    '<b>' .
                    e($attribute->type_name) .
                    ':</b> ' .
                    e($attribute->attribute_name) .
                    '<br>';
            }


            /*
         * SPECIFICATIONS
         */

            $specs = DB::table('product_specs')
                ->where(
                    'product_id',
                    $product->id
                )
                ->where(
                    'deleted',
                    'No'
                )
                ->get();


            $specificationsHtml = '';

            foreach ($specs as $spec) {

                $specificationsHtml .=
                    '<b>' .
                    e($spec->spec_name) .
                    ':</b> ' .
                    e($spec->spec_value) .
                    '<br>';
            }


            /*
         * OPENING STOCK
         */

            $openingStock = DB::table('stocks')
                ->where(
                    'product_id',
                    $product->id
                )
                ->where(
                    'reference_type',
                    'Opening Stock'
                )
                ->where(
                    'deleted',
                    'No'
                )
                ->sum('in_qty');


            $product->current_stock =
                $currentStock;

            $product->opening_stock =
                $openingStock;

            $product->attributes_html =
                $attributesHtml;

            $product->specifications_html =
                $specificationsHtml;


            $output[] = $product;
        }


        return response()->json([
            'status' => true,
            'data' => $output
        ]);
    }


    public function data()
    {
        $products = DB::table('products')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->where('products.deleted', 'No')
            ->select(
                'products.*',
                'brands.name as brand_name',
                'categories.name as category_name',
                'units.name as unit_name'
            )
            ->orderByDesc('products.id')
            ->get();

        return response()->json($products);
    }


    public function attributes($attributeTypeId)
    {
        $attributes = DB::table('attributes')
            ->where('attribute_type_id', $attributeTypeId)
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $attributes
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'brand_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
            'unit_id' => 'nullable|integer',
            'sku' => 'nullable|string|max:150|unique:products,sku',
            'barcode' => 'nullable|string|max:150|unique:products,barcode',
            'stock_alert' => 'nullable|numeric|min:0',
            'opening_stock' => 'nullable|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'old_sale_price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        DB::beginTransaction();

        try {

            $product = new Product();

            $product->name = $request->name;

            $product->slug = $this->generateSlug($request->name);

            $product->brand_id = $request->brand_id;
            $product->category_id = $request->category_id;
            $product->unit_id = $request->unit_id;

            $product->sku = $request->sku;
            $product->barcode = $request->barcode;

            $product->stock_alert = $request->stock_alert ?? 0;

            $product->purchase_price = $request->purchase_price ?? 0;
            $product->sale_price = $request->sale_price ?? 0;
            $product->old_sale_price = $request->old_sale_price ?? 0;
            $product->discount = $request->discount ?? 0;

            $product->description = $request->description;

            $product->deleted = 'No';

            $product->created_date = now();
            $product->created_by = auth()->id();

            $product->status = 'Active';

            $product->save();


            /*
             * IMAGE
             */
            if ($request->hasFile('image')) {

                $image = $request->file('image');

                $imageName = time() . '_' . Str::random(10)
                    . '.' . $image->getClientOriginalExtension();

                $image->move(
                    public_path('uploads/products'),
                    $imageName
                );

                $product->image = $imageName;

                $product->save();
            }


            /*
             * ATTRIBUTES
             */
            if ($request->has('attributes')) {

                foreach ($request->attributes as $attribute) {

                    if (
                        !empty($attribute['attribute_type_id']) &&
                        !empty($attribute['attribute_id'])
                    ) {

                        $productAttribute = new ProductAttribute();

                        $productAttribute->product_id = $product->id;
                        $productAttribute->attribute_type_id =
                            $attribute['attribute_type_id'];

                        $productAttribute->attribute_id =
                            $attribute['attribute_id'];

                        $productAttribute->deleted = 'No';

                        $productAttribute->created_date = now();
                        $productAttribute->created_by = auth()->id();

                        $productAttribute->status = 'Active';

                        $productAttribute->save();
                    }
                }
            }


            /*
             * SPECS
             */
            if ($request->has('specs')) {

                foreach ($request->specs as $spec) {

                    if (!empty($spec['spec_name'])) {

                        $productSpec = new ProductSpec();

                        $productSpec->product_id = $product->id;

                        $productSpec->spec_name =
                            $spec['spec_name'];

                        $productSpec->spec_value =
                            $spec['spec_value'] ?? null;

                        $productSpec->deleted = 'No';

                        $productSpec->created_date = now();
                        $productSpec->created_by = auth()->id();

                        $productSpec->status = 'Active';

                        $productSpec->save();
                    }
                }
            }


            /*
             * OPENING STOCK
             */
            $openingStock = $request->opening_stock ?? 0;

            if ($openingStock > 0) {

                $stock = new Stock();

                $stock->product_id = $product->id;

                $stock->in_qty = $openingStock;
                $stock->out_qty = 0;

                $stock->purchase_id = null;
                $stock->sale_id = null;

                $stock->damage_id = null;

                $stock->purchase_return_id = null;
                $stock->sale_return_id = null;

                $stock->adjustment_id = null;

                $stock->reference_type = 'Opening Stock';
                $stock->reference_id = $product->id;

                $stock->date = now();

                $stock->remarks = 'Product opening stock';

                $stock->deleted = 'No';

                $stock->created_date = now();
                $stock->created_by = auth()->id();

                $stock->status = 'Active';

                $stock->save();
            }


            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Product created successfully.'
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function edit($id)
    {
        $product = DB::table('products')
            ->where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$product) {

            return response()->json([
                'status' => false,
                'message' => 'Product not found.'
            ], 404);
        }


        $attributes = DB::table('product_attributes')
            ->join(
                'attribute_types',
                'attribute_types.id',
                '=',
                'product_attributes.attribute_type_id'
            )
            ->join(
                'attributes',
                'attributes.id',
                '=',
                'product_attributes.attribute_id'
            )
            ->where('product_attributes.product_id', $id)
            ->where('product_attributes.deleted', 'No')
            ->select(
                'product_attributes.attribute_type_id',
                'product_attributes.attribute_id',
                'attribute_types.name as attribute_type_name',
                'attributes.name as attribute_name'
            )
            ->get();


        $specs = DB::table('product_specs')
            ->where('product_id', $id)
            ->where('deleted', 'No')
            ->get();


        $stock = DB::table('stocks')
            ->where('product_id', $id)
            ->where('deleted', 'No')
            ->sum(DB::raw('in_qty - out_qty'));


        return response()->json([
            'status' => true,
            'product' => $product,
            'attributes' => $attributes,
            'specs' => $specs,
            'current_stock' => $stock
        ]);
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'brand_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
            'unit_id' => 'nullable|integer',

            'sku' => 'nullable|string|max:150|unique:products,sku,' . $id,
            'barcode' => 'nullable|string|max:150|unique:products,barcode,' . $id,

            'stock_alert' => 'nullable|numeric|min:0',

            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'old_sale_price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',

            'description' => 'nullable|string',

            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        DB::beginTransaction();

        try {

            $product = Product::where('id', $id)
                ->where('deleted', 'No')
                ->first();

            if (!$product) {

                return response()->json([
                    'status' => false,
                    'message' => 'Product not found.'
                ], 404);
            }


            $product->name = $request->name;

            /*
             * Keep same slug if name unchanged.
             * Generate new slug if name changed.
             */
            if ($product->name != $request->name) {
                $product->slug = $this->generateSlug(
                    $request->name,
                    $id
                );
            }

            $product->brand_id = $request->brand_id;
            $product->category_id = $request->category_id;
            $product->unit_id = $request->unit_id;

            $product->sku = $request->sku;
            $product->barcode = $request->barcode;

            $product->stock_alert = $request->stock_alert ?? 0;

            $product->purchase_price = $request->purchase_price ?? 0;
            $product->sale_price = $request->sale_price ?? 0;
            $product->old_sale_price = $request->old_sale_price ?? 0;
            $product->discount = $request->discount ?? 0;

            $product->description = $request->description;

            $product->updated_by = auth()->id();
            $product->updated_date = now();

            $product->save();


            /*
             * IMAGE
             */
            if ($request->hasFile('image')) {

                if (
                    $product->image &&
                    file_exists(
                        public_path(
                            'uploads/products/' . $product->image
                        )
                    )
                ) {

                    unlink(
                        public_path(
                            'uploads/products/' . $product->image
                        )
                    );
                }


                $image = $request->file('image');

                $imageName = time() . '_' . Str::random(10)
                    . '.' . $image->getClientOriginalExtension();

                $image->move(
                    public_path('uploads/products'),
                    $imageName
                );

                $product->image = $imageName;

                $product->save();
            }


            /*
             * REPLACE ATTRIBUTES
             */
            ProductAttribute::where('product_id', $id)
                ->update([
                    'deleted' => 'Yes',
                    'deleted_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                    'updated_date' => now()
                ]);


            if ($request->has('attributes')) {

                foreach ($request->attributes as $attribute) {

                    if (
                        !empty($attribute['attribute_type_id']) &&
                        !empty($attribute['attribute_id'])
                    ) {

                        $productAttribute = new ProductAttribute();

                        $productAttribute->product_id = $id;

                        $productAttribute->attribute_type_id =
                            $attribute['attribute_type_id'];

                        $productAttribute->attribute_id =
                            $attribute['attribute_id'];

                        $productAttribute->deleted = 'No';

                        $productAttribute->created_date = now();
                        $productAttribute->created_by = auth()->id();

                        $productAttribute->status = 'Active';

                        $productAttribute->save();
                    }
                }
            }


            /*
             * REPLACE SPECS
             */
            ProductSpec::where('product_id', $id)
                ->update([
                    'deleted' => 'Yes',
                    'deleted_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                    'updated_date' => now()
                ]);


            if ($request->has('specs')) {

                foreach ($request->specs as $spec) {

                    if (!empty($spec['spec_name'])) {

                        $productSpec = new ProductSpec();

                        $productSpec->product_id = $id;

                        $productSpec->spec_name =
                            $spec['spec_name'];

                        $productSpec->spec_value =
                            $spec['spec_value'] ?? null;

                        $productSpec->deleted = 'No';

                        $productSpec->created_date = now();
                        $productSpec->created_by = auth()->id();

                        $productSpec->status = 'Active';

                        $productSpec->save();
                    }
                }
            }


            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Product updated successfully.'
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function delete($id)
    {
        $product = Product::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$product) {

            return response()->json([
                'status' => false,
                'message' => 'Product not found.'
            ]);
        }


        $product->deleted = 'Yes';
        $product->deleted_by = auth()->id();
        $product->updated_by = auth()->id();
        $product->updated_date = now();

        $product->save();


        return response()->json([
            'status' => true,
            'message' => 'Product deleted successfully.'
        ]);
    }


    public function status($id)
    {
        $product = Product::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$product) {

            return response()->json([
                'status' => false,
                'message' => 'Product not found.'
            ]);
        }


        $product->status =
            $product->status == 'Active'
            ? 'Inactive'
            : 'Active';

        $product->updated_by = auth()->id();
        $product->updated_date = now();

        $product->save();


        return response()->json([
            'status' => true,
            'message' => 'Product status updated successfully.'
        ]);
    }


    private function generateSlug($name, $ignoreId = null)
    {
        $slug = Str::slug($name);

        $originalSlug = $slug;

        $counter = 1;

        while (true) {

            $query = DB::table('products')
                ->where('slug', $slug);

            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }

            if (!$query->exists()) {
                break;
            }

            $slug = $originalSlug . '-' . $counter;

            $counter++;
        }

        return $slug;
    }
}
