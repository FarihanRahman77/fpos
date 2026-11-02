<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Purchase;
use App\Models\Admin\PurchaseItem;
use App\Models\Admin\PurchaseReturn;
use App\Models\Admin\PurchaseReturnItem;
use App\Models\Admin\Product;
use App\Models\Admin\ProductVariant;
use App\Models\Admin\Stock;
use App\Models\Admin\Party;
use App\Models\Admin\Payment;
use App\Models\Admin\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use PDF;
use Exception;
use App\Traits\PartyDueTrait;
use App\Traits\PaymentTrait;
use App\Traits\SequentialCodeTrait;

class PurchaseController extends Controller
{
    use PartyDueTrait, PaymentTrait, SequentialCodeTrait;

    /** A purchase of the logged-in branch (null when missing, deleted or another branch's). */
    private function branchPurchase($id, bool $lock = false): ?Purchase
    {
        return BranchController::scope(Purchase::query(), 'branch_id')
            ->where('id', $id)
            ->where('deleted', 'No')
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->first();
    }

    /** Messages we throw ourselves go to the user; anything else (SQL, PHP errors) is logged and replaced. */
    private function failResponse(\Throwable $e, string $fallback = 'Something went wrong. Please try again.')
    {
        if (get_class($e) === Exception::class) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        Log::error('Purchase error: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

        return response()->json(['status' => false, 'message' => $fallback], 500);
    }

    /** Products must exist and be active; a product with variants needs one of its own variants on the line. */
    private function checkItems(array $items): void
    {
        foreach ($items as $item) {
            $product = Product::where('id', $item['product_id'] ?? 0)->where('deleted', 'No')->first();
            if (!$product) {
                throw new Exception('A product on this purchase no longer exists.');
            }

            $variantIds = ProductVariant::where('product_id', $product->id)->where('deleted', 'No')->pluck('id')->map(fn ($v) => (int) $v)->all();
            $variantId = !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;

            if ($variantId && !in_array($variantId, $variantIds, true)) {
                throw new Exception('The variant chosen for "' . $product->name . '" does not belong to it.');
            }
            if (!$variantId && $variantIds) {
                throw new Exception('Choose a variant for "' . $product->name . '".');
            }
        }
    }

    /**
     * Refuse a change that would leave this branch with negative stock (e.g. the purchased goods were already sold).
     * $keys: [[product_id, variant_id|null, batch_id|null], ...] checked after the change is written.
     */
    private function assertNoNegativeStock(array $keys): void
    {
        $branch = (int) BranchController::activeId();
        $seen = [];
        // Each batch, and the product / variant as a whole.
        $all = array_merge($keys, array_map(fn ($k) => [$k[0], $k[1], null], $keys));
        foreach ($all as [$pid, $vid, $bid]) {
            $k = $pid . ':' . ($vid ?: 0) . ':' . ($bid ?: 0);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;

            $left = StockController::onHand((int) $pid, $vid ? (int) $vid : null, $branch, $bid ? (int) $bid : null);
            if ($left < -0.0005) {
                $name = DB::table('products')->where('id', $pid)->value('name');
                throw new Exception('"' . $name . '" from this purchase has already been sold or moved out (stock would become '
                    . rtrim(rtrim(number_format($left, 3), '0'), '.') . '). Return or adjust that stock first.');
            }
        }
    }

    public function index()
    {
        return view('admin.inventory.purchase.index');
    }


    /*
    |--------------------------------------------------------------------------
    | Create Purchase
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        $suppliers = Party::where('deleted', 'No')
            ->where('status', 'Active')
            ->where('type','Supplier')
            ->orderBy('name')
            ->get();
        $products = Product::where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('name',)
            ->get();

        $coa=ChartOfAccount::where('slug','liquid-asset')->where('deleted','No')->first();
        $childCoas=ChartOfAccount::where('deleted','No')->where('parent_id',$coa->id)->get();
        $purchaseVat = CustomerProgramController::on('vat_purchase_enabled');

        return view('admin.inventory.purchase.create', compact('suppliers', 'products', 'childCoas', 'purchaseVat'));
    }


    /*
    |--------------------------------------------------------------------------
    | Purchase List AJAX
    |--------------------------------------------------------------------------
    */
    public function list(Request $request)
    {
        try {

            $purchases = BranchController::scope(DB::table('purchases'), 'purchases.branch_id')
                ->leftJoin('parties', 'parties.id', '=', 'purchases.supplier_id')
                ->leftJoin('chart_of_accounts as pm', 'pm.id', '=', 'purchases.payment_method')
                ->leftJoin('chart_of_accounts as pma', 'pma.id', '=', 'purchases.payment_method_account')
                ->select(
                    'purchases.*',
                    'parties.name as supplier_name',
                    'pm.account_name as pmaccount_name',
                    'pma.account_name as pmaaccount_name'
                )
                ->where('purchases.deleted', 'No')
                ->orderByDesc('purchases.id')
                ->get();

            $html = '';

            foreach ($purchases as $index => $row) {

                $statusBadge = $row->status === 'Active'
                    ? '<i class="fas fa-check-circle text-success" style="font-size:18px;"></i>'
                    : '<i class="fas fa-times-circle text-danger" style="font-size:18px;"></i>';

                $html .= '
                <tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . e($row->invoice_no) . '</td>
                    <td>' . e($row->purchase_date ?? '') . '</td>
                    <td>' . e($row->supplier_name ?? '') . '</td>
                    <td>' . number_format((float) $row->subtotal, 2, '.', '') . '</td>
                    <td>' . number_format((float) $row->discount_amount, 2, '.', '') . '</td>
                    <td>' . number_format((float) $row->grand_total, 2, '.', '') . '</td>
                    <td>' . number_format((float) $row->paid_amount, 2, '.', '') . '</td>
                    <td>' . number_format((float) $row->due_amount, 2, '.', '') . '</td>
                    <td>' . e($row->pmaccount_name ?? '') . ' - '.e($row->pmaaccount_name ?? '').'</td>
                    <td>' . $statusBadge . '</td>
                    <td class="text-center">' . action_dropdown([
                        ['label' => 'Invoice', 'icon' => 'fas fa-file-pdf', 'href' => url('/admin/purchases/invoice-pdf/' . $row->id), 'target' => '_blank'],
                        ['label' => 'Purchase Return', 'icon' => 'fas fa-undo', 'href' => url('/admin/purchases/return/' . $row->id)],
                        ['divider' => true],
                        ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger deleteBtn', 'attrs' => ['data-id' => $row->id]],
                    ]) . '</td>
                </tr>';
            }

            return response()->json([
                'status' => true,
                'html'   => $html
            ]);
        } catch (Exception $e) {

            return $this->failResponse($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Supplier Current Due
    |--------------------------------------------------------------------------
    */
    public function supplierDue($supplierId)
    {
        try {

            $due = $this->partyDue($supplierId);

            return response()->json([
                'status' => true,
                'due' => number_format((float)$due, 2, '.', '')
            ]);
        } catch (Exception $e) {

            return $this->failResponse($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Product Search
    |--------------------------------------------------------------------------
    */
    public function productSearch(Request $request)
    {
        try {

            $search = trim($request->input('search', ''));

            $products = Product::where('deleted', 'No')
                ->where('status', 'Active')
                ->when($search != '', function ($query) use ($search) {

                    $query->where(function ($q) use ($search) {

                        $q->where('name', 'like', '%' . $search . '%')
                            ->orWhere('sku', 'like', '%' . $search . '%')
                            ->orWhere('barcode', 'like', '%' . $search . '%');
                    });
                })
                ->orderBy('name')
                ->limit(30)
                ->get();

            return response()->json([
                'status' => true,
                'data' => $products
            ]);
        } catch (Exception $e) {

            return $this->failResponse($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Product Variants
    |--------------------------------------------------------------------------
    */
    public function variants($productId)
    {
        try {

            $product = Product::where('id', $productId)
                ->where('deleted', 'No')
                ->where('status', 'Active')
                ->first();

            if (!$product) {

                return response()->json([
                    'status' => false,
                    'message' => 'Product not found.'
                ], 404);
            }


            $variants = ProductVariant::leftjoin('attributes', 'attributes.id', '=', 'product_variants.attribute_id')
                ->select('product_variants.*', 'attributes.name')
                ->where('product_variants.product_id', $productId)
                ->where('product_variants.deleted', 'No')
                ->where('product_variants.status', 'Active')
                ->get();


            $data = [
                'product' => $product,
                'variants' => $variants
            ];


            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {

            return $this->failResponse($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Store Purchase
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        //return $request;
        $validator = Validator::make($request->all(), [

            'supplier_id' => 'required|integer',
            'purchase_date' => 'required|date',

            'discount_type' => 'required|in:Percentage,Amount',
            'discount_value' => 'nullable|numeric|min:0',

            'paid_amount' => 'nullable|numeric|min:0',
            'vat_percent' => 'nullable|numeric|min:0|max:100',

            // A payment account is needed only when something is paid now (a credit purchase has none).
            'payment_method' => [Rule::requiredIf(fn () => (float) $request->input('paid_amount') > 0), 'nullable', 'integer', 'exists:chart_of_accounts,id'],
            'payment_method_account' => [Rule::requiredIf(fn () => (float) $request->input('paid_amount') > 0), 'nullable', 'integer', 'exists:chart_of_accounts,id'],

            'items' => 'required|array|min:1',

            'items.*.product_id' => 'required|integer',
            'items.*.product_variant_id' => 'nullable|integer',

            'items.*.qty' => 'required|numeric|min:0.0001',

            'items.*.purchase_price' => 'required|numeric|min:0',

            'items.*.discount_type' => 'required|in:Percentage,Amount',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.batch_no' => 'nullable|string|max:60',
            'items.*.expiry_date' => 'nullable|date',

        ], [
            'payment_method.required' => 'Choose the payment method for the paid amount.',
            'payment_method_account.required' => 'Choose the payment account for the paid amount.',
            'items.required' => 'Add at least one product.',
            'items.*.qty.min' => 'Quantity must be more than zero.',
        ]);


        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }


        DB::beginTransaction();

        try {

            $this->checkItems($request->input('items', []));

            $supplier = Party::where('id', $request->supplier_id)
                ->where('deleted', 'No')
                ->where('status', 'Active')
                ->where('type', 'Supplier')
                ->first();

            if (!$supplier) {

                throw new Exception('Invalid supplier.');
            }


            $items = $request->input('items', []);


            $subtotal = 0;


            /*
            |--------------------------------------------------------------------------
            | Calculate Items
            |--------------------------------------------------------------------------
            */
            foreach ($items as $item) {

                $qty = (float)$item['qty'];
                $purchasePrice = (float)$item['purchase_price'];

                $itemSubtotal = $qty * $purchasePrice;

                $discountType = $item['discount_type'] ?? 'Amount';
                $discountValue = (float)($item['discount_value'] ?? 0);

                $itemDiscount = 0;


                if ($discountType == 'Percentage') {

                    $itemDiscount = ($itemSubtotal * $discountValue) / 100;
                } else {

                    $itemDiscount = $discountValue;
                }


                if ($itemDiscount > $itemSubtotal) {

                    $itemDiscount = $itemSubtotal;
                }


                $itemTotal = $itemSubtotal - $itemDiscount;

                $subtotal += $itemTotal;
            }


            /*
            |--------------------------------------------------------------------------
            | Header Discount
            |--------------------------------------------------------------------------
            */
            $discountType = $request->discount_type ?? 'Amount';

            $discountValue = (float)($request->discount_value ?? 0);

            $discountAmount = 0;


            if ($discountType == 'Percentage') {

                $discountAmount = ($subtotal * $discountValue) / 100;
            } else {

                $discountAmount = $discountValue;
            }


            if ($discountAmount > $subtotal) {

                $discountAmount = $subtotal;
            }


            $grandTotal = $subtotal - $discountAmount;

            // Input VAT on the purchase after its discount.
            $vatPercent = min(100, max(0, (float) $request->input('vat_percent', 0)));
            $vatAmount = round(($subtotal - $discountAmount) * $vatPercent / 100, 2);
            $grandTotal += $vatAmount;


            $paidAmount = (float)($request->paid_amount ?? 0);


            if ($paidAmount > $grandTotal) {

                $paidAmount = $grandTotal;
            }


            $dueAmount = $grandTotal - $paidAmount;


            /*
            |--------------------------------------------------------------------------
            | Invoice Number
            |--------------------------------------------------------------------------
            */
            $invoiceNo = $this->nextSequentialCode(Purchase::class, 'invoice_no', 'PUR');


            /*
            |--------------------------------------------------------------------------
            | Purchase
            |--------------------------------------------------------------------------
            */
            $purchase = new Purchase();

            $purchase->invoice_no = $invoiceNo;
            $purchase->supplier_id = $request->supplier_id;
            $purchase->purchase_date = $request->purchase_date;

            $purchase->subtotal = round($subtotal, 2);

            $purchase->discount_type = $discountType;
            $purchase->discount_value = round($discountValue, 2);
            $purchase->discount_amount = round($discountAmount, 2);

            $purchase->vat_percent = $vatPercent;
            $purchase->vat_amount = $vatAmount;
            $purchase->grand_total = round($grandTotal, 2);

            $purchase->paid_amount = round($paidAmount, 2);
            $purchase->due_amount = round($dueAmount, 2);

            $purchase->payment_method = $request->payment_method;
            $purchase->payment_method_account = $request->payment_method_account;
            $purchase->remarks = $request->remarks;

            $purchase->deleted = 'No';
            $purchase->status = 'Active';

            $purchase->created_by = auth()->id();
            $purchase->created_date = Carbon::now();

            $purchase->created_at = Carbon::now();
            $purchase->updated_at = Carbon::now();

            $purchase->save();


            /*
            |--------------------------------------------------------------------------
            | Purchase Items + Stock
            |--------------------------------------------------------------------------
            */
            foreach ($items as $item) {

                $qty = (float)$item['qty'];
                $purchasePrice = (float)$item['purchase_price'];

                $itemSubtotal = $qty * $purchasePrice;

                $itemDiscountType = $item['discount_type'] ?? 'Amount';
                $itemDiscountValue = (float)($item['discount_value'] ?? 0);

                $itemDiscount = 0;


                if ($itemDiscountType == 'Percentage') {

                    $itemDiscount =
                        ($itemSubtotal * $itemDiscountValue) / 100;
                } else {

                    $itemDiscount = $itemDiscountValue;
                }


                if ($itemDiscount > $itemSubtotal) {

                    $itemDiscount = $itemSubtotal;
                }


                $itemTotal = $itemSubtotal - $itemDiscount;


                $purchaseItem = new PurchaseItem();

                $purchaseItem->purchase_id = $purchase->id;
                $purchaseItem->product_id = $item['product_id'];
                $purchaseItem->product_variant_id =
                    !empty($item['product_variant_id'])
                    ? $item['product_variant_id']
                    : null;

                $purchaseItem->qty = $qty;
                $purchaseItem->purchase_price = $purchasePrice;

                $purchaseItem->discount_type = $itemDiscountType;
                $purchaseItem->discount_value = $itemDiscountValue;
                $purchaseItem->discount_amount = $itemDiscount;

                $purchaseItem->subtotal = $itemSubtotal;
                $purchaseItem->total = $itemTotal;

                $purchaseItem->deleted = 'No';
                $purchaseItem->status = 'Active';

                $purchaseItem->created_by = auth()->id();
                $purchaseItem->created_date = Carbon::now();

                $purchaseItem->created_at = Carbon::now();
                $purchaseItem->updated_at = Carbon::now();

                $purchaseItem->save();

                // Batch-tracked products: the batch (blank number = one per purchase line) goes on the item and the stock row.
                $batchId = null;
                if (DB::table('products')->where('id', $item['product_id'])->value('track_batch') === 'Yes') {
                    $batchId = StockController::batchFor(
                        (int) $item['product_id'],
                        !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null,
                        trim((string) ($item['batch_no'] ?? '')) ?: ($purchase->invoice_no . '-' . $purchaseItem->id),
                        $item['expiry_date'] ?? null,
                        null,
                        (float) $purchasePrice,
                        (int) $purchase->id
                    );
                    $purchaseItem->batch_id = $batchId;
                    $purchaseItem->save();
                }


                /*
                |--------------------------------------------------------------------------
                | Stock
                |--------------------------------------------------------------------------
                */
                $stock = new Stock();

                $stock->product_id = $item['product_id'];

                $stock->product_variant_id =
                    !empty($item['product_variant_id'])
                    ? $item['product_variant_id']
                    : null;

                $stock->batch_id = $batchId;

                $stock->in_qty = $qty;
                $stock->out_qty = 0;

                $stock->purchase_id = $purchase->id;

                $stock->date = $request->purchase_date;

                $stock->reference_type = 'Purchase';
                $stock->reference_id = $purchase->id;

                $stock->created_at = Carbon::now();
                $stock->updated_at = Carbon::now();

                $stock->save();
            }

            if ($grandTotal > 0) {
                $this->storeDebitPayment([
                    'party_id'       => $request->supplier_id,
                    'party_type'     => 'Supplier',
                    'payment_type'   => 'Purchase',
                    'datetime'       => $request->purchase_date,
                    'debit'          => $grandTotal,
                    'purchase_id'    => $purchase->id,
                    'payment_method' => '',
                    'payment_method_account' => '',
                ]);
            }

            if ($paidAmount > 0) {
                $this->storeCreditPayment([
                    'party_id'       => $request->supplier_id,
                    'party_type'     => 'Supplier',
                    'payment_type'   => 'Purchase Payment',
                    'datetime'       => $request->purchase_date,
                    'credit'         => $paidAmount,
                    'purchase_id'    => $purchase->id,
                    'payment_method' => $request->payment_method,
                    'payment_method_account' => $request->payment_method_account,
                    
                ]);
            }

            DB::commit();


            return response()->json([
                'status' => true,
                'message' => 'Purchase created successfully.',
                'redirect' => route('purchases.index')
            ]);
        } catch (Exception $e) {

            DB::rollBack();


            return $this->failResponse($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Edit
    |--------------------------------------------------------------------------
    */
    public function edit($id)
    {
        try {

            $purchase = $this->branchPurchase($id);

            if (!$purchase) {

                return response()->json([
                    'status' => false,
                    'message' => 'Purchase not found.'
                ], 404);
            }


            $items = DB::table('purchase_items')
                ->leftJoin(
                    'products',
                    'products.id',
                    '=',
                    'purchase_items.product_id'
                )
                ->leftJoin(
                    'product_variants',
                    'product_variants.id',
                    '=',
                    'purchase_items.product_variant_id'
                )
                ->select(
                    'purchase_items.*',
                    'products.name as product_name',
                    'products.sku',
                    'products.barcode',
                    'product_variants.barcode as variant_barcode'
                )
                ->where('purchase_items.purchase_id', $id)
                ->where('purchase_items.deleted', 'No')
                ->get();


            return response()->json([
                'status' => true,
                'data' => [
                    'purchase' => $purchase,
                    'items' => $items
                ]
            ]);
        } catch (Exception $e) {

            return $this->failResponse($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update Purchase
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [

            'supplier_id' => 'required|integer',
            'purchase_date' => 'required|date',

            'discount_type' => 'required|in:Percentage,Amount',
            'discount_value' => 'nullable|numeric|min:0',

            'paid_amount' => 'nullable|numeric|min:0',
            'vat_percent' => 'nullable|numeric|min:0|max:100',

            'payment_method' => [Rule::requiredIf(fn () => (float) $request->input('paid_amount') > 0), 'nullable', 'integer', 'exists:chart_of_accounts,id'],
            'payment_method_account' => [Rule::requiredIf(fn () => (float) $request->input('paid_amount') > 0), 'nullable', 'integer', 'exists:chart_of_accounts,id'],

            'items' => 'required|array|min:1',

            'items.*.product_id' => 'required|integer',
            'items.*.product_variant_id' => 'nullable|integer',

            'items.*.qty' => 'required|numeric|min:0.0001',

            'items.*.purchase_price' => 'required|numeric|min:0',

            'items.*.discount_type' => 'required|in:Percentage,Amount',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.batch_no' => 'nullable|string|max:60',
            'items.*.expiry_date' => 'nullable|date',

        ]);


        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }


        DB::beginTransaction();

        try {

            $purchase = $this->branchPurchase($id, true);

            if (!$purchase) {

                throw new Exception('Purchase not found.');
            }

            // Return items point at these purchase items; rebuilding them would orphan the return.
            if (PurchaseReturn::where('purchase_id', $purchase->id)->where('deleted', 'No')->exists()) {

                throw new Exception('This purchase has returns. Delete its returns before editing it.');
            }

            $this->checkItems($request->input('items', []));

            // Products / batches whose stock this edit changes — checked for negative stock once the new rows are in.
            $stockKeys = Stock::where('purchase_id', $purchase->id)->whereNull('purchase_return_id')->where('deleted', 'No')
                ->get(['product_id', 'product_variant_id', 'batch_id'])
                ->map(fn ($s) => [$s->product_id, $s->product_variant_id, $s->batch_id])->all();


            /*
            |--------------------------------------------------------------------------
            | Reverse Old Stock (purchase rows only, not return rows)
            |--------------------------------------------------------------------------
            */
            Stock::where('purchase_id', $purchase->id)->whereNull('purchase_return_id')->delete();


            /*
            |--------------------------------------------------------------------------
            | Delete Old Items
            |--------------------------------------------------------------------------
            */
            PurchaseItem::where('purchase_id', $purchase->id)->delete();


            $items = $request->input('items', []);


            $subtotal = 0;


            foreach ($items as $item) {

                $qty = (float)$item['qty'];
                $purchasePrice = (float)$item['purchase_price'];

                $itemSubtotal = $qty * $purchasePrice;

                $discountType = $item['discount_type'] ?? 'Amount';
                $discountValue = (float)($item['discount_value'] ?? 0);

                $itemDiscount = 0;


                if ($discountType == 'Percentage') {

                    $itemDiscount =
                        ($itemSubtotal * $discountValue) / 100;
                } else {

                    $itemDiscount = $discountValue;
                }


                if ($itemDiscount > $itemSubtotal) {

                    $itemDiscount = $itemSubtotal;
                }


                $itemTotal = $itemSubtotal - $itemDiscount;

                $subtotal += $itemTotal;
            }


            /*
            |--------------------------------------------------------------------------
            | Overall Discount
            |--------------------------------------------------------------------------
            */
            $discountType = $request->discount_type ?? 'Amount';
            $discountValue = (float)($request->discount_value ?? 0);

            $discountAmount = 0;


            if ($discountType == 'Percentage') {

                $discountAmount =
                    ($subtotal * $discountValue) / 100;
            } else {

                $discountAmount = $discountValue;
            }


            if ($discountAmount > $subtotal) {

                $discountAmount = $subtotal;
            }


            $grandTotal = $subtotal - $discountAmount;

            // Input VAT on the purchase after its discount.
            $vatPercent = min(100, max(0, (float) $request->input('vat_percent', $purchase->vat_percent ?? 0)));
            $vatAmount = round(($subtotal - $discountAmount) * $vatPercent / 100, 2);
            $grandTotal += $vatAmount;


            $paidAmount = (float)($request->paid_amount ?? 0);


            if ($paidAmount > $grandTotal) {

                $paidAmount = $grandTotal;
            }


            $dueAmount = $grandTotal - $paidAmount;


            /*
            |--------------------------------------------------------------------------
            | Update Purchase
            |--------------------------------------------------------------------------
            */
            $purchase->supplier_id = $request->supplier_id;
            $purchase->purchase_date = $request->purchase_date;

            $purchase->subtotal = round($subtotal, 2);

            $purchase->discount_type = $discountType;
            $purchase->discount_value = round($discountValue, 2);
            $purchase->discount_amount = round($discountAmount, 2);

            $purchase->vat_percent = $vatPercent;
            $purchase->vat_amount = $vatAmount;
            $purchase->grand_total = round($grandTotal, 2);

            $purchase->paid_amount = round($paidAmount, 2);
            $purchase->due_amount = round($dueAmount, 2);

            $purchase->payment_method = $request->payment_method ?: null;
            $purchase->payment_method_account = $request->payment_method_account ?: null;
            $purchase->remarks = $request->remarks;

            $purchase->updated_by = auth()->id();
            $purchase->updated_date = Carbon::now();
            $purchase->updated_at = Carbon::now();

            $purchase->save();


            /*
            |--------------------------------------------------------------------------
            | Insert New Items + Stock
            |--------------------------------------------------------------------------
            */
            foreach ($items as $item) {

                $qty = (float)$item['qty'];
                $purchasePrice = (float)$item['purchase_price'];

                $itemSubtotal = $qty * $purchasePrice;

                $itemDiscountType =
                    $item['discount_type'] ?? 'Amount';

                $itemDiscountValue =
                    (float)($item['discount_value'] ?? 0);

                $itemDiscount = 0;


                if ($itemDiscountType == 'Percentage') {

                    $itemDiscount =
                        ($itemSubtotal * $itemDiscountValue) / 100;
                } else {

                    $itemDiscount = $itemDiscountValue;
                }


                if ($itemDiscount > $itemSubtotal) {

                    $itemDiscount = $itemSubtotal;
                }


                $itemTotal = $itemSubtotal - $itemDiscount;


                $purchaseItem = new PurchaseItem();

                $purchaseItem->purchase_id = $purchase->id;
                $purchaseItem->product_id = $item['product_id'];

                $purchaseItem->product_variant_id =
                    !empty($item['product_variant_id'])
                    ? $item['product_variant_id']
                    : null;

                $purchaseItem->qty = $qty;
                $purchaseItem->purchase_price = $purchasePrice;

                $purchaseItem->discount_type = $itemDiscountType;
                $purchaseItem->discount_value = $itemDiscountValue;
                $purchaseItem->discount_amount = $itemDiscount;

                $purchaseItem->subtotal = $itemSubtotal;
                $purchaseItem->total = $itemTotal;

                $purchaseItem->deleted = 'No';
                $purchaseItem->status = 'Active';

                $purchaseItem->created_by = auth()->id();
                $purchaseItem->created_date = Carbon::now();

                $purchaseItem->created_at = Carbon::now();
                $purchaseItem->updated_at = Carbon::now();

                $purchaseItem->save();

                // Batch-tracked products: the batch (blank number = one per purchase line) goes on the item and the stock row.
                $batchId = null;
                if (DB::table('products')->where('id', $item['product_id'])->value('track_batch') === 'Yes') {
                    $batchId = StockController::batchFor(
                        (int) $item['product_id'],
                        !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null,
                        trim((string) ($item['batch_no'] ?? '')) ?: ($purchase->invoice_no . '-' . $purchaseItem->id),
                        $item['expiry_date'] ?? null,
                        null,
                        (float) $purchasePrice,
                        (int) $purchase->id
                    );
                    $purchaseItem->batch_id = $batchId;
                    $purchaseItem->save();
                }


                /*
                |--------------------------------------------------------------------------
                | New Stock
                |--------------------------------------------------------------------------
                */
                $stock = new Stock();

                $stock->product_id = $item['product_id'];

                $stock->product_variant_id =
                    !empty($item['product_variant_id'])
                    ? $item['product_variant_id']
                    : null;

                $stock->batch_id = $batchId;

                $stock->in_qty = $qty;
                $stock->out_qty = 0;

                $stock->purchase_id = $purchase->id;

                $stock->date = $request->purchase_date;

                $stock->reference_type = 'Purchase';
                $stock->reference_id = $purchase->id;

                $stock->created_at = Carbon::now();
                $stock->updated_at = Carbon::now();

                $stock->save();
            }


            /*
            |--------------------------------------------------------------------------
            | Re-post Supplier Ledger (purchase rows only, not return rows)
            |--------------------------------------------------------------------------
            */
            Payment::where('purchase_id', $purchase->id)
                ->whereNull('purchase_return_id')
                ->where('deleted', 'No')
                ->update([
                    'deleted'          => 'Yes',
                    'status'           => 'Inactive',
                    'deleted_by'       => auth()->id(),
                    'updated_by'       => auth()->id(),
                    'updated_datetime' => now(),
                    'deleted_datetime' => now(),
                ]);

            if ($grandTotal > 0) {
                $this->storeDebitPayment([
                    'party_id'       => $request->supplier_id,
                    'party_type'     => 'Supplier',
                    'payment_type'   => 'Purchase',
                    'datetime'       => $request->purchase_date,
                    'debit'          => $grandTotal,
                    'purchase_id'    => $purchase->id,
                    'payment_method' => '',
                    'payment_method_account' => '',
                ]);
            }

            if ($paidAmount > 0) {
                $this->storeCreditPayment([
                    'party_id'       => $request->supplier_id,
                    'party_type'     => 'Supplier',
                    'payment_type'   => 'Purchase Payment',
                    'datetime'       => $request->purchase_date,
                    'credit'         => $paidAmount,
                    'purchase_id'    => $purchase->id,
                    'payment_method' => $request->payment_method,
                    'payment_method_account' => $request->payment_method_account,
                ]);
            }

            $this->assertNoNegativeStock($stockKeys);


            DB::commit();


            return response()->json([
                'status' => true,
                'message' => 'Purchase updated successfully.',
                'redirect' => route('purchases.index')
            ]);
        } catch (Exception $e) {

            DB::rollBack();


            return $this->failResponse($e);
        }
    }


    /*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/
    public function delete($id)
    {
        DB::beginTransaction();

        try {

            $purchase = $this->branchPurchase($id, true);

            if (!$purchase) {

                throw new Exception('Purchase not found.');
            }

            // Its returns hold stock and supplier-ledger rows of their own; they must go first.
            if (PurchaseReturn::where('purchase_id', $purchase->id)->where('deleted', 'No')->exists()) {

                throw new Exception('This purchase has returns. Delete its returns first, then delete the purchase.');
            }

            $stockKeys = Stock::where('purchase_id', $purchase->id)->where('deleted', 'No')
                ->get(['product_id', 'product_variant_id', 'batch_id'])
                ->map(fn ($s) => [$s->product_id, $s->product_variant_id, $s->batch_id])->all();


            /*
        |--------------------------------------------------------------------------
        | Delete Purchase Items
        |--------------------------------------------------------------------------
        */
            $purchaseItems = PurchaseItem::where('purchase_id', $purchase->id)
                ->where('deleted', 'No')
                ->get();

            foreach ($purchaseItems as $purchaseItem) {

                $purchaseItem->deleted = 'Yes';
                $purchaseItem->status = 'Inactive';
                $purchaseItem->deleted_by = auth()->id();

                $purchaseItem->updated_by = auth()->id();
                $purchaseItem->updated_date = Carbon::now();

                $purchaseItem->updated_at = Carbon::now();

                $purchaseItem->save();

            }


            /*
        |--------------------------------------------------------------------------
        | Delete Purchase Stock
        |--------------------------------------------------------------------------
        */
            $stocks = Stock::where('purchase_id', $purchase->id)
                ->get();

            foreach ($stocks as $stock) {

                $stock->deleted = 'Yes';
                $stock->status = 'Inactive';
                $stock->deleted_by = auth()->id();

                $stock->updated_by = auth()->id();
                $stock->updated_date = Carbon::now();

                $stock->updated_at = Carbon::now();

                $stock->save();
            }


            /*
        |--------------------------------------------------------------------------
        | Delete Purchase Payments
        |--------------------------------------------------------------------------
        */
            $this->deletePaymentsBy('purchase_id', $purchase->id);


            /*
        |--------------------------------------------------------------------------
        | Delete Purchase
        |--------------------------------------------------------------------------
        */
            $purchase->deleted = 'Yes';
            $purchase->status = 'Inactive';
            $purchase->deleted_by = auth()->id();

            $purchase->updated_by = auth()->id();
            $purchase->updated_date = Carbon::now();

            $purchase->updated_at = Carbon::now();

            $purchase->save();

            // The goods must still be in stock to take them back out.
            $this->assertNoNegativeStock($stockKeys);


            DB::commit();


            return response()->json([
                'status' => true,
                'message' => 'Purchase deleted successfully.'
            ]);
        } catch (Exception $e) {

            DB::rollBack();

            return $this->failResponse($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */



    public function invoicePdf(Request $request, $id)
    {
        $purchase = $this->branchPurchase($id);
        abort_unless($purchase, 404, 'Purchase not found.');
        $supplier = Party::find($purchase->supplier_id);

        // payment_method / payment_method_account hold chart-of-account ids; the invoice prints their names.
        $paymentLabel = trim(implode(' - ', array_filter([
            $this->getCoaName($purchase->payment_method),
            $this->getCoaName($purchase->payment_method_account),
        ])));

        $items = DB::table('purchase_items')
            ->leftJoin('products', 'products.id', '=', 'purchase_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'purchase_items.product_variant_id')
            ->leftJoin('attributes', 'attributes.id', '=', 'product_variants.attribute_id')
            ->select(
                'purchase_items.*',
                'products.name as product_name',
                'products.sku',
                'products.barcode',
                'product_variants.barcode as variant_barcode',
                'attributes.name as variant_name'
            )
            ->where('purchase_items.purchase_id', $id)
            ->where('purchase_items.deleted', 'No')
            ->get();

        $amountInWords = amount_in_words($purchase->grand_total);
        $preparedBy = optional(\App\Models\User::find($purchase->created_by))->name;

        //$height = 460 + ($items->count() * 30);

        $pdf = PDF::loadView(
            'admin.inventory.purchase.invoice',
            compact('purchase', 'supplier', 'items', 'amountInWords', 'preparedBy', 'paymentLabel')
        )->setPaper('a4', 'portrait');

        $filename = $purchase->invoice_no . '.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }




    /*
|--------------------------------------------------------------------------
| Purchase Return Page
|--------------------------------------------------------------------------
*/
    public function returnPage($id)
    {
        $purchase = $this->branchPurchase($id);

        if (!$purchase) {
            abort(404, 'Purchase not found.');
        }

        return view(
            'admin.inventory.purchase.return',
            compact('purchase')
        );
    }

    /*
|--------------------------------------------------------------------------
| Purchase Return Data
|--------------------------------------------------------------------------
*/
    public function returnData($id)
    {
        try {

            $purchase = $this->branchPurchase($id);

            if (!$purchase || $purchase->status !== 'Active') {
                return response()->json([
                    'status' => false,
                    'message' => 'Purchase not found.'
                ], 404);
            }

            $supplierName = Party::where('id', $purchase->supplier_id)->value('name');

            $items = DB::table('purchase_items')
                ->leftJoin(
                    'products',
                    'products.id',
                    '=',
                    'purchase_items.product_id'
                )
                ->leftJoin(
                    'product_variants',
                    'product_variants.id',
                    '=',
                    'purchase_items.product_variant_id'
                )
                ->leftJoin(
                    'attributes',
                    'attributes.id',
                    '=',
                    'product_variants.attribute_id'
                )
                ->select(
                    'purchase_items.id as purchase_item_id',
                    'purchase_items.purchase_id',
                    'purchase_items.product_id',
                    'purchase_items.product_variant_id',
                    'purchase_items.qty',
                    'purchase_items.purchase_price',
                    'purchase_items.discount_type',
                    'purchase_items.discount_value',
                    'purchase_items.discount_amount',
                    'purchase_items.subtotal',
                    'purchase_items.total',

                    'products.name as product_name',
                    'products.sku',
                    'products.barcode',

                    'product_variants.barcode as variant_barcode',
                    'attributes.name as variant_name'
                )
                ->where(
                    'purchase_items.purchase_id',
                    $purchase->id
                )
                ->where(
                    'purchase_items.deleted',
                    'No'
                )
                ->where(
                    'purchase_items.status',
                    'Active'
                )
                ->get();

            foreach ($items as $item) {

                $returnedQty = PurchaseReturnItem::where(
                    'purchase_item_id',
                    $item->purchase_item_id
                )
                    ->where('deleted', 'No')
                    ->where('status', 'Active')
                    ->sum('qty');

                $item->returned_qty = (float) $returnedQty;

                $item->remaining_qty =
                    max(
                        0,
                        (float) $item->qty -
                            (float) $returnedQty
                    );
            }

            return response()->json([
                'status' => true,

                'data' => [
                    'purchase' => $purchase,
                    'supplier_name' => $supplierName,
                    'items' => $items
                ]
            ]);
        } catch (Exception $e) {

            return $this->failResponse($e, 'Unable to load the purchase.');
        }
    }

    /*
|--------------------------------------------------------------------------
| Store Purchase Return
|--------------------------------------------------------------------------
*/
    public function storeReturn(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'purchase_id' => 'required|integer',

            'return_date' => 'required|date',

            'items' => 'required|array|min:1',

            'items.*.purchase_item_id' =>
            'required|integer',

            'items.*.qty' =>
            'required|numeric|min:0.0001',

        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }


        DB::beginTransaction();

        try {

            /*
        |--------------------------------------------------------------------------
        | Purchase
        |--------------------------------------------------------------------------
        */

            $purchase = $this->branchPurchase($request->purchase_id, true);

            if (!$purchase || $purchase->status !== 'Active') {
                throw new Exception('Purchase not found.');
            }

            $ids = array_map(fn ($r) => (int) ($r['purchase_item_id'] ?? 0), $request->input('items', []));
            if (count($ids) !== count(array_unique($ids))) {
                throw new Exception('Each purchased line can appear only once in a return.');
            }

            $branch = (int) BranchController::activeId();
            $plannedOut = [];   // stock already claimed by earlier lines of this return, per product / variant / batch


            /*
        |--------------------------------------------------------------------------
        | Items
        |--------------------------------------------------------------------------
        */

            $requestItems = $request->input('items', []);

            $subtotal = 0;

            $validItems = [];


            foreach ($requestItems as $requestItem) {

                $purchaseItem = PurchaseItem::where(
                    'id',
                    $requestItem['purchase_item_id']
                )
                    ->where('purchase_id', $purchase->id)
                    ->where('deleted', 'No')
                    ->where('status', 'Active')
                    ->lockForUpdate()
                    ->first();

                if (!$purchaseItem) {

                    throw new Exception(
                        'Invalid purchase item.'
                    );
                }


                $returnQty = (float) $requestItem['qty'];


                /*
            |--------------------------------------------------------------------------
            | Already Returned
            |--------------------------------------------------------------------------
            */

                $alreadyReturned = PurchaseReturnItem::where(
                    'purchase_item_id',
                    $purchaseItem->id
                )
                    ->where('deleted', 'No')
                    ->where('status', 'Active')
                    ->sum('qty');


                $remainingQty =
                    (float) $purchaseItem->qty -
                    (float) $alreadyReturned;


                if ($returnQty > $remainingQty) {

                    throw new Exception(
                        'Return quantity for "' .
                            Product::find($purchaseItem->product_id)->name .
                            '" cannot exceed remaining quantity ' .
                            $remainingQty . '.'
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | Check Actual Stock
            |--------------------------------------------------------------------------
            */

                // What the branch still holds of this product (of this batch, for batch-tracked lines):
                // goods already sold cannot be sent back. Earlier lines of this return are counted too.
                $variantId = $purchaseItem->product_variant_id ? (int) $purchaseItem->product_variant_id : null;
                $batchId = $purchaseItem->batch_id ? (int) $purchaseItem->batch_id : null;
                $key = $purchaseItem->product_id . ':' . ($variantId ?: 0) . ':' . ($batchId ?: 0);

                $availableStock =
                    StockController::onHand((int) $purchaseItem->product_id, $variantId, $branch, $batchId)
                    - ($plannedOut[$key] ?? 0);

                $plannedOut[$key] = ($plannedOut[$key] ?? 0) + $returnQty;


                /*
            |--------------------------------------------------------------------------
            | Return Cannot Exceed Available Stock
            |--------------------------------------------------------------------------
            */

                if ($returnQty > $availableStock + 0.0005) {

                    throw new Exception(
                        'Not enough "' . Product::where('id', $purchaseItem->product_id)->value('name') . '" in stock to return. ' .
                            'In stock: ' . rtrim(rtrim(number_format(max(0, $availableStock), 4, '.', ''), '0'), '.') .
                            ', return qty: ' . $returnQty . '.'
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | Calculate Item Amount
            |--------------------------------------------------------------------------
            */

                $itemSubtotal =
                    $returnQty *
                    (float) $purchaseItem->purchase_price;


                $originalQty =
                    (float) $purchaseItem->qty;


                $originalItemDiscount =
                    (float) $purchaseItem->discount_amount;


                /*
            |--------------------------------------------------------------------------
            | Proportional Discount
            |--------------------------------------------------------------------------
            */

                $itemDiscount = 0;

                if ($originalQty > 0) {

                    $itemDiscount =
                        ($originalItemDiscount / $originalQty)
                        * $returnQty;
                }


                if ($itemDiscount > $itemSubtotal) {

                    $itemDiscount = $itemSubtotal;
                }


                $itemTotal =
                    $itemSubtotal -
                    $itemDiscount;


                $subtotal += $itemTotal;


                $validItems[] = [
                    'purchase_item' => $purchaseItem,
                    'qty' => $returnQty,
                    'subtotal' => $itemSubtotal,
                    'discount_amount' => $itemDiscount,
                    'total' => $itemTotal
                ];
            }


            /*
        |--------------------------------------------------------------------------
        | Header Discount
        |--------------------------------------------------------------------------
        */

            $discountAmount = 0;



            if ((float) $purchase->subtotal > 0) {

                $discountAmount =
                    (
                        (float) $purchase->discount_amount
                        /
                        (float) $purchase->subtotal
                    )
                    *
                    $subtotal;
            }


            if ($discountAmount > $subtotal) {

                $discountAmount = $subtotal;
            }


            // Input VAT paid on the goods comes back with them, at the purchase's VAT rate.
            $vatAmount = round(($subtotal - $discountAmount) * (float) $purchase->vat_percent / 100, 2);

            $grandTotal =
                $subtotal -
                $discountAmount +
                $vatAmount;


            /*
        |--------------------------------------------------------------------------
        | Return Number
        |--------------------------------------------------------------------------
        */

            $returnNo = $this->nextSequentialCode(PurchaseReturn::class, 'return_no', 'PRE');


            /*
        |--------------------------------------------------------------------------
        | Create Return
        |--------------------------------------------------------------------------
        */

            $purchaseReturn =
                new PurchaseReturn();

            $purchaseReturn->return_no =
                $returnNo;

            $purchaseReturn->purchase_id =
                $purchase->id;

            $purchaseReturn->supplier_id =
                $purchase->supplier_id;

            $purchaseReturn->datetime =
                $request->return_date;

            $purchaseReturn->subtotal =
                round($subtotal, 2);

            $purchaseReturn->discount_type =
                $purchase->discount_type;

            $purchaseReturn->discount_value =
                round($purchase->discount_value, 2);

            $purchaseReturn->discount_amount =
                round($discountAmount, 2);

            $purchaseReturn->grand_total =
                round($grandTotal, 2);

            $purchaseReturn->remarks =
                $request->remarks;

            $purchaseReturn->deleted =
                'No';

            $purchaseReturn->status =
                'Active';

            $purchaseReturn->created_by =
                auth()->id();

            $purchaseReturn->created_datetime =
                Carbon::now();

            $purchaseReturn->created_at =
                Carbon::now();

            $purchaseReturn->updated_at =
                Carbon::now();

            $purchaseReturn->save();




            foreach ($validItems as $returnItem) {

                $purchaseItem =
                    $returnItem['purchase_item'];

                $returnQty =
                    $returnItem['qty'];


                /*
            |--------------------------------------------------------------------------
            | Return Item
            |--------------------------------------------------------------------------
            */

                $item =
                    new PurchaseReturnItem();

                $item->purchase_return_id =
                    $purchaseReturn->id;

                $item->purchase_id =
                    $purchase->id;

                $item->purchase_item_id =
                    $purchaseItem->id;

                $item->product_id =
                    $purchaseItem->product_id;

                $item->product_variant_id =
                    $purchaseItem->product_variant_id;

                $item->qty =
                    $returnQty;

                $item->purchase_price =
                    $purchaseItem->purchase_price;

                $item->subtotal =
                    round(
                        $returnItem['subtotal'],
                        2
                    );


                $item->discount_value =
                    $purchaseItem->discount_value;

                $item->discount_amount =
                    round(
                        $returnItem['discount_amount'],
                        2
                    );

                $item->total =
                    round(
                        $returnItem['total'],
                        2
                    );

                $item->deleted =
                    'No';

                $item->status =
                    'Active';

                $item->created_by =
                    auth()->id();

                $item->created_datetime =
                    Carbon::now();

                $item->created_at =
                    Carbon::now();

                $item->updated_at =
                    Carbon::now();

                $item->save();


                /*
            |--------------------------------------------------------------------------
            | Stock Out
            |--------------------------------------------------------------------------
            */

                $stock =
                    new Stock();

                $stock->product_id =
                    $purchaseItem->product_id;

                $stock->product_variant_id =
                    $purchaseItem->product_variant_id;

                // Batch-tracked goods leave from the batch they came in with.
                $stock->batch_id =
                    $purchaseItem->batch_id;

                $stock->in_qty = 0;

                $stock->out_qty =
                    $returnQty;

                $stock->purchase_id =
                    $purchase->id;

                $stock->purchase_return_id =
                    $purchaseReturn->id;

                $stock->date =
                    $request->return_date;

                $stock->reference_type = 'Purchase Return';
                $stock->reference_id = $purchaseReturn->id;

                $stock->created_by =
                    auth()->id();

                $stock->created_at =
                    Carbon::now();

                $stock->updated_at =
                    Carbon::now();

                $stock->save();
            }


            /*
        |--------------------------------------------------------------------------
        | Update Purchase Financials
        |--------------------------------------------------------------------------
        */

            $oldGrandTotal =
                (float) $purchase->grand_total;


            $oldPaidAmount =
                (float) $purchase->paid_amount;


            $newGrandTotal =
                max(
                    0,
                    $oldGrandTotal -
                        $grandTotal
                );


            /*
        |--------------------------------------------------------------------------
        | Paid Amount
        |--------------------------------------------------------------------------
        |
        | If purchase was already paid and the return makes
        | the new grand total lower than paid amount,
        | the difference becomes supplier refund.
        |
        */

            $newPaidAmount =
                min(
                    $oldPaidAmount,
                    $newGrandTotal
                );


            $refundAmount =
                max(
                    0,
                    $oldPaidAmount -
                        $newPaidAmount
                );


            $newDueAmount =
                max(
                    0,
                    $newGrandTotal -
                        $newPaidAmount
                );


            $purchase->grand_total =
                round($newGrandTotal, 2);

            $purchase->paid_amount =
                round($newPaidAmount, 2);

            $purchase->due_amount =
                round($newDueAmount, 2);

            $purchase->updated_by =
                auth()->id();

            $purchase->updated_date =
                Carbon::now();

            $purchase->updated_at =
                Carbon::now();

            $purchase->save();


            /*
        |--------------------------------------------------------------------------
        | Accounting Entry
        |--------------------------------------------------------------------------
        */

            /*
         * Purchase return reduces the payable. Supplier due is opening + debit - credit
         * (PartyDueTrait, payable aging, dashboard), so the reduction is a CREDIT and the
         * supplier's cash refund, which settles that credit, is a DEBIT. The party ledger and
         * cash book read these rows by payment_type, so they show the same either way.
         */

            if ($grandTotal > 0) {
                $this->storeCreditPayment([
                    'party_id'           => $purchase->supplier_id,
                    'party_type'         => 'Supplier',
                    'payment_type'       => 'Purchase Payment',
                    'datetime'           => $request->return_date,
                    'credit'             => round($grandTotal, 2),
                    'purchase_id'        => $purchase->id,
                    'purchase_return_id' => $purchaseReturn->id,
                    'payment_method'     => '',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Supplier Refund
            |--------------------------------------------------------------------------
            */
            if ($refundAmount > 0) {
                $this->storeDebitPayment([
                    'party_id'           => $purchase->supplier_id,
                    'party_type'         => 'Supplier',
                    'payment_type'       => 'Payment',
                    'datetime'           => $request->return_date,
                    'debit'              => round($refundAmount, 2),
                    'purchase_id'        => $purchase->id,
                    'purchase_return_id' => $purchaseReturn->id,
                    // The refund comes back to the account the purchase was paid from (so the cash book can place it).
                    'payment_method'         => $purchase->payment_method ?: '',
                    'payment_method_account' => $purchase->payment_method_account ?: '',
                ]);
            }


            DB::commit();


            return response()->json([

                'status' => true,

                'message' =>
                'Purchase return created successfully.',

                'return_no' =>
                $purchaseReturn->return_no,

                'redirect' =>
                route('purchases.return.index')
            ]);
        } catch (Exception $e) {

            DB::rollBack();

            return $this->failResponse($e, 'Unable to save the purchase return.');
        }
    }






    /*
    |--------------------------------------------------------------------------
    | Purchase Return Index
    |--------------------------------------------------------------------------
    */

    public function returnIndex()
    {
        return view('admin.inventory.purchase.return_index');
    }


    /*
    |--------------------------------------------------------------------------
    | Purchase Return List
    |--------------------------------------------------------------------------
    */

    public function returnList()
    {
        try {

            $returns = BranchController::scope(DB::table('purchase_returns'), 'purchase_returns.branch_id')
                ->leftJoin(
                    'purchases',
                    'purchases.id',
                    '=',
                    'purchase_returns.purchase_id'
                )
                ->leftJoin(
                    'parties',
                    'parties.id',
                    '=',
                    'purchase_returns.supplier_id'
                )
                ->where(
                    'purchase_returns.deleted',
                    'No'
                )
                ->select(
                    'purchase_returns.*',
                    'purchases.invoice_no',
                    'parties.name as supplier_name'
                )
                ->orderBy(
                    'purchase_returns.id',
                    'desc'
                )
                ->get();


            $html = '';

            $i = 1;


            foreach ($returns as $row) {

                /*
                |--------------------------------------------------------------------------
                | Status Badge
                |--------------------------------------------------------------------------
                */

                if ($row->status == 'Active') {

                    $statusBadge =
                        '<span class="badge bg-success">Active</span>';

                    $statusText =
                        'Deactivate';
                } else {

                    $statusBadge =
                        '<span class="badge bg-secondary">Inactive</span>';

                    $statusText =
                        'Activate';
                }


                /*
                |--------------------------------------------------------------------------
                | Discount
                |--------------------------------------------------------------------------
                */

                $discount =
                    number_format(
                        (float) $row->discount_amount,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | Action
                |--------------------------------------------------------------------------
                */

                $action = action_dropdown([
                    ['label' => 'View', 'icon' => 'fas fa-eye', 'href' => route('purchases.return.view', $row->id)],
                    ['label' => 'Invoice', 'icon' => 'fas fa-file-pdf', 'href' => route('purchases.return.view', $row->id)],
                    ['divider' => true],
                    ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger deleteReturnBtn', 'attrs' => ['data-id' => $row->id]],
                ]);


                $html .= '

                    <tr>

                        <td>' .
                    $i .
                    '</td>

                        <td>' .
                    e($row->return_no) .
                    '</td>

                        <td>' .
                    e($row->invoice_no ?? '-') .
                    '</td>

                        <td>' .
                    e($row->datetime) .
                    '</td>

                        <td>' .
                    e($row->supplier_name ?? '-') .
                    '</td>

                        <td class="text-end">

                            ' .
                    number_format(
                        (float) $row->subtotal,
                        2
                    ) .
                    '

                        </td>

                        <td class="text-end">

                            ' .
                    $discount .
                    '

                        </td>

                        <td class="text-end">

                            ' .
                    number_format(
                        (float) $row->grand_total,
                        2
                    ) .
                    '

                        </td>

                        <td>

                            ' .
                    $statusBadge .
                    '

                        </td>

                        <td>

                            ' .
                    $action .
                    '

                        </td>

                    </tr>

                ';


                $i++;
            }


            return response()->json([

                'status' => true,

                'html' => $html,

            ]);
        } catch (Exception $e) {

            return $this->failResponse($e, 'Unable to load purchase returns.');
        }
    }


    /*
    |--------------------------------------------------------------------------
    | View Purchase Return
    |--------------------------------------------------------------------------
    */

    public function returnView($id)
    {
        $purchaseReturn = BranchController::scope(DB::table('purchase_returns'), 'purchase_returns.branch_id')
            ->leftJoin(
                'purchases',
                'purchases.id',
                '=',
                'purchase_returns.purchase_id'
            )
            ->leftJoin(
                'parties',
                'parties.id',
                '=',
                'purchase_returns.supplier_id'
            )
            ->where(
                'purchase_returns.id',
                $id
            )
            ->where(
                'purchase_returns.deleted',
                'No'
            )
            ->select(
                'purchase_returns.*',
                'purchases.invoice_no',
                'parties.name as supplier_name'
            )
            ->first();


        if (!$purchaseReturn) {

            return redirect()
                ->route('purchases.return.index')
                ->with(
                    'error',
                    'Purchase return not found.'
                );
        }


        $items = DB::table('purchase_return_items')
            ->leftJoin(
                'products',
                'products.id',
                '=',
                'purchase_return_items.product_id'
            )
            ->leftJoin(
                'product_variants',
                'product_variants.id',
                '=',
                'purchase_return_items.product_variant_id'
            )
            ->where(
                'purchase_return_items.purchase_return_id',
                $id
            )
            ->where(
                'purchase_return_items.deleted',
                'No'
            )
            ->select(
                'purchase_return_items.*',
                'products.name as product_name',
                'product_variants.barcode as variant_barcode'
            )
            ->get();


        return view(
            'admin.inventory.purchase.return_view',
            compact(
                'purchaseReturn',
                'items'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Purchase Return
    |--------------------------------------------------------------------------
    */

    public function returnDelete($id)
    {
        DB::beginTransaction();

        try {

            $purchaseReturn = BranchController::scope(PurchaseReturn::query(), 'branch_id')
                ->where('id', $id)
                ->where('deleted', 'No')
                ->lockForUpdate()
                ->first();

            if (!$purchaseReturn) {
                throw new Exception('Purchase return not found.');
            }

            if ($purchaseReturn->status != 'Active') {
                throw new Exception('Only active purchase returns can be deleted.');
            }

            $purchase = Purchase::where('id', $purchaseReturn->purchase_id)->lockForUpdate()->first();

            // The supplier refund recorded with the return (row type "Payment"; old rows stored it as a credit, new ones as a debit).
            $refund = (float) Payment::where('purchase_return_id', $purchaseReturn->id)
                ->where('payment_type', 'Payment')
                ->where('deleted', 'No')
                ->sum(DB::raw('COALESCE(debit, 0) + COALESCE(credit, 0)'));

            $now = now();
            $userId = auth()->id();

            PurchaseReturnItem::where('purchase_return_id', $purchaseReturn->id)->update([
                'deleted'          => 'Yes',
                'deleted_by'       => $userId,
                'updated_by'       => $userId,
                'deleted_datetime' => $now,
                'updated_at'       => $now,
            ]);

            // The returned goods come back into stock.
            DB::table('stocks')
                ->where('purchase_return_id', $purchaseReturn->id)
                ->update([
                    'deleted'      => 'Yes',
                    'deleted_by'   => $userId,
                    'deleted_date' => $now,
                    'updated_by'   => $userId,
                    'updated_date' => $now,
                    'updated_at'   => $now,
                ]);

            $this->deletePaymentsBy('purchase_return_id', $purchaseReturn->id);

            // Undo what the return did to the purchase: its total went down by the return and its paid amount by the refund.
            if ($purchase && $purchase->deleted === 'No') {
                $grand = round((float) $purchase->grand_total + (float) $purchaseReturn->grand_total, 2);
                $paid = round(min($grand, (float) $purchase->paid_amount + $refund), 2);

                $purchase->grand_total = $grand;
                $purchase->paid_amount = $paid;
                $purchase->due_amount = round(max(0, $grand - $paid), 2);
                $purchase->updated_by = $userId;
                $purchase->updated_date = $now;
                $purchase->updated_at = $now;
                $purchase->save();
            }

            $purchaseReturn->deleted = 'Yes';
            $purchaseReturn->deleted_by = $userId;
            $purchaseReturn->updated_by = $userId;
            $purchaseReturn->deleted_datetime = $now;
            $purchaseReturn->updated_at = $now;
            $purchaseReturn->save();

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Purchase return deleted. Its goods are back in stock and the purchase due is restored.',
            ]);
        } catch (Exception $e) {

            DB::rollBack();

            return $this->failResponse($e, 'Unable to delete the purchase return.');
        }
    }
}
