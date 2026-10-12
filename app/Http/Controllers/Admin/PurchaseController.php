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
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use PDF;
use Exception;
use App\Traits\PartyDueTrait;
use App\Traits\PaymentTrait;
use App\Traits\SequentialCodeTrait;

class PurchaseController extends Controller
{
    use PartyDueTrait, PaymentTrait, SequentialCodeTrait;
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
        return view('admin.inventory.purchase.create', compact('suppliers', 'products','childCoas'));
    }


    /*
    |--------------------------------------------------------------------------
    | Purchase List AJAX
    |--------------------------------------------------------------------------
    */
    public function list(Request $request)
    {
        try {

            $purchases = DB::table('purchases')
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

                $editUrl = url('/admin/purchases/edit/' . $row->id);

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
                    <td class="text-center">
                        <div class="dropdown">
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                <i class="fa fa-cogs text-secondary me-2"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item"
                                    href="' . url('/admin/purchases/invoice-pdf/' . $row->id) . '"
                                    target="_blank">
                                        <i class="fa fa-file-pdf text-secondary me-2"></i> Invoice
                                    </a>
                                </li>
                                <li class="d-none">
                                    <a class="dropdown-item" href="' . $editUrl . '">
                                        <i class="fa fa-edit text-info me-2"></i> Edit
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item"
                                    href="' . url('/admin/purchases/return/' . $row->id) . '">
                                        <i class="fa fa-undo text-warning me-2"></i> Purchase Return
                                    </a>
                                </li>
                                <li>
                                    <button type="button"
                                            class="dropdown-item text-danger deleteBtn"
                                            data-id="' . $row->id . '">
                                        <i class="fa fa-trash me-2"></i> Delete
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>';
            }

            return response()->json([
                'status' => true,
                'html'   => $html
            ]);
        } catch (Exception $e) {

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
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

            'payment_method' => 'required|integer|exists:chart_of_accounts,id',
            'payment_method_account' => 'required|integer|exists:chart_of_accounts,id',

            'items' => 'required|array|min:1',

            'items.*.product_id' => 'required|integer',
            'items.*.product_variant_id' => 'nullable|integer',

            'items.*.qty' => 'required|numeric|min:0.0001',

            'items.*.purchase_price' => 'required|numeric|min:0',

            'items.*.discount_type' => 'required|in:Percentage,Amount',
            'items.*.discount_value' => 'nullable|numeric|min:0',

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


            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
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

            $purchase = Purchase::where('id', $id)
                ->where('deleted', 'No')
                ->first();

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

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
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

            'payment_method' => 'nullable|integer|exists:chart_of_accounts,id',
            'payment_method_account' => 'nullable|integer|exists:chart_of_accounts,id',

            'items' => 'required|array|min:1',

            'items.*.product_id' => 'required|integer',
            'items.*.product_variant_id' => 'nullable|integer',

            'items.*.qty' => 'required|numeric|min:0.0001',

            'items.*.purchase_price' => 'required|numeric|min:0',

            'items.*.discount_type' => 'required|in:Percentage,Amount',
            'items.*.discount_value' => 'nullable|numeric|min:0',

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

            $purchase = Purchase::where('id', $id)
                ->where('deleted', 'No')
                ->lockForUpdate()
                ->first();

            if (!$purchase) {

                throw new Exception('Purchase not found.');
            }

            // Return items point at these purchase items; rebuilding them would orphan the return.
            if (PurchaseReturn::where('purchase_id', $purchase->id)->where('deleted', 'No')->exists()) {

                throw new Exception('This purchase has returns. Delete its returns before editing it.');
            }


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


            DB::commit();


            return response()->json([
                'status' => true,
                'message' => 'Purchase updated successfully.',
                'redirect' => route('purchases.index')
            ]);
        } catch (Exception $e) {

            DB::rollBack();


            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
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

            $purchase = Purchase::where('id', $id)
                ->where('deleted', 'No')
                ->first();

            if (!$purchase) {

                throw new Exception('Purchase not found.');
            }


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


            DB::commit();


            return response()->json([
                'status' => true,
                'message' => 'Purchase deleted successfully.'
            ]);
        } catch (Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */



    public function invoicePdf(Request $request, $id)
    {
        $purchase = Purchase::where('id', $id)->where('deleted', 'No')->firstOrFail();
        $supplier = Party::find($purchase->supplier_id);

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
            compact('purchase', 'supplier', 'items', 'amountInWords', 'preparedBy')
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
        //return $id;
        $purchase = Purchase::where('id', $id)
            ->where('deleted', 'No')
            ->first();

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

            $purchase = Purchase::where('id', $id)
                ->where('deleted', 'No')
                ->where('status', 'Active')
                ->first();

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
                    'items' => $items
                ]
            ]);
        } catch (Exception $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
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

            $purchase = Purchase::where('id', $request->purchase_id)
                ->where('deleted', 'No')
                ->where('status', 'Active')
                ->lockForUpdate()
                ->first();

            if (!$purchase) {
                throw new Exception('Purchase not found.');
            }


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

                $stockQuery = Stock::where(
                    'product_id',
                    $purchaseItem->product_id
                )
                    ->where(
                        'purchase_id',
                        $purchase->id
                    )
                    ->where('deleted', 'No');


                if (!empty($purchaseItem->product_variant_id)) {

                    $stockQuery->where(
                        'product_variant_id',
                        $purchaseItem->product_variant_id
                    );
                } else {

                    $stockQuery->whereNull(
                        'product_variant_id'
                    );
                }


                $purchaseStockQty =
                    (float) $stockQuery->sum('in_qty');


                $purchaseStockOutQty =
                    (float) $stockQuery->sum('out_qty');


                $availableStock =
                    $purchaseStockQty -
                    $purchaseStockOutQty;


                /*
            |--------------------------------------------------------------------------
            | Return Cannot Exceed Available Stock
            |--------------------------------------------------------------------------
            */

                if ($returnQty > $availableStock) {

                    throw new Exception(
                        'Insufficient stock available for return. ' .
                            'Available: ' .
                            $availableStock .
                            ', Requested: ' .
                            $returnQty
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


            $grandTotal =
                $subtotal -
                $discountAmount;


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

                $stock->in_qty = 0;

                $stock->out_qty =
                    $returnQty;

                $stock->purchase_id =
                    $purchase->id;

                $stock->purchase_return_id =
                    $purchaseReturn->id;

                $stock->date =
                    $request->return_date;

                if (isset($stock->reference_type)) {

                    $stock->reference_type =
                        'Purchase Return';
                }

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
         * Purchase return reduces payable.
         *
         * Debit = supplier payable reduction.
         */

            if ($grandTotal > 0) {
                $this->storeDebitPayment([
                    'party_id'           => $purchase->supplier_id,
                    'party_type'         => 'Supplier',
                    'payment_type'       => 'Purchase Payment',
                    'datetime'           => $request->return_date,
                    'debit'              => round($grandTotal, 2),
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
                $this->storeCreditPayment([
                    'party_id'           => $purchase->supplier_id,
                    'party_type'         => 'Supplier',
                    'payment_type'       => 'Payment',
                    'datetime'           => $request->return_date,
                    'credit'             => round($refundAmount, 2),
                    'purchase_id'        => $purchase->id,
                    'purchase_return_id' => $purchaseReturn->id,
                    'payment_method'     => '',
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
                route('purchases.index')
            ]);
        } catch (Exception $e) {

            DB::rollBack();

            return response()->json([

                'status' => false,

                'message' =>
                $e->getMessage()

            ], 500);
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

            $returns = DB::table('purchase_returns')
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

                $action = '

                    <div class="dropdown">

                        <button
                            class="btn btn-sm btn-primary dropdown-toggle"
                            type="button"
                            data-bs-toggle="dropdown">

                            Action

                        </button>


                        <ul class="dropdown-menu">

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="' .
                    route(
                        'purchases.return.view',
                        $row->id
                    ) .
                    '">

                                    <i class="fa fa-eye text-primary me-2"></i>

                                    View

                                </a>

                            </li>


                            <li>

                                <a
                                    class="dropdown-item"
                                    href="' .
                    route(
                        'purchases.return.view',
                        $row->id
                    ) .
                    '">

                                    <i class="fa fa-file-pdf text-danger me-2"></i>

                                    Invoice

                                </a>

                            </li>





                            <li>

                                <a
                                    href="javascript:void(0)"
                                    class="dropdown-item deleteReturnBtn"
                                    data-id="' .
                    $row->id .
                    '">

                                    <i class="fa fa-trash text-danger me-2"></i>

                                    Delete

                                </a>

                            </li>

                        </ul>

                    </div>

                ';


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

            return response()->json([

                'status' => false,

                'message' =>
                'Unable to load purchase returns.',

                'error' =>
                $e->getMessage(),

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | View Purchase Return
    |--------------------------------------------------------------------------
    */

    public function returnView($id)
    {
        $purchaseReturn = DB::table('purchase_returns')
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

            $purchaseReturn = PurchaseReturn::where(
                'id',
                $id
            )
                ->where(
                    'deleted',
                    'No'
                )
                ->first();


            if (!$purchaseReturn) {

                DB::rollBack();

                return response()->json([

                    'status' => false,

                    'message' =>
                    'Purchase return not found.'

                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | Do not delete inactive return
            |--------------------------------------------------------------------------
            */

            if ($purchaseReturn->status != 'Active') {

                DB::rollBack();

                return response()->json([

                    'status' => false,

                    'message' =>
                    'Only active purchase returns can be deleted.'

                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | Delete Return Items
            |--------------------------------------------------------------------------
            */

            PurchaseReturnItem::where(
                'purchase_return_id',
                $purchaseReturn->id
            )->update([

                'deleted' => 'Yes',

                'deleted_by' =>
                Session::get('user_id'),

                'updated_by' =>
                Session::get('user_id'),

                'deleted_datetime' =>
                now(),

                'updated_at' =>
                now(),

            ]);


            /*
            |--------------------------------------------------------------------------
            | Delete Return Stock
            |--------------------------------------------------------------------------
            */

            DB::table('stocks')
                ->where(
                    'purchase_return_id',
                    $purchaseReturn->id
                )
                ->update([

                    'deleted' => 'Yes',

                    'deleted_by' =>
                    Session::get('user_id'),

                    'updated_by' =>
                    Session::get('user_id'),

                    'updated_date' =>
                    now(),

                    'updated_at' =>
                    now(),

                ]);


            /*
            |--------------------------------------------------------------------------
            | Delete Return Payments
            |--------------------------------------------------------------------------
            */

            $this->deletePaymentsBy('purchase_return_id', $purchaseReturn->id);


            /*
            |--------------------------------------------------------------------------
            | Delete Purchase Return
            |--------------------------------------------------------------------------
            */

            $purchaseReturn->deleted =
                'Yes';

            $purchaseReturn->deleted_by =
                Session::get('user_id');

            $purchaseReturn->updated_by =
                Session::get('user_id');

            $purchaseReturn->deleted_datetime =
                now();

            $purchaseReturn->updated_at =
                now();

            $purchaseReturn->save();


            DB::commit();


            return response()->json([

                'status' => true,

                'message' =>
                'Purchase return deleted successfully.'

            ]);
        } catch (Exception $e) {

            DB::rollBack();

            return response()->json([

                'status' => false,

                'message' =>
                'Unable to delete purchase return.',

                'error' =>
                $e->getMessage(),

            ], 500);
        }
    }
}
