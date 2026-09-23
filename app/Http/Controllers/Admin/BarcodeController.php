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
use Milon\Barcode\DNS1D;

class BarcodeController extends Controller
{
    public function index()
    {
        // Full product list for the Select2 dropdown (id + name is enough here —
        // full details incl. variants are fetched on selection via `variants()`).
        $products = Product::where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        return view('admin.inventory.barcode.index', compact('products'));
    }

    /**
     * Live search: products by name / sku / barcode. Each result carries its
     * variants (if any) so the front-end can auto-expand them into the cart.
     */
    public function search(Request $request)
    {
        $term = trim($request->get('term', ''));

        $products = Product::query()
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'LIKE', "%{$term}%")
                        ->orWhere('sku', 'LIKE', "%{$term}%")
                        ->orWhere('barcode', 'LIKE', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'slug', 'sku', 'barcode', 'sale_price', 'image']);

        $result = $products->map(fn($product) => $this->formatProduct($product));

        return response()->json(['data' => $result]);
    }

    /**
     * Fetch a single product with its variants (used if the UI needs a fresh
     * lookup instead of relying on the search payload).
     */
    public function variants($productId)
    {
        $product = Product::where('id', $productId)
            ->firstOrFail(['id', 'name', 'sku', 'barcode', 'sale_price', 'image']);

        return response()->json(['data' => $this->formatProduct($product)]);
    }

    private function formatProduct(Product $product)
    {

        $variantsTable  = (new ProductVariant())->getTable();
        $attributeTable = (new ProductAttribute())->getTable();

        $variants = DB::table("product_variants as pv")
            ->leftJoin("attributes as pa", 'pv.attribute_id', '=', 'pa.id')
            ->leftJoin("attribute_types as patype", 'pv.attribute_type_id', '=', 'patype.id')
            ->where('pv.product_id', $product->id)
            ->where('pv.deleted', 'No')
            ->where('pv.status', 'Active')
            ->select([
                'pv.id',
                'pv.product_id',
                'pv.barcode',
                'pv.sale_price',
                'patype.name as attribute_type_name', // TODO: confirm product_attributes' name column
                'pa.name as attribute_name',
            ])
            ->get();

        return [
            'id'           => $product->id,
            'name'         => $product->name,
            'sku'          => $product->sku,
            'barcode'      => $product->barcode,
            'sale_price'   => $product->sale_price,
            'image'        => $product->image,
            'has_variants' => $variants->count() > 0,
            'variants'     => $variants->map(function ($v) {
                return [
                    'id'         => $v->id,
                    'product_id' => $v->product_id,
                    'barcode'    => $v->barcode,
                    'sale_price' => $v->sale_price,
                    'label'      => trim(($v->attribute_type_name ?? '') . ': ' . ($v->attribute_name ?? ''), ': '),
                ];
            })->values(),
        ];
    }




    public function print(Request $request)
    {
        $request->validate([
            'items'          => 'required|array|min:1',
            'items.*.type'   => 'required|in:product,variant',
            'items.*.id'     => 'required|integer',
            'items.*.qty'    => 'required|integer|min:1|max:500',
        ]);

        $lines = [];

        foreach ($request->items as $item) {
            if ($item['type'] === 'product') {
                $product = Product::find($item['id']);
                if (!$product) {
                    continue;
                }

                $lines[] = [
                    'name'    => $product->name,
                    'barcode' => $product->barcode ?: $product->sku,
                    'price'   => $product->sale_price,
                    'qty'     => (int) $item['qty'],
                ];
            } else {
                // Same no-Eloquent-relation approach as formatProduct(): join
                // manually rather than relying on ->with(['product','attribute','attributeType']).
                $variantsTable  = (new ProductVariant())->getTable();
                $productsTable  = (new Product())->getTable();
                $attributeTable = (new ProductAttribute())->getTable();

                $variant = DB::table("product_variants as pv")
                    ->leftJoin("attributes as pa", 'pv.attribute_id', '=', 'pa.id')
                    ->leftJoin("attribute_types as patype", 'pv.attribute_type_id', '=', 'patype.id')
                    ->leftJoin("products as p", 'pv.product_id', '=', 'p.id')
                    ->where('pv.id', $item['id'])
                    ->select([
                        'pv.id',
                        'pv.barcode',
                        'pv.sale_price',
                        'p.name as product_name',
                        'p.sku as product_sku',
                        'patype.name as attribute_type_name',
                        'pa.name as attribute_name',
                    ])
                    ->first();

                if (!$variant) {
                    continue;
                }

                $label = trim(($variant->attribute_type_name ?? '') . ': ' . ($variant->attribute_name ?? ''), ': ');

                $lines[] = [
                    'name'    => $label !== '' ? "{$variant->product_name} ({$label})" : $variant->product_name,
                    'barcode' => $variant->barcode ?: $variant->product_sku,
                    'price'   => $variant->sale_price,
                    'qty'     => (int) $item['qty'],
                ];
            }
        }

        $settings = [
            'barcode_type' => $request->get('barcode_type', 'C128'),
            'show_price'   => (bool) $request->get('show_price', true),
            'show_name'    => (bool) $request->get('show_name', true),
            'label_width'  => (float) $request->get('label_width', 40),  // mm
            'label_height' => (float) $request->get('label_height', 30), // mm
            'columns'      => (int) $request->get('columns', 3),
        ];

        // Render each unique line's barcode ONCE here (server-side, as SVG) rather
        // than per repeated label, and rather than in the Blade view — that way a
        // single bad code (e.g. a non-numeric value with EAN13/UPC) can't throw and
        // break the whole print page; it just falls back to an error note for that label.
        $barcodeGenerator = new DNS1D();

        foreach ($lines as &$line) {
            try {
                $svg = $barcodeGenerator->getBarcodeSVG($line['barcode'], $settings['barcode_type'], 1.4, 35);

                // Strip the human-readable digits the library draws under the bars —
                // done via regex rather than a "show text" param so this works
                // regardless of which milon/barcode version/signature is installed.
                $svg = preg_replace('/<text\b[^>]*>.*?<\/text>/is', '', $svg);

                $line['barcode_svg']   = $svg;
                $line['barcode_error'] = null;
            } catch (\Throwable $e) {
                $line['barcode_svg']   = null;
                $line['barcode_error'] = $e->getMessage();
            }
        }
        unset($line);
        //return $lines;
        return view('admin.inventory.barcode.print', compact('lines', 'settings'));
    }
}
