<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Sale;
use App\Models\Admin\SaleItem;
use App\Models\Admin\SaleReturn;
use App\Models\Admin\SaleReturnItem;
use App\Models\Admin\SaleOrder;
use App\Models\Admin\SaleOrderItem;
use App\Models\Admin\Product;
use App\Models\Admin\ProductVariant;
use App\Models\Admin\Stock;
use App\Models\Admin\Party;
use App\Models\Admin\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use PDF;
use Exception;
use App\Traits\PartyDueTrait;

class SaleController extends Controller
{
    use PartyDueTrait;
    /*
    |--------------------------------------------------------------------------
    | Sales Index / List Page
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $sale_type = $request->input('type', 'Walk-In');
        if (!in_array($sale_type, ['Walk-In', 'Wholesale'])) {
            $sale_type = 'Walk-In';
        }
        $party_type = $sale_type . ' Sale';

        return view('admin.inventory.sale.index', compact('sale_type', 'party_type'));
    }

    /*
    |--------------------------------------------------------------------------
    | Sales Create Page
    |--------------------------------------------------------------------------
    */
    public function create(Request $request)
    {
        $sale_type = $request->input('type', 'Walk-In');
        if (!in_array($sale_type, ['Walk-In', 'Wholesale'])) {
            $sale_type = 'Walk-In';
        }
        $party_type = 'Add ' . $sale_type . ' Sale';

        // For Wholesale, load Customer parties
        $customers = [];
        if ($sale_type === 'Wholesale') {
            $customers = Party::where('deleted', 'No')
                ->where('status', 'Active')
                ->where('type', 'Customer')
                ->orderBy('name')
                ->get();
        }

        $products = Product::where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        return view('admin.inventory.sale.create', compact('sale_type', 'party_type', 'customers', 'products'));
    }

    /*
    |--------------------------------------------------------------------------
    | Sales AJAX List
    |--------------------------------------------------------------------------
    */
    public function list(Request $request)
    {
        try {
            $sale_type = $request->input('type');

            $query = DB::table('sales')
                ->leftJoin('parties', 'parties.id', '=', 'sales.customer_id')
                ->select(
                    'sales.*',
                    'parties.name as customer_name',
                    'parties.phone as customer_phone'
                )
                ->where('sales.deleted', 'No');

            if (!empty($sale_type)) {
                $query->where('sales.sale_type', $sale_type);
            }

            $sales = $query->orderByDesc('sales.id')->get();

            $html = '';
            foreach ($sales as $index => $row) {
                $statusBadge = $row->status === 'Active'
                    ? '<i class="fas fa-check-circle text-success" style="font-size:18px;"></i>'
                    : '<i class="fas fa-times-circle text-danger" style="font-size:18px;"></i>';

                $typeBadge = $row->sale_type === 'Wholesale'
                    ? '<span class="badge bg-info text-dark">Wholesale</span>'
                    : '<span class="badge bg-primary">Walk-In</span>';

                $invoiceUrl = route('sales.invoice.pdf', $row->id);
                $returnUrl  = route('sales.return.page', $row->id);

                $html .= '
                <tr>
                    <td>' . ($index + 1) . '</td>
                    <td><strong class="text-primary">' . e($row->invoice_no) . '</strong></td>
                    <td>' . e($row->sale_date ?? '') . '</td>
                    <td>
                        <strong>' . e($row->customer_name ?? 'Walk-In Customer') . '</strong>' .
                    ($row->customer_phone ? '<br><small class="text-muted"><i class="fas fa-phone-alt me-1"></i>' . e($row->customer_phone) . '</small>' : '') .
                    '</td>
                    <td class="text-end">' . number_format((float) $row->subtotal, 2, '.', '') . '</td>
                    <td class="text-end">' . number_format((float) $row->discount_amount, 2, '.', '') . '</td>
                    <td class="text-end fw-bold">' . number_format((float) $row->grand_total, 2, '.', '') . '</td>
                    <td class="text-end text-success">' . number_format((float) $row->paid_amount, 2, '.', '') . '</td>
                    <td class="text-end ' . ((float)$row->due_amount > 0 ? 'text-danger fw-bold' : '') . '">' . number_format((float) $row->due_amount, 2, '.', '') . '</td>
                    <td class="text-center">' . e($row->payment_method ?? 'Cash') . '</td>
                    <td class="text-center">
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-cogs text-secondary me-1"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="' . $invoiceUrl . '" target="_blank">
                                        <i class="fa fa-file-pdf text-danger me-2"></i> Invoice PDF
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="' . $returnUrl . '">
                                        <i class="fa fa-undo text-warning me-2"></i> Sale Return
                                    </a>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item text-danger deleteBtn" data-id="' . $row->id . '">
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
    | Customer Lookup By Phone (Walk-In Sales Auto Resolution)
    |--------------------------------------------------------------------------
    */
    public function customerByPhone($phone)
    {
        try {
            $phone = trim($phone);
            if (strlen($phone) < 6) {
                return response()->json(['status' => false, 'found' => false, 'message' => 'Phone too short']);
            }

            $customer = Party::where('deleted', 'No')
                ->where(function ($q) use ($phone) {
                    $q->where('phone', $phone)
                        ->orWhere('phone', 'like', '%' . substr($phone, -11));
                })
                ->first();

            if ($customer) {
                $due = $this->partyDue($customer->id);
                return response()->json([
                    'status'      => true,
                    'found'       => true,
                    'customer'    => $customer,
                    'current_due' => number_format((float) $due, 2, '.', '')
                ]);
            }

            return response()->json([
                'status' => true,
                'found'  => false,
                'message' => 'New Customer'
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Customer Due Balance (Wholesale)
    |--------------------------------------------------------------------------
    */
    public function customerDue($customerId)
    {
        try {
            $customer = Party::find($customerId);
            if (!$customer) {
                return response()->json(['status' => false, 'message' => 'Customer not found'], 404);
            }

            $due = $this->partyDue($customerId);

            return response()->json([
                'status'   => true,
                'customer' => $customer,
                'due'      => number_format((float) $due, 2, '.', '')
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Product Live Search with Stock Check
    |--------------------------------------------------------------------------
    */
    public function productSearch(Request $request)
    {
        try {
            $search = trim((string) $request->input('search', ''));

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

            // Attach current stock
            foreach ($products as $product) {
                $stock = DB::table('stocks')
                    ->where('product_id', $product->id)
                    ->where('deleted', 'No')
                    ->selectRaw('COALESCE(SUM(in_qty) - SUM(out_qty), 0) as current_stock')
                    ->value('current_stock');

                $product->current_stock = (float) ($stock ?? 0);
            }

            return response()->json([
                'status'   => true,
                'products' => $products
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Product Variants with Stock
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
                    'status'  => false,
                    'message' => 'Product not found.'
                ], 404);
            }

            // Calculate stock for base product
            $baseStock = DB::table('stocks')
                ->where('product_id', $productId)
                ->where('deleted', 'No')
                ->selectRaw('COALESCE(SUM(in_qty) - SUM(out_qty), 0) as current_stock')
                ->value('current_stock');
            $product->current_stock = (float) ($baseStock ?? 0);

            $variants = DB::table('product_variants')
                ->leftJoin('attributes', 'attributes.id', '=', 'product_variants.attribute_id')
                ->select(
                    'product_variants.*',
                    'attributes.name as attribute_name'
                )
                ->where('product_variants.product_id', $productId)
                ->where('product_variants.deleted', 'No')
                ->where('product_variants.status', 'Active')
                ->orderBy('product_variants.id')
                ->get();

            foreach ($variants as $variant) {
                $stock = DB::table('stocks')
                    ->where('product_variant_id', $variant->id)
                    ->where('deleted', 'No')
                    ->selectRaw('COALESCE(SUM(in_qty) - SUM(out_qty), 0) as current_stock')
                    ->value('current_stock');

                $variant->current_stock = (float) ($stock ?? 0);
            }

            return response()->json([
                'status'   => true,
                'data'     => [
                    'product'  => $product,
                    'variants' => $variants
                ],
                'variants' => $variants
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Store Sale (Walk-In or Wholesale)
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $sale_type = $request->input('sale_type', 'Walk-In');

        /*
    |--------------------------------------------------------------------------
    | Decode sale items JSON
    |--------------------------------------------------------------------------
    */

        if ($request->filled('items') && is_string($request->input('items'))) {

            $decodedItems = json_decode(
                $request->input('items'),
                true
            );

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedItems)) {

                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid sale items data.'
                ], 422);
            }

            $request->merge([
                'items' => $decodedItems
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

        $rules = [
            'sale_type' => 'required|in:Walk-In,Wholesale',
            'sale_date' => 'required|date',

            'discount_type' => 'required|in:Percentage,Amount',

            'discount_value' => [
                'nullable',
                'numeric',
                'min:0'
            ],

            'paid_amount' => [
                'nullable',
                'numeric',
                'min:0'
            ],

            'payment_method' => [
                'nullable',
                'string',
                'max:100'
            ],

            'items' => 'required|array|min:1',

            'items.*.product_id' => [
                'required',
                'integer'
            ],

            'items.*.qty' => [
                'required',
                'numeric',
                'min:0.0001'
            ],

            'items.*.sale_price' => [
                'required',
                'numeric',
                'min:0'
            ],

            'items.*.discount_type' => [
                'required',
                'in:Percentage,Amount'
            ],

            'items.*.discount_value' => [
                'nullable',
                'numeric',
                'min:0'
            ],
        ];

        if ($sale_type === 'Wholesale') {

            $rules['customer_id'] =
                'required|integer|exists:parties,id';
        } else {

            $rules['customer_phone'] =
                'required|string|min:6|max:20';

            $rules['customer_name'] =
                'nullable|string|max:255';
        }

        $validator = Validator::make(
            $request->all(),
            $rules
        );

        if ($validator->fails()) {

            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Now items is a real PHP array
    |--------------------------------------------------------------------------
    */

        $items = $request->input('items', []);

        DB::beginTransaction();

        try {
            // Customer Resolution
            $customerId = null;
            if ($sale_type === 'Wholesale') {
                $customerId = $request->customer_id;
            } else {
                // Walk-In: check if exists by phone, or create new WIC
                $phone = trim($request->customer_phone);
                $existingParty = Party::where('deleted', 'No')
                    ->where(function ($q) use ($phone) {
                        $q->where('phone', $phone)
                            ->orWhere('phone', 'like', '%' . substr($phone, -11));
                    })
                    ->first();

                if ($existingParty) {
                    $customerId = $existingParty->id;
                    // Update name/address if previously blank
                    if (!empty($request->customer_name) && $existingParty->name === 'Walk-In Customer') {
                        $existingParty->name = $request->customer_name;
                        $existingParty->save();
                    }
                } else {
                    $newParty = new Party();
                    $newParty->code         = $this->generatePartyCode('Walk-In Customer');
                    $newParty->type         = 'Walk-In Customer';
                    $newParty->name         = $request->customer_name ?: 'Walk-In Customer';
                    $newParty->phone        = $phone;
                    $newParty->address      = $request->customer_address ?: 'Walk-In';
                    $newParty->status       = 'Active';
                    $newParty->deleted      = 'No';
                    $newParty->created_by   = auth()->id();
                    $newParty->created_date = now();
                    $newParty->save();
                    $customerId = $newParty->id;
                }
            }

            $items = $request->input('items', []);
            $subtotal = 0;

            // Calculate Item Totals
            foreach ($items as $item) {
                $qty = (float) $item['qty'];
                $price = (float) $item['sale_price'];
                $itemSub = $qty * $price;

                $dType = $item['discount_type'] ?? 'Amount';
                $dVal  = (float) ($item['discount_value'] ?? 0);
                $itemDisc = ($dType === 'Percentage') ? ($itemSub * $dVal) / 100 : $dVal;
                if ($itemDisc > $itemSub) $itemDisc = $itemSub;

                $subtotal += ($itemSub - $itemDisc);
            }

            // Header Discount
            $discountType  = $request->discount_type ?? 'Amount';
            $discountValue = (float) ($request->discount_value ?? 0);
            $discountAmount = ($discountType === 'Percentage') ? ($subtotal * $discountValue) / 100 : $discountValue;
            if ($discountAmount > $subtotal) $discountAmount = $subtotal;

            $grandTotal = $subtotal - $discountAmount;
            $paidAmount = (float) ($request->paid_amount ?? 0);
            if ($paidAmount > $grandTotal) $paidAmount = $grandTotal;
            $dueAmount = $grandTotal - $paidAmount;

            // Generate Invoice No: INV-XXXXXX
            $last = Sale::max('invoice_no');
            $next = $last ? ((int) preg_replace('/[^0-9]/', '', $last)) + 1 : 1;
            $invoiceNo = 'INV-' . str_pad($next, 6, '0', STR_PAD_LEFT);

            // Create Sale
            $sale = new Sale();
            $sale->invoice_no       = $invoiceNo;
            $sale->sale_type        = $sale_type;
            $sale->customer_id      = $customerId;
            $sale->sale_date        = $request->sale_date;
            $sale->subtotal         = round($subtotal, 2);
            $sale->discount_type    = $discountType;
            $sale->discount_value   = round($discountValue, 2);
            $sale->discount_amount  = round($discountAmount, 2);
            $sale->grand_total      = round($grandTotal, 2);
            $sale->paid_amount      = round($paidAmount, 2);
            $sale->due_amount       = round($dueAmount, 2);
            $sale->payment_method   = $request->payment_method ?: 'Cash';
            $sale->remarks          = $request->remarks;
            $sale->deleted          = 'No';
            $sale->status           = 'Active';
            $sale->created_by       = auth()->id();
            $sale->created_date     = now();
            $sale->save();

            // Save Sale Items & Deduct Stock
            foreach ($items as $item) {
                $qty = (float) $item['qty'];
                $price = (float) $item['sale_price'];
                $itemSub = $qty * $price;

                $dType = $item['discount_type'] ?? 'Amount';
                $dVal  = (float) ($item['discount_value'] ?? 0);
                $itemDisc = ($dType === 'Percentage') ? ($itemSub * $dVal) / 100 : $dVal;
                if ($itemDisc > $itemSub) $itemDisc = $itemSub;
                $itemTotal = $itemSub - $itemDisc;

                $saleItem = new SaleItem();
                $saleItem->sale_id            = $sale->id;
                $saleItem->product_id         = $item['product_id'];
                $saleItem->product_variant_id = !empty($item['product_variant_id']) ? $item['product_variant_id'] : null;
                $saleItem->qty                = $qty;
                $saleItem->sale_price         = $price;
                $saleItem->purchase_price     = (float) (Product::find($item['product_id'])->purchase_price ?? 0);
                $saleItem->discount_type      = $dType;
                $saleItem->discount_value     = $dVal;
                $saleItem->discount_amount    = $itemDisc;
                $saleItem->subtotal           = $itemSub;
                $saleItem->total              = $itemTotal;
                $saleItem->deleted            = 'No';
                $saleItem->status             = 'Active';
                $saleItem->created_by         = auth()->id();
                $saleItem->created_date       = now();
                $saleItem->save();

                // Stock Reduction (out_qty)
                $stock = new Stock();
                $stock->product_id         = $item['product_id'];
                $stock->product_variant_id = $saleItem->product_variant_id;
                $stock->in_qty             = 0;
                $stock->out_qty            = $qty;
                $stock->sale_id            = $sale->id;
                $stock->date               = $request->sale_date;
                $stock->reference_type     = 'Sale';
                $stock->reference_id       = $sale->id;
                $stock->remarks            = 'Sale: ' . $invoiceNo;
                $stock->deleted            = 'No';
                $stock->status             = 'Active';
                $stock->created_by         = auth()->id();
                $stock->created_date       = now();
                $stock->save();
            }

            // Accounting Payments
            $partyType = ($sale_type === 'Walk-In') ? 'Walk-In Customer' : 'Customer';

            $lastPayment = Payment::max('code');
            $nextP = $lastPayment ? ((int) preg_replace('/[^0-9]/', '', $lastPayment)) + 1 : 1;
            $code1 = 'VO-' . str_pad($nextP, 6, '0', STR_PAD_LEFT);

            if ($grandTotal > 0) {
                $payment = new Payment();
                $payment->party_id       = $customerId;
                $payment->party_type     = $partyType;
                $payment->code           = $code1;
                $payment->payment_type   = $sale_type;
                $payment->datetime       = $request->sale_date . ' ' . date('H:i:s');
                $payment->debit          = 0;
                $payment->credit         = $grandTotal;
                $payment->sale_id        = $sale->id;
                $payment->payment_method = $request->payment_method ?: 'Cash';
                $payment->status         = 'Active';
                $payment->deleted        = 'No';
                $payment->created_by     = auth()->id();
                $payment->created_datetime = now();
                $payment->save();
            }

            if ($paidAmount > 0) {
                $code2 = 'VO-' . str_pad($nextP + 1, 6, '0', STR_PAD_LEFT);
                $payment2 = new Payment();
                $payment2->party_id       = $customerId;
                $payment2->party_type     = $partyType;
                $payment2->code           = $code2;
                $payment2->payment_type   = $sale_type . ' Payment Receive';
                $payment2->datetime       = $request->sale_date . ' ' . date('H:i:s');
                $payment2->debit          = $paidAmount;
                $payment2->credit         = 0;
                $payment2->sale_id        = $sale->id;
                $payment2->payment_method = $request->payment_method ?: 'Cash';
                $payment2->status         = 'Active';
                $payment2->deleted        = 'No';
                $payment2->created_by     = auth()->id();
                $payment2->created_datetime = now();
                $payment2->save();
            }

            DB::commit();

            return response()->json([
                'status'      => true,
                'message'     => 'Sale completed successfully.',
                'sale_id'     => $sale->id,
                'invoice_url' => route('sales.invoice.pdf', $sale->id)
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Invoice PDF (DOMPDF)
    |--------------------------------------------------------------------------
    */
    public function invoicePdf(Request $request, $id)
    {
        $sale = Sale::where('id', $id)->where('deleted', 'No')->firstOrFail();
        $customer = Party::find($sale->customer_id);

        $items = DB::table('sale_items')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'sale_items.product_variant_id')
            ->leftJoin('attributes', 'attributes.id', '=', 'product_variants.attribute_id')
            ->select(
                'sale_items.*',
                'products.name as product_name',
                'products.sku',
                'products.barcode',
                'product_variants.barcode as variant_barcode',
                'attributes.name as variant_name'
            )
            ->where('sale_items.sale_id', $id)
            ->where('sale_items.deleted', 'No')
            ->get();

        $amountInWords = function_exists('amount_in_words') ? amount_in_words($sale->grand_total) : '';
        $preparedBy = optional(\App\Models\User::find($sale->created_by))->name ?? 'Admin';

        $generalSetting = DB::table('settings')->where('deleted', 'No')->first();

        $pdf = Pdf::loadView(
            'admin.inventory.sale.invoice',
            compact('sale', 'customer', 'items', 'amountInWords', 'preparedBy', 'generalSetting')
        )->setPaper('a4', 'portrait');

        $filename = $sale->invoice_no . '.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Sale
    |--------------------------------------------------------------------------
    */
    public function delete($id)
    {
        DB::beginTransaction();

        try {
            $sale = Sale::where('id', $id)->where('deleted', 'No')->first();
            if (!$sale) {
                return response()->json(['status' => false, 'message' => 'Sale not found'], 404);
            }

            // Check if return exists for this sale
            $hasReturn = SaleReturn::where('sale_id', $id)->where('deleted', 'No')->exists();
            if ($hasReturn) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete sale because it has associated sale returns.'
                ], 422);
            }

            $userId = auth()->id();
            $now = now();

            $sale->deleted = 'Yes';
            $sale->deleted_by = $userId;
            $sale->updated_by = $userId;
            $sale->updated_date = $now;
            $sale->save();

            SaleItem::where('sale_id', $id)->where('deleted', 'No')->update([
                'deleted' => 'Yes',
                'deleted_by' => $userId,
                'updated_by' => $userId,
                'updated_date' => $now
            ]);

            Stock::where('sale_id', $id)->where('deleted', 'No')->update([
                'deleted' => 'Yes',
                'deleted_by' => $userId,
                'updated_by' => $userId,
                'updated_date' => $now
            ]);

            Payment::where('sale_id', $id)->where('deleted', 'No')->update([
                'deleted' => 'Yes',
                'deleted_by' => $userId,
                'updated_by' => $userId,
                'updated_datetime' => $now
            ]);

            DB::commit();

            return response()->json(['status' => true, 'message' => 'Sale deleted successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SALE RETURN: Index & List
    |--------------------------------------------------------------------------
    */
    public function returnIndex(Request $request)
    {
        $sale_type = $request->input('type', 'Walk-In');
        if (!in_array($sale_type, ['Walk-In', 'Wholesale'])) {
            $sale_type = 'Walk-In';
        }
        $party_type = $sale_type . ' Sale Return';

        return view('admin.inventory.sale.return_index', compact('sale_type', 'party_type'));
    }

    public function returnList(Request $request)
    {
        try {
            $sale_type = $request->input('type');

            $query = DB::table('sale_returns')
                ->leftJoin('sales', 'sales.id', '=', 'sale_returns.sale_id')
                ->leftJoin('parties', 'parties.id', '=', 'sale_returns.customer_id')
                ->select(
                    'sale_returns.*',
                    'sales.invoice_no as original_invoice_no',
                    'parties.name as customer_name',
                    'parties.phone as customer_phone'
                )
                ->where('sale_returns.deleted', 'No');

            if (!empty($sale_type)) {
                $query->where('sale_returns.sale_type', $sale_type);
            }

            $returns = $query->orderByDesc('sale_returns.id')->get();

            $html = '';
            foreach ($returns as $index => $row) {
                $statusBadge = $row->status === 'Active'
                    ? '<i class="fas fa-check-circle text-success" style="font-size:18px;"></i>'
                    : '<i class="fas fa-times-circle text-danger" style="font-size:18px;"></i>';

                $invoiceUrl = route('sales.return.invoice.pdf', $row->id);
                $viewUrl    = route('sales.return.view', $row->id);

                $html .= '
                <tr>
                    <td class="text-center">' . ($index + 1) . '</td>
                    <td><strong class="text-warning">' . e($row->return_no) . '</strong></td>
                    <td><strong class="text-primary">' . e($row->original_invoice_no) . '</strong></td>
                    <td>' . date('Y-m-d', strtotime($row->datetime)) . '</td>
                    <td><strong>' . e($row->customer_name ?? 'Walk-In Customer') . '</strong></td>
                    <td class="text-center"><span class="badge bg-secondary">' . e($row->sale_type) . '</span></td>
                    <td class="text-end fw-bold">' . number_format((float) $row->grand_total, 2, '.', '') . '</td>
                    <td class="text-center">' . $statusBadge . '</td>
                    <td class="text-center">
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-cogs text-secondary me-1"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="' . $invoiceUrl . '" target="_blank">
                                        <i class="fa fa-file-pdf text-danger me-2"></i> Credit Note PDF
                                    </a>
                                </li>

                                <li>
                                    <button type="button" class="dropdown-item text-danger deleteSaleReturnBtn" data-id="' . $row->id . '">
                                        <i class="fa fa-trash me-2"></i> Delete
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>';
            }

            return response()->json(['status' => true, 'html' => $html]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SALE RETURN: Create Page & Data
    |--------------------------------------------------------------------------
    */
    public function returnPage($id)
    {
        $sale = Sale::where('id', $id)->where('deleted', 'No')->firstOrFail();
        $customer = Party::find($sale->customer_id);
        $party_type = 'Return for ' . $sale->invoice_no;

        return view('admin.inventory.sale.return_create', compact('sale', 'customer', 'party_type'));
    }

    public function returnData($id)
    {
        try {
            $sale = Sale::where('id', $id)->where('deleted', 'No')->firstOrFail();
            $customer = Party::find($sale->customer_id);

            $items = DB::table('sale_items')
                ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
                ->leftJoin('product_variants', 'product_variants.id', '=', 'sale_items.product_variant_id')
                ->leftJoin('attributes', 'attributes.id', '=', 'product_variants.attribute_id')
                ->select(
                    'sale_items.*',
                    'products.name as product_name',
                    'attributes.name as variant_name'
                )
                ->where('sale_items.sale_id', $id)
                ->where('sale_items.deleted', 'No')
                ->get();

            // replaces the old foreach loop
            $items = $items->map(function ($item) {
                $returned = (float) DB::table('sale_return_items')
                    ->where('sale_item_id', $item->id)
                    ->where('deleted', 'No')
                    ->sum('qty');

                $item->sale_item_id  = $item->id;
                $item->sold_qty      = (float) $item->qty;
                $item->returned_qty  = $returned;
                $item->remaining_qty = max(0, $item->sold_qty - $returned);

                return $item;
            });

            return response()->json([
                'status'   => true,
                'sale'     => $sale,
                'customer' => $customer,
                'items'    => $items->values(),   // values() keeps it a JSON array
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SALE RETURN: Store
    |--------------------------------------------------------------------------
    */
    public function storeReturn(Request $request)
    {
        //return $request;
        $validator = Validator::make($request->all(), [
            'sale_id'         => 'required|integer|exists:sales,id',
            'datetime'        => 'required|date',
            'items'           => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|integer',
            'items.*.qty'     => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        DB::beginTransaction();

        try {
            $sale = Sale::where('id', $request->sale_id)->where('deleted', 'No')->firstOrFail();
            $items = $request->input('items', []);

            $subtotal = 0;
            $itemsToReturn = [];

            foreach ($items as $itemInput) {
                $saleItem = SaleItem::where('id', $itemInput['sale_item_id'])
                    ->where('sale_id', $sale->id)
                    ->where('deleted', 'No')
                    ->firstOrFail();

                $alreadyReturned = DB::table('sale_return_items')
                    ->where('sale_item_id', $saleItem->id)
                    ->where('deleted', 'No')
                    ->sum('qty');

                $maxReturnable = (float)$saleItem->qty - (float)$alreadyReturned;
                $returnQty = (float)$itemInput['qty'];

                if ($returnQty > $maxReturnable) {
                    throw new Exception("Return quantity for product exceeds sold quantity.");
                }

                $effectiveUnitPrice = (float)$saleItem->total / (float)$saleItem->qty;
                $itemTotal = round($returnQty * $effectiveUnitPrice, 2);
                $subtotal += $itemTotal;

                $itemsToReturn[] = [
                    'sale_item'   => $saleItem,
                    'qty'         => $returnQty,
                    'unit_price'  => $effectiveUnitPrice,
                    'total'       => $itemTotal
                ];
            }

            if (empty($itemsToReturn)) {
                throw new Exception("No valid items to return.");
            }

            // Generate Return No: SR-XXXXXX
            $last = SaleReturn::max('return_no');
            $next = $last ? ((int) preg_replace('/[^0-9]/', '', $last)) + 1 : 1;
            $returnNo = 'SR-' . str_pad($next, 6, '0', STR_PAD_LEFT);

            $saleReturn = new SaleReturn();
            $saleReturn->return_no       = $returnNo;
            $saleReturn->sale_id         = $sale->id;
            $saleReturn->customer_id     = $sale->customer_id;
            $saleReturn->sale_type       = $sale->sale_type;
            $saleReturn->datetime        = $request->datetime;
            $saleReturn->subtotal        = $subtotal;
            $saleReturn->grand_total     = $subtotal;
            $saleReturn->remarks         = $request->remarks;
            $saleReturn->deleted         = 'No';
            $saleReturn->status          = 'Active';
            $saleReturn->created_by      = auth()->id();
            $saleReturn->created_date = now();
            $saleReturn->save();

            // Return items & Restock (in_qty)
            foreach ($itemsToReturn as $ret) {
                $sItem = $ret['sale_item'];

                $returnItem = new SaleReturnItem();
                $returnItem->sale_return_id     = $saleReturn->id;
                $returnItem->sale_id            = $sale->id;
                $returnItem->sale_item_id       = $sItem->id;
                $returnItem->product_id         = $sItem->product_id;
                $returnItem->product_variant_id = $sItem->product_variant_id;
                $returnItem->qty                = $ret['qty'];
                $returnItem->sale_price         = $ret['unit_price'];
                $returnItem->subtotal           = $ret['total'];
                $returnItem->total              = $ret['total'];
                $returnItem->deleted            = 'No';
                $returnItem->status             = 'Active';
                $returnItem->created_by         = auth()->id();
                $returnItem->created_date   = now();
                $returnItem->save();

                // Restock inventory (in_qty)
                $stock = new Stock();
                $stock->product_id         = $sItem->product_id;
                $stock->product_variant_id = $sItem->product_variant_id;
                $stock->in_qty             = $ret['qty'];
                $stock->out_qty            = 0;
                $stock->sale_return_id     = $saleReturn->id;
                $stock->date               = date('Y-m-d', strtotime($request->datetime));
                $stock->reference_type     = 'Sale Return';
                $stock->reference_id       = $saleReturn->id;
                $stock->remarks            = 'Return: ' . $returnNo;
                $stock->deleted            = 'No';
                $stock->status             = 'Active';
                $stock->created_by         = auth()->id();
                $stock->created_date       = now();
                $stock->save();
            }

            // Adjust Customer Receivable/Payment
            $lastPayment = Payment::max('code');
            $nextP = $lastPayment ? ((int) preg_replace('/[^0-9]/', '', $lastPayment)) + 1 : 1;
            $code = 'VO-' . str_pad($nextP, 6, '0', STR_PAD_LEFT);

            $payment = new Payment();
            $payment->party_id         = $sale->customer_id;
            $payment->party_type       = ($sale->sale_type === 'Walk-In') ? 'Walk-In Customer' : 'Customer';
            $payment->code             = $code;
            $payment->payment_type     = 'Walk-In Sale Return';
            $payment->datetime         = $request->datetime;
            $payment->debit            = 0;
            $payment->credit           = $subtotal;
            $payment->sale_return_id   = $saleReturn->id;
            $payment->status           = 'Active';
            $payment->deleted          = 'No';
            $payment->created_by       = auth()->id();
            $payment->created_datetime = now();
            $payment->save();

            DB::commit();

            return response()->json([
                'status'     => true,
                'message'    => 'Sale return processed successfully.',
                'return_url' => route('sales.return.invoice.pdf', $saleReturn->id)
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function returnView($id)
    {
        $saleReturn = SaleReturn::where('id', $id)->where('deleted', 'No')->firstOrFail();
        $sale = Sale::find($saleReturn->sale_id);
        $customer = Party::find($saleReturn->customer_id);

        $items = DB::table('sale_return_items')
            ->leftJoin('products', 'products.id', '=', 'sale_return_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'sale_return_items.product_variant_id')
            ->leftJoin('attributes', 'attributes.id', '=', 'product_variants.attribute_id')
            ->select(
                'sale_return_items.*',
                'products.name as product_name',
                'attributes.name as variant_name'
            )
            ->where('sale_return_items.sale_return_id', $id)
            ->where('sale_return_items.deleted', 'No')
            ->get();

        $party_type = 'View Return ' . $saleReturn->return_no;

        return view('admin.inventory.sale.return_view', compact('saleReturn', 'sale', 'customer', 'items', 'party_type'));
    }

    public function returnInvoicePdf(Request $request, $id)
    {
        $saleReturn = SaleReturn::where('id', $id)->where('deleted', 'No')->firstOrFail();
        $sale = Sale::find($saleReturn->sale_id);
        $customer = Party::find($saleReturn->customer_id);

        $items = DB::table('sale_return_items')
            ->leftJoin('products', 'products.id', '=', 'sale_return_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'sale_return_items.product_variant_id')
            ->leftJoin('attributes', 'attributes.id', '=', 'product_variants.attribute_id')
            ->select(
                'sale_return_items.*',
                'products.name as product_name',
                'attributes.name as variant_name'
            )
            ->where('sale_return_items.sale_return_id', $id)
            ->where('sale_return_items.deleted', 'No')
            ->get();

        $amountInWords = function_exists('amount_in_words') ? amount_in_words($saleReturn->grand_total) : '';
        $preparedBy = optional(\App\Models\User::find($saleReturn->created_by))->name ?? 'Admin';
        $generalSetting = DB::table('settings')->where('deleted', 'No')->first();

        $pdf = Pdf::loadView(
            'admin.inventory.sale.return_invoice',
            compact('saleReturn', 'sale', 'customer', 'items', 'amountInWords', 'preparedBy', 'generalSetting')
        )->setPaper('a4', 'portrait');

        $filename = $saleReturn->return_no . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    public function returnDelete($id)
    {
        DB::beginTransaction();

        try {
            $saleReturn = SaleReturn::where('id', $id)->where('deleted', 'No')->firstOrFail();
            $userId = auth()->id();
            $now = now();

            $saleReturn->deleted = 'Yes';
            $saleReturn->deleted_by = $userId;
            $saleReturn->deleted_date = $now;
            $saleReturn->save();

            SaleReturnItem::where('sale_return_id', $id)->update([
                'deleted' => 'Yes',
                'deleted_by' => $userId,
                'deleted_date' => $now
            ]);

            Stock::where('sale_return_id', $id)->update([
                'deleted' => 'Yes',
                'deleted_by' => $userId
            ]);

            Payment::where('sale_return_id', $id)->update([
                'deleted' => 'Yes',
                'deleted_by' => $userId
            ]);

            DB::commit();

            return response()->json(['status' => true, 'message' => 'Sale return deleted successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SALE ORDERS: Index, Create, Store, List, Invoice, Delete
    |--------------------------------------------------------------------------
    */
    public function orderIndex()
    {
        $party_type = 'Sale Orders';

        return view('admin.inventory.sale_order.index', compact('party_type'));
    }

    /*
    |--------------------------------------------------------------------------
    | SALE ORDERS: Create Page
    |--------------------------------------------------------------------------
    */
    public function orderCreate()
    {
        $party_type = 'Create Sale Order';

        $customers = Party::where('deleted', 'No')
            ->where('status', 'Active')
            ->whereIn('type', ['Customer', 'Walk-In Customer'])
            ->orderBy('name')
            ->get();

        $products = Product::where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        return view('admin.inventory.sale_order.create', compact('party_type', 'customers', 'products'));
    }

    /*
    |--------------------------------------------------------------------------
    | SALE ORDERS: AJAX List
    |--------------------------------------------------------------------------
    */
    public function orderList()
    {
        try {
            $orders = DB::table('sale_orders')
                ->leftJoin('parties', 'parties.id', '=', 'sale_orders.customer_id')
                ->select(
                    'sale_orders.*',
                    'parties.name as customer_name',
                    'parties.phone as customer_phone'
                )
                ->where('sale_orders.deleted', 'No')
                ->orderByDesc('sale_orders.id')
                ->get();

            $statusColors = [
                'Pending'   => 'bg-warning text-dark',
                'Partial'   => 'bg-primary text-white',
                'Confirmed' => 'bg-info text-dark',
                'Delivered' => 'bg-success text-white',
                'Cancelled' => 'bg-danger text-white',
            ];

            $html = '';

            foreach ($orders as $index => $row) {

                $color = $statusColors[$row->order_status] ?? 'bg-secondary text-white';
                $badge = '<span class="badge ' . $color . '">' . e($row->order_status) . '</span>';

                $invoiceUrl = route('sales.order.invoice.pdf', $row->id);

                // Only open orders can be delivered or cancelled
                $isOpen = in_array($row->order_status, ['Pending', 'Confirmed', 'Partial']);

                $actions = '
                    <li>
                        <a class="dropdown-item" href="' . $invoiceUrl . '" target="_blank">
                            <i class="fa fa-file-pdf text-danger me-2"></i> Order PDF
                        </a>
                    </li>';

                if ($isOpen) {
                    $actions .= '
                    <li>
                        <a href="' . route('sales.orders.delivery_page', $row->id) . '"
                        class="dropdown-item text-success">
                            <i class="fa fa-truck me-2"></i> Delivery
                        </a>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item text-warning cancelOrderBtn" data-id="' . $row->id . '">
                            <i class="fa fa-ban me-2"></i> Cancel Order
                        </button>
                    </li>';
                }

                // Delivered orders already created a sale, so they cannot be deleted here
                if ($row->order_status !== 'Delivered') {
                    $actions .= '
                    <li>
                        <button type="button" class="dropdown-item text-danger deleteOrderBtn" data-id="' . $row->id . '">
                            <i class="fa fa-trash me-2"></i> Delete
                        </button>
                    </li>';
                }

                $html .= '
                <tr>
                    <td class="text-center">' . ($index + 1) . '</td>
                    <td><strong class="text-primary">' . e($row->order_no) . '</strong></td>
                    <td>' . e($row->order_date) . '</td>
                    <td>' . e($row->delivery_date ?? '-') . '</td>
                    <td>
                        <strong>' . e($row->customer_name ?? '-') . '</strong>' .
                    ($row->customer_phone
                        ? '<br><small class="text-muted">' . e($row->customer_phone) . '</small>'
                        : '') . '
                    </td>
                    <td class="text-end fw-bold">' . number_format((float) $row->grand_total, 2, '.', '') . '</td>
                    <td class="text-end text-success">' . number_format((float) $row->advance_paid, 2, '.', '') . '</td>
                    <td class="text-end ' . ((float) $row->due_amount > 0 ? 'text-danger fw-bold' : '') . '">' . number_format((float) $row->due_amount, 2, '.', '') . '</td>
                    <td class="text-center">' . $badge . '</td>
                    <td class="text-center">
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-cogs text-secondary me-1"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                ' . $actions . '
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
    | SALE ORDERS: Store
    |--------------------------------------------------------------------------
    */
    public function orderStore(Request $request)
    {

        if ($request->filled('items') && is_string($request->input('items'))) {

            $decodedItems = json_decode($request->input('items'), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedItems)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid order items data.'
                ], 422);
            }

            $request->merge(['items' => $decodedItems]);
        }


        $validator = Validator::make($request->all(), [
            'customer_id'    => 'required|integer|exists:parties,id',
            'order_date'     => 'required|date',
            'delivery_date'  => 'nullable|date|after_or_equal:order_date',

            'discount_type'  => 'nullable|in:Percentage,Amount',
            'discount_value' => 'nullable|numeric|min:0',
            'advance_paid'   => 'nullable|numeric|min:0',
            'remarks'        => 'nullable|string',

            'items'                      => 'required|array|min:1',
            'items.*.product_id'         => 'required|integer|exists:products,id',
            'items.*.product_variant_id' => 'nullable|integer',
            'items.*.qty'                => 'required|numeric|min:0.0001',
            'items.*.sale_price'         => 'required|numeric|min:0',
            'items.*.discount_type'      => 'nullable|in:Percentage,Amount',
            'items.*.discount_value'     => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $items    = $request->input('items', []);
            $subtotal = 0;

            // Item totals
            foreach ($items as $item) {
                $qty     = (float) $item['qty'];
                $price   = (float) $item['sale_price'];
                $itemSub = $qty * $price;

                $dType    = $item['discount_type'] ?? 'Amount';
                $dVal     = (float) ($item['discount_value'] ?? 0);
                $itemDisc = ($dType === 'Percentage') ? ($itemSub * $dVal) / 100 : $dVal;

                if ($itemDisc > $itemSub) {
                    $itemDisc = $itemSub;
                }

                $subtotal += ($itemSub - $itemDisc);
            }

            // Header discount
            $discountType   = $request->discount_type ?? 'Amount';
            $discountValue  = (float) ($request->discount_value ?? 0);
            $discountAmount = ($discountType === 'Percentage')
                ? ($subtotal * $discountValue) / 100
                : $discountValue;

            if ($discountAmount > $subtotal) {
                $discountAmount = $subtotal;
            }

            $grandTotal  = $subtotal - $discountAmount;
            $advancePaid = (float) ($request->advance_paid ?? 0);

            if ($advancePaid > $grandTotal) {
                $advancePaid = $grandTotal;
            }

            $dueAmount = max(0, $grandTotal - $advancePaid);

            // Order No: SO-XXXXXX
            $last    = SaleOrder::max('order_no');
            $next    = $last ? ((int) preg_replace('/[^0-9]/', '', $last)) + 1 : 1;
            $orderNo = 'SO-' . str_pad($next, 6, '0', STR_PAD_LEFT);

            // Create order
            $order = new SaleOrder();
            $order->order_no        = $orderNo;
            $order->customer_id     = $request->customer_id;
            $order->order_date      = $request->order_date;
            $order->delivery_date   = $request->delivery_date;
            $order->subtotal        = round($subtotal, 2);
            $order->discount_type   = $discountType;
            $order->discount_value  = round($discountValue, 2);
            $order->discount_amount = round($discountAmount, 2);
            $order->grand_total     = round($grandTotal, 2);
            $order->advance_paid    = round($advancePaid, 2);
            $order->due_amount      = round($dueAmount, 2);
            $order->order_status    = 'Pending';
            $order->remarks         = $request->remarks;
            $order->deleted         = 'No';
            $order->status          = 'Active';
            $order->created_by      = auth()->id();
            $order->created_date    = now();
            $order->save();

            // Order items
            foreach ($items as $item) {
                $qty     = (float) $item['qty'];
                $price   = (float) $item['sale_price'];
                $itemSub = $qty * $price;

                $dType    = $item['discount_type'] ?? 'Amount';
                $dVal     = (float) ($item['discount_value'] ?? 0);
                $itemDisc = ($dType === 'Percentage') ? ($itemSub * $dVal) / 100 : $dVal;

                if ($itemDisc > $itemSub) {
                    $itemDisc = $itemSub;
                }

                $itemTotal = $itemSub - $itemDisc;

                $orderItem = new SaleOrderItem();
                $orderItem->sale_order_id      = $order->id;
                $orderItem->product_id         = $item['product_id'];
                $orderItem->product_variant_id = !empty($item['product_variant_id']) ? $item['product_variant_id'] : null;
                $orderItem->qty                = $qty;
                $orderItem->sale_price         = $price;
                $orderItem->discount_type      = $dType;
                $orderItem->discount_value     = $dVal;
                $orderItem->discount_amount    = round($itemDisc, 2);
                $orderItem->subtotal           = round($itemSub, 2);
                $orderItem->total              = round($itemTotal, 2);
                $orderItem->deleted            = 'No';
                $orderItem->status             = 'Active';
                $orderItem->created_by         = auth()->id();
                $orderItem->created_date       = now();
                $orderItem->save();
            }
            $lastPayment = Payment::max('code');
            $nextP = $lastPayment ? ((int) preg_replace('/[^0-9]/', '', $lastPayment)) + 1 : 1;
            $code1 = 'VO-' . str_pad($nextP, 6, '0', STR_PAD_LEFT);

            if ($grandTotal > 0) {
                $payment = new Payment();
                $payment->party_id       = $request->customer_id;
                $payment->party_type     = 'Customer';
                $payment->code           = $code1;
                $payment->payment_type   = 'Sale Order';
                $payment->datetime       = $request->order_date . ' ' . date('H:i:s');
                $payment->debit          = 0;
                $payment->credit         = $grandTotal;
                $payment->order_id        = $order->id;
                $payment->payment_method = $request->payment_method ?: 'Cash';
                $payment->status         = 'Active';
                $payment->deleted        = 'No';
                $payment->created_by     = auth()->id();
                $payment->created_datetime = now();
                $payment->save();
            }
            $lastPayment2 = Payment::max('code');
            $nextP2 = $lastPayment2 ? ((int) preg_replace('/[^0-9]/', '', $lastPayment2)) + 1 : 1;
            $code2 = 'VO-' . str_pad($nextP2, 6, '0', STR_PAD_LEFT);
            if ($advancePaid > 0) {
                $payment = new Payment();
                $payment->party_id       = $request->customer_id;
                $payment->party_type     = 'Customer';
                $payment->code           = $code2;
                $payment->payment_type   = 'Sale Order';
                $payment->datetime       = $request->order_date . ' ' . date('H:i:s');
                $payment->debit          = $advancePaid;
                $payment->credit         = 0;
                $payment->order_id        = $order->id;
                $payment->payment_method = $request->payment_method ?: 'Cash';
                $payment->status         = 'Active';
                $payment->deleted        = 'No';
                $payment->created_by     = auth()->id();
                $payment->created_datetime = now();
                $payment->save();
            }
            DB::commit();

            return response()->json([
                'status'    => true,
                'message'   => 'Sale order created successfully.',
                'order_id'  => $order->id,
                'order_url' => route('sales.order.invoice.pdf', $order->id)
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SALE ORDERS: PDF
    |--------------------------------------------------------------------------
    */
    public function orderInvoicePdf(Request $request, $id)
    {
        $order    = SaleOrder::where('id', $id)->where('deleted', 'No')->firstOrFail();
        $customer = Party::find($order->customer_id);

        $items = DB::table('sale_order_items')
            ->leftJoin('products', 'products.id', '=', 'sale_order_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'sale_order_items.product_variant_id')
            ->leftJoin('attributes', 'attributes.id', '=', 'product_variants.attribute_id')
            ->select(
                'sale_order_items.*',
                'products.name as product_name',
                'attributes.name as variant_name'
            )
            ->where('sale_order_items.sale_order_id', $id)
            ->where('sale_order_items.deleted', 'No')
            ->get();

        $amountInWords  = function_exists('amount_in_words') ? amount_in_words($order->grand_total) : '';
        $preparedBy     = optional(\App\Models\User::find($order->created_by))->name ?? 'Admin';
        $generalSetting = DB::table('settings')->where('deleted', 'No')->first();

        // NOTE: make sure this view file exists at
        // resources/views/admin/inventory/sale/order_invoice.blade.php
        // (your create/index views are in admin/inventory/sale_order/ - use that path if the PDF view lives there)
        $pdf = Pdf::loadView(
            'admin.inventory.sale_order.invoice',
            compact('order', 'customer', 'items', 'amountInWords', 'preparedBy', 'generalSetting')
        )->setPaper('a4', 'portrait');

        $filename = $order->order_no . '.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /*
    |--------------------------------------------------------------------------
    | SALE ORDERS: Deliver
    |--------------------------------------------------------------------------
    */
    public function orderDeliver(Request $request, $id)
    {
        /*
        |--------------------------------------------------------------------------
        | Decode items JSON
        |--------------------------------------------------------------------------
        */
        if (
            $request->filled('items') &&
            is_string($request->input('items'))
        ) {

            $decodedItems = json_decode(
                $request->input('items'),
                true
            );

            if (
                json_last_error() !== JSON_ERROR_NONE ||
                !is_array($decodedItems)
            ) {

                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid delivery items data.'
                ], 422);
            }

            $request->merge([
                'items' => $decodedItems
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */
        $validator = Validator::make(
            $request->all(),
            [

                'items' =>
                'required|array|min:1',

                'items.*.sale_order_item_id' =>
                'required|integer',

                'items.*.delivery_qty' =>
                'required|numeric|min:0',
            ]
        );


        if ($validator->fails()) {

            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }


        DB::beginTransaction();


        try {

            /*
            |--------------------------------------------------------------------------
            | Lock Sale Order
            |--------------------------------------------------------------------------
            */
            $order = SaleOrder::where('id', $id)
                ->where('deleted', 'No')
                ->lockForUpdate()
                ->first();


            if (!$order) {

                DB::rollBack();

                return response()->json([
                    'status'  => false,
                    'message' => 'Sale order not found.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | Status Check
            |--------------------------------------------------------------------------
            */
            if ($order->order_status === 'Cancelled') {

                DB::rollBack();

                return response()->json([
                    'status'  => false,
                    'message' => 'A cancelled order cannot be delivered.'
                ], 422);
            }


            if ($order->order_status === 'Delivered') {

                DB::rollBack();

                return response()->json([
                    'status'  => false,
                    'message' => 'This order is already fully delivered.'
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | Get Order Items
            |--------------------------------------------------------------------------
            */
            $orderItems = SaleOrderItem::where(
                'sale_order_id',
                $order->id
            )
                ->where('deleted', 'No')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');


            if ($orderItems->isEmpty()) {

                DB::rollBack();

                return response()->json([
                    'status'  => false,
                    'message' => 'This order has no items.'
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | Prepare delivery lines
            |--------------------------------------------------------------------------
            */
            $deliveryLines = [];

            $totalDeliveryValueBeforeHeaderDiscount = 0;

            $hasDelivery = false;


            foreach ($request->items as $deliveryItem) {

                $saleOrderItemId =
                    (int) $deliveryItem['sale_order_item_id'];

                $deliveryQty =
                    (float) $deliveryItem['delivery_qty'];


                /*
                |--------------------------------------------------------------------------
                | Ignore zero quantities
                |--------------------------------------------------------------------------
                */
                if ($deliveryQty <= 0) {
                    continue;
                }


                $item =
                    $orderItems->get(
                        $saleOrderItemId
                    );


                if (!$item) {

                    DB::rollBack();

                    return response()->json([
                        'status'  => false,
                        'message' =>
                        'Invalid Sale Order Item selected.'
                    ], 422);
                }


                /*
                |--------------------------------------------------------------------------
                | Original Ordered Quantity
                |--------------------------------------------------------------------------
                */
                $orderedQty =
                    (float) $item->qty;


                /*
                |--------------------------------------------------------------------------
                | Already Delivered Quantity
                |--------------------------------------------------------------------------
                */
                $alreadyDeliveredQty =
                    (float) ($item->delivery_qty ?? 0);


                /*
                |--------------------------------------------------------------------------
                | Remaining Quantity
                |--------------------------------------------------------------------------
                */
                $remainingQty =
                    $orderedQty -
                    $alreadyDeliveredQty;


                if ($remainingQty < 0) {
                    $remainingQty = 0;
                }


                /*
                |--------------------------------------------------------------------------
                | CRITICAL VALIDATION
                |--------------------------------------------------------------------------
                |
                | New delivery quantity MUST NOT exceed remaining quantity.
                |
                |--------------------------------------------------------------------------
                */
                if ($deliveryQty > $remainingQty) {

                    DB::rollBack();

                    return response()->json([
                        'status'  => false,
                        'message' =>
                        'Delivery quantity for product ID '
                            . $item->product_id
                            . ' cannot be greater than remaining quantity '
                            . $remainingQty
                            . '. Ordered: '
                            . $orderedQty
                            . ', Already Delivered: '
                            . $alreadyDeliveredQty
                            . '.'
                    ], 422);
                }


                /*
                |--------------------------------------------------------------------------
                | Stock Check
                |--------------------------------------------------------------------------
                */
                $stockQuery = DB::table('stocks')
                    ->where(
                        'product_id',
                        $item->product_id
                    )
                    ->where(
                        'deleted',
                        'No'
                    );


                if ($item->product_variant_id) {

                    $stockQuery->where(
                        'product_variant_id',
                        $item->product_variant_id
                    );
                }


                $availableStock = (float) (
                    $stockQuery
                    ->selectRaw(
                        'COALESCE(SUM(in_qty) - SUM(out_qty), 0) as current_stock'
                    )
                    ->value(
                        'current_stock'
                    ) ?? 0
                );


                if ($deliveryQty > $availableStock) {

                    $productName =
                        Product::find(
                            $item->product_id
                        )->name
                        ?? (
                            'Product #' .
                            $item->product_id
                        );


                    DB::rollBack();

                    return response()->json([
                        'status'  => false,
                        'message' =>
                        'Insufficient stock for "'
                            . $productName
                            . '". Available: '
                            . $availableStock
                            . ', required: '
                            . $deliveryQty
                            . '.'
                    ], 422);
                }


                /*
                |--------------------------------------------------------------------------
                | Delivery Line Value
                |--------------------------------------------------------------------------
                */
                $lineSubtotal =
                    $deliveryQty *
                    (float) $item->sale_price;


                /*
                |--------------------------------------------------------------------------
                | Item Discount
                |--------------------------------------------------------------------------
                |
                | Calculate discount based on the quantity being delivered.
                |
                |--------------------------------------------------------------------------
                */
                $discountType =
                    $item->discount_type ?? 'Amount';


                $originalQty =
                    max(
                        (float) $item->qty,
                        0
                    );


                $originalItemSubtotal =
                    $originalQty *
                    (float) $item->sale_price;


                $originalDiscount =
                    (float) (
                        $item->discount_amount ?? 0
                    );


                /*
                |--------------------------------------------------------------------------
                | Allocate original item discount proportionally
                |--------------------------------------------------------------------------
                */
                $deliveryItemDiscount = 0;


                if ($originalItemSubtotal > 0) {

                    $deliveryItemDiscount =
                        $originalDiscount *
                        (
                            $lineSubtotal /
                            $originalItemSubtotal
                        );
                }


                if (
                    $deliveryItemDiscount >
                    $lineSubtotal
                ) {

                    $deliveryItemDiscount =
                        $lineSubtotal;
                }


                $deliveryItemTotal =
                    $lineSubtotal -
                    $deliveryItemDiscount;


                $totalDeliveryValueBeforeHeaderDiscount +=
                    $deliveryItemTotal;


                $hasDelivery = true;


                $deliveryLines[] = [
                    'item' => $item,
                    'delivery_qty' => $deliveryQty,
                    'subtotal' => round(
                        $lineSubtotal,
                        2
                    ),
                    'discount_amount' => round(
                        $deliveryItemDiscount,
                        2
                    ),
                    'total' => round(
                        $deliveryItemTotal,
                        2
                    )
                ];
            }
            DB::commit();
            return response()->json([
                'status'  => true,
                'message' => 'Delivery processed successfully.',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /*
    |--------------------------------------------------------------------------
    | SALE ORDERS: Cancel
    |--------------------------------------------------------------------------
    */
    public function orderCancel(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $order = SaleOrder::where('id', $id)
                ->where('deleted', 'No')
                ->lockForUpdate()
                ->first();

            if (!$order) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Sale order not found.'], 404);
            }

            if ($order->order_status === 'Delivered') {
                DB::rollBack();
                return response()->json([
                    'status'  => false,
                    'message' => 'A delivered order cannot be cancelled. Use Sale Return instead.'
                ], 422);
            }

            if ($order->order_status === 'Cancelled') {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'This order is already cancelled.'], 422);
            }

            $order->order_status = 'Cancelled';

            if ($request->filled('reason')) {
                $order->remarks = trim(($order->remarks ? $order->remarks . "\n" : '') . 'Cancelled: ' . $request->reason);
            }

            $order->updated_by   = auth()->id();
            $order->updated_date = now();
            $order->save();

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Sale order cancelled.'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function orderDeliveryPage($id)
    {
        $order = SaleOrder::where('id', $id)->where('deleted', 'No')->firstOrFail();

        if ($order->order_status === 'Cancelled') {
            abort(422, 'A cancelled order cannot be delivered.');
        }

        if ($order->order_status === 'Delivered') {
            abort(422, 'This order is already fully delivered.');
        }

        $customer = Party::find($order->customer_id);

        $items = SaleOrderItem::where('sale_order_id', $id)
            ->where('deleted', 'No')
            ->get();

        return view('admin.inventory.sale_order.delivery', compact('order', 'items', 'customer'));
    }

    public function orderDelivery(Request $request, $id)
    {
        $saleOrder = SaleOrder::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$saleOrder) {
            return response()->json([
                'status' => false,
                'message' => 'Sale order not found.',
            ], 404);
        }

        if ($saleOrder->order_status == 'Delivered') {
            return response()->json([
                'status' => false,
                'message' => 'This order is already delivered.',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Decode Items
    |--------------------------------------------------------------------------
    */

        $items = json_decode($request->items, true);

        if (!is_array($items)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid order items.',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Update Delivery Quantity
    |--------------------------------------------------------------------------
    */

        foreach ($items as $item) {

            if (
                !isset($item['sale_order_item_id']) ||
                !isset($item['delivery_qty'])
            ) {
                continue;
            }

            $saleOrderItem = SaleOrderItem::where('id', $item['sale_order_item_id'])
                ->where('sale_order_id', $saleOrder->id)
                ->where('deleted', 'No')
                ->first();

            if ($saleOrderItem) {
                $previousDeliveryQty = (float) $saleOrderItem->delivery_qty;
                $saleOrderItem->delivery_qty = $item['delivery_qty'] + $previousDeliveryQty;
                $saleOrderItem->save();
            }

            // Stock Reduction (out_qty)
                $stock = new Stock();
                $stock->product_id         = $saleOrderItem->product_id;
                $stock->product_variant_id =  $saleOrderItem->product_variant_id;
                $stock->in_qty             = 0;
                $stock->out_qty            = $item['delivery_qty'];
                $stock->order_id            = $id;
                $stock->date               = now();
                $stock->reference_type     = 'Order';
                $stock->reference_id       = $id;
                $stock->remarks            = 'Order: ' . $saleOrder->order_no;
                $stock->deleted            = 'No';
                $stock->status             = 'Active';
                $stock->created_by         = auth()->id();
                $stock->created_date       = now();
                $stock->save();
        }

        /*
    |--------------------------------------------------------------------------
    | Determine Order Status
    |--------------------------------------------------------------------------
    */

        $orderItems = SaleOrderItem::where('sale_order_id', $saleOrder->id)
            ->where('deleted', 'No')
            ->get();

        $isPartial = false;

        foreach ($orderItems as $orderItem) {

            if ((float) $orderItem->delivery_qty < (float) $orderItem->qty) {
                $isPartial = true;
                break;
            }
        }

        if ($isPartial) {
            $saleOrder->order_status = 'Partial';
        } else {
            $saleOrder->order_status = 'Delivered';
        }

        $saleOrder->delivery_datetime = now();
        $saleOrder->updated_by = auth()->id();
        $saleOrder->updated_date = date('Y-m-d H:i:s');
        $saleOrder->save();

        return response()->json([
            'status' => true,
            'message' => $saleOrder->order_status == 'Partial'
                ? 'Order marked as partially delivered successfully.'
                : 'Order marked as delivered successfully.',
            'order_status' => $saleOrder->order_status,
        ]);
    }


    public function orderDelete($id)
    {
        DB::beginTransaction();

        try {
            $order = SaleOrder::where('id', $id)->where('deleted', 'No')->first();

            if (!$order) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Sale order not found.'], 404);
            }

            if ($order->order_status === 'Delivered') {
                DB::rollBack();
                return response()->json([
                    'status'  => false,
                    'message' => 'A delivered order cannot be deleted because a sale invoice was created from it.'
                ], 422);
            }

            $userId = auth()->id();

            $order->deleted    = 'Yes';
            $order->status    = 'Inactive';
            $order->deleted_date = now();
            $order->deleted_by = $userId;
            $order->save();

            SaleOrderItem::where('sale_order_id', $id)
                ->where('deleted', 'No')
                ->update([
                    'deleted'    => 'Yes',
                    'status'    => 'Inactive',
                    'deleted_date'    => now(),
                    'deleted_by' => $userId
                ]);
            Payment::where('order_id', $id)
                ->where('deleted', 'No')
                ->update([
                    'deleted'    => 'Yes',
                    'status'    => 'Inactive',
                    'deleted_datetime'    => now(),
                    'deleted_by' => $userId
                ]);
            Stock::where('order_id', $id)
                ->where('deleted', 'No')
                ->update([
                    'deleted'    => 'Yes',
                    'status'    => 'Inactive',
                    'deleted_date'    => now(),
                    'deleted_by' => $userId
                ]);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Sale order deleted successfully.'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }



    private function calculateCustomerDue($customerId): float
    {
        $customer = Party::find($customerId);
        if (!$customer) return 0.0;

        $saleDue = (float) Sale::where('customer_id', $customerId)
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->sum('due_amount');

        $openingDue = ($customer->opening_balance_type === 'Due')
            ? (float) $customer->opening_balance
            : 0.0;

        return $saleDue + $openingDue;
    }

    private function generatePartyCode(string $type): string
    {
        $prefixes = [
            'Customer'         => 'CUS',
            'Walk-In Customer' => 'WIC',
            'Supplier'         => 'SUP',
        ];
        $prefix = $prefixes[$type] ?? 'WIC';
        $next = (Party::max('id') ?? 0) + 1;

        return $prefix . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
