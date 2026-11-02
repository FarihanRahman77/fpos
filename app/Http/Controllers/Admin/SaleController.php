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
use App\Models\Admin\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use PDF;
use Exception;
use App\Traits\PartyDueTrait;
use App\Traits\PaymentTrait;
use App\Traits\SequentialCodeTrait;

class SaleController extends Controller
{
    use PartyDueTrait, PaymentTrait, SequentialCodeTrait;
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
        $coa=ChartOfAccount::where('slug','liquid-asset')->where('deleted','No')->first();
        $childCoas=ChartOfAccount::where('deleted','No')->where('parent_id',$coa->id)->get();
        return view('admin.inventory.sale.create', compact('sale_type', 'party_type', 'customers', 'products','childCoas'));
    }

    /*
    |--------------------------------------------------------------------------
    | POS Page (Walk-In sale with product grid, saved via store())
    |--------------------------------------------------------------------------
    */
    public function pos()
    {
        $stockSubQuery = DB::table('stocks')->where('branch_id', BranchController::activeId())
            ->select('product_id', DB::raw('SUM(in_qty) - SUM(out_qty) as current_stock'))
            ->where('deleted', 'No')
            ->groupBy('product_id');

        $variantSubQuery = DB::table('product_variants')
            ->select('product_id', DB::raw('COUNT(*) as variant_count'))
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->groupBy('product_id');

        $products = DB::table('products as p')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoinSub($stockSubQuery, 'stock_data', function ($join) {
                $join->on('stock_data.product_id', '=', 'p.id');
            })
            ->leftJoinSub($variantSubQuery, 'variant_data', function ($join) {
                $join->on('variant_data.product_id', '=', 'p.id');
            })
            ->select(
                'p.id',
                'p.name',
                'p.sku',
                'p.barcode',
                'p.image',
                'p.sale_price',
                'p.vat_percent',
                'p.category_id',
                'c.name as category_name',
                'p.brand_id',
                'b.name as brand_name',
                DB::raw('COALESCE(stock_data.current_stock, 0) as current_stock'),
                DB::raw('COALESCE(variant_data.variant_count, 0) as variant_count')
            )
            ->where('p.deleted', 'No')
            ->where('p.status', 'Active')
            ->orderBy('p.name')
            ->get()
            ->map(function ($product) {
                $product->image_url     = $product->image ? asset('uploads/products/' . $product->image) : null;
                $product->sale_price    = (float) $product->sale_price;
                $product->current_stock = (float) $product->current_stock;
                $product->variant_count = (int) $product->variant_count;
                return $product;
            });

        $categories = $products->whereNotNull('category_id')
            ->unique('category_id')
            ->map(fn ($p) => ['id' => $p->category_id, 'name' => $p->category_name])
            ->sortBy('name')
            ->values();

        $brands = $products->whereNotNull('brand_id')
            ->unique('brand_id')
            ->map(fn ($p) => ['id' => $p->brand_id, 'name' => $p->brand_name])
            ->sortBy('name')
            ->values();

        // Variant barcode => product lookup for scanner input
        $variantBarcodes = DB::table('product_variants')
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->pluck('product_id', 'barcode');

        $coa = ChartOfAccount::where('slug', 'liquid-asset')->where('deleted', 'No')->first();
        $childCoas = $coa
            ? ChartOfAccount::where('deleted', 'No')->where('parent_id', $coa->id)->get()
            : collect();

        $programs = [
            'vat_enabled'   => CustomerProgramController::on('vat_enabled'),
            'vat_rate'      => (float) CustomerProgramController::setting('vat_rate'),
            'vat_inclusive' => CustomerProgramController::on('vat_inclusive'),
            'loyalty'       => CustomerProgramController::on('loyalty_enabled'),
            'credit_mode'   => CustomerProgramController::setting('credit_mode'),
        ];

        return view('admin.inventory.sale.pos', compact('products', 'categories', 'brands', 'variantBarcodes', 'childCoas', 'programs'));
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

            $query = BranchController::scope(DB::table('sales'), 'sales.branch_id')
                ->leftJoin('parties', 'parties.id', '=', 'sales.customer_id')
                ->leftJoin('chart_of_accounts', 'chart_of_accounts.id', '=', 'sales.payment_method_account')
                ->select(
                    'sales.*',
                    'parties.name as customer_name',
                    'parties.phone as customer_phone',
                    'chart_of_accounts.account_name'
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
                    <td class="text-center">' . e($row->account_name ?? '-') . '</td>
                    <td class="text-center">' . action_dropdown([
                        ['label' => 'Invoice PDF', 'icon' => 'fas fa-file-pdf', 'href' => $invoiceUrl, 'target' => '_blank'],
                        ['label' => 'Sale Return', 'icon' => 'fas fa-undo', 'href' => $returnUrl],
                        ['divider' => true],
                        ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger deleteSaleBtn', 'attrs' => ['data-id' => $row->id]],
                    ]) . '</td>
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

            $customer = $this->customerByPhoneNumber($phone);

            if ($customer) {
                $due = $this->partyDue($customer->id);
                return response()->json([
                    'status'      => true,
                    'found'       => true,
                    'customer'    => $customer,
                    'current_due' => number_format((float) $due, 2, '.', ''),
                    'programs'    => CustomerProgramController::customerInfo((int) $customer->id)
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
                $stock = DB::table('stocks')->where('branch_id', BranchController::activeId())
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
            $baseStock = DB::table('stocks')->where('branch_id', BranchController::activeId())
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
                $stock = DB::table('stocks')->where('branch_id', BranchController::activeId())
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
                'integer',
                'exists:chart_of_accounts,id'
            ],
            'payment_method_account' => [
                'nullable',
                'integer',
                'exists:chart_of_accounts,id'
            ],

            'items' => 'required|array|min:1',

            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('deleted', 'No')
            ],

            'items.*.product_variant_id' => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->where('deleted', 'No')
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

            $rules['customer_id'] = [
                'required', 'integer',
                Rule::exists('parties', 'id')->where('deleted', 'No')->whereIn('type', ['Customer', 'Walk-In Customer']),
            ];
        } else {

            $rules['customer_phone'] =
                'required|string|min:6|max:20';

            $rules['customer_name'] =
                'nullable|string|max:255';
        }

        $validator = Validator::make(
            $request->all(),
            $rules,
            [
                'items.required'         => 'Add at least one product to the sale.',
                'items.*.product_id.exists' => 'A product in the cart no longer exists. Remove it and add it again.',
                'items.*.product_variant_id.exists' => 'A product variant in the cart no longer exists. Remove it and add it again.',
                'items.*.qty.min'        => 'Quantity must be more than zero.',
                'customer_id.required'   => 'Select the customer.',
                'customer_id.exists'     => 'Select a valid customer.',
                'customer_phone.required' => 'Enter the customer phone number.',
            ]
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
                $existingParty = $this->customerByPhoneNumber($phone);

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

            // VAT on the lines after the sale discount: added on top, or already inside the prices.
            $vatLines = [];
            foreach ($items as $i => $item) {
                $lineSub = (float) $item['qty'] * (float) $item['sale_price'];
                $lineDisc = ($item['discount_type'] ?? 'Amount') === 'Percentage' ? $lineSub * (float) ($item['discount_value'] ?? 0) / 100 : (float) ($item['discount_value'] ?? 0);
                $vatLines[$i] = ['product_id' => $item['product_id'], 'total' => $lineSub - min($lineDisc, $lineSub)];
            }
            $vat = CustomerProgramController::vat($vatLines, $discountAmount);
            if (!$vat['inclusive']) {
                $grandTotal += $vat['amount'];
            }

            // Loyalty points redeemed as money off.
            [$pointsUsed, $loyaltyDiscount] = $request->filled('redeem_points')
                ? CustomerProgramController::redeemValue((int) $customerId, (float) $request->input('redeem_points'), $grandTotal)
                : [0.0, 0.0];
            $grandTotal -= $loyaltyDiscount;

            $paidAmount = (float) ($request->paid_amount ?? 0);
            if ($paidAmount > $grandTotal) $paidAmount = $grandTotal;
            $dueAmount = $grandTotal - $paidAmount;

            // Credit limit: block (or only warn, per Customer Programs settings).
            if ($creditProblem = CustomerProgramController::creditCheck((int) $customerId, $dueAmount)) {
                if (CustomerProgramController::setting('credit_mode') === 'block') {
                    DB::rollBack();

                    return response()->json(['status' => false, 'message' => $creditProblem . ' Collect more payment or raise the limit.'], 422);
                }
            }

            // Generate Invoice No: INV-XXXXXX
            $invoiceNo = $this->nextSequentialCode(Sale::class, 'invoice_no', 'INV');

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
            $sale->vat_amount       = $vat['amount'];
            $sale->vat_inclusive    = $vat['inclusive'] ? 'Yes' : 'No';
            $sale->loyalty_points_redeemed = $pointsUsed;
            $sale->loyalty_discount = $loyaltyDiscount;
            $sale->grand_total      = round($grandTotal, 2);
            $sale->paid_amount      = round($paidAmount, 2);
            $sale->due_amount       = round($dueAmount, 2);
            $sale->payment_method   = $request->payment_method ?: null;
            $sale->payment_method_account   = $request->payment_method_account ?: null;
            $sale->remarks          = $request->remarks;
            $sale->deleted          = 'No';
            $sale->status           = 'Active';
            $sale->created_by       = auth()->id();
            $sale->created_date     = now();
            $sale->save();

            // Save Sale Items & Deduct Stock
            $purchasePrices = Product::whereIn('id', array_column($items, 'product_id'))->pluck('purchase_price', 'id');

            foreach ($items as $lineIndex => $item) {
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
                $saleItem->purchase_price     = (float) ($purchasePrices[$item['product_id']] ?? 0);
                $saleItem->discount_type      = $dType;
                $saleItem->discount_value     = $dVal;
                $saleItem->discount_amount    = $itemDisc;
                $saleItem->subtotal           = $itemSub;
                $saleItem->total              = $itemTotal;
                $saleItem->vat_percent        = $vat['lines'][$lineIndex]['rate'] ?? 0;
                $saleItem->vat_amount         = $vat['lines'][$lineIndex]['vat'] ?? 0;
                $saleItem->deleted            = 'No';
                $saleItem->status             = 'Active';
                $saleItem->created_by         = auth()->id();
                $saleItem->created_date       = now();
                $saleItem->save();

                // Stock Reduction (out_qty) — batch-tracked products leave earliest expiry first
                foreach (StockController::allocate((int) $item['product_id'], $saleItem->product_variant_id ? (int) $saleItem->product_variant_id : null,
                    (int) BranchController::activeId(), (float) $qty) as [$batchId, $part]) {
                    $stock = new Stock();
                    $stock->product_id         = $item['product_id'];
                    $stock->product_variant_id = $saleItem->product_variant_id;
                    $stock->batch_id           = $batchId;
                    $stock->in_qty             = 0;
                    $stock->out_qty            = $part;
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
            }

            // Accounting Payments using PaymentTrait
            $partyType = ($sale_type === 'Walk-In') ? 'Walk-In Customer' : 'Customer';
            $paymentDateTime = $request->sale_date . ' ' . date('H:i:s');
            $paymentMethod = $request->payment_method ?: 'Cash';

            if ($grandTotal > 0) {
                $this->storeCreditPayment([
                    'party_id'       => $customerId,
                    'party_type'     => $partyType,
                    'payment_type'   => $sale_type,
                    'datetime'       => $paymentDateTime,
                    'credit'         => $grandTotal,
                    'sale_id'        => $sale->id,
                    'payment_method' => '',
                    'payment_method_account' => '',
                ]);
            }

            if ($paidAmount > 0) {
                $this->storeDebitPayment([
                    'party_id'       => $customerId,
                    'party_type'     => $partyType,
                    'payment_type'   => $sale_type . ' Payment Receive',
                    'datetime'       => $paymentDateTime,
                    'debit'          => $paidAmount,
                    'sale_id'        => $sale->id,
                    'payment_method' => $request->payment_method,
                    'payment_method_account' => $request->payment_method_account,
                ]);
            }

            if ($pointsUsed > 0) {
                CustomerProgramController::addPoints((int) $customerId, -$pointsUsed, 'Redeem', $sale->id, $loyaltyDiscount, 'Redeemed on ' . $invoiceNo);
            }
            $earned = CustomerProgramController::earnPoints($grandTotal);
            if ($earned > 0) {
                CustomerProgramController::addPoints((int) $customerId, $earned, 'Earn', $sale->id, $grandTotal, 'Earned on ' . $invoiceNo);
                $sale->loyalty_points_earned = $earned;
                $sale->save();
            }

            DB::commit();

            // Invoice SMS (never blocks the sale).
            $smsNote = null;
            if (SmsController::enabled('invoice_sms')) {
                $party = Party::find($customerId);
                if ($party && $party->phone) {
                    $sms = SmsController::send($party->phone, SmsController::render('invoice_sms', [
                        'customer' => $party->name, 'invoice' => $invoiceNo, 'amount' => $grandTotal, 'paid' => $paidAmount, 'due' => $dueAmount,
                    ]), 'Invoice', (int) $customerId);
                    $smsNote = $sms['ok'] ? 'SMS sent.' : 'SMS not sent: ' . $sms['message'];
                }
            }

            return response()->json([
                'status'      => true,
                'message'     => trim('Sale completed successfully. ' . ($earned > 0 ? number_format($earned, 0) . ' loyalty points earned. ' : '')
                    . ($creditProblem ? 'Warning: ' . $creditProblem . ' ' : '') . ($smsNote ?? '')),
                'sale_id'     => $sale->id,
                'invoice_url' => route('sales.invoice.pdf', $sale->id)
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getStatusCode() : 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Invoice PDF (DOMPDF)
    |--------------------------------------------------------------------------
    */
    public function invoicePdf(Request $request, $id)
    {
        $sale = $this->branchSale($id);
        abort_unless($sale, 404, 'Sale not found in this branch.');
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
            $sale = $this->branchSale($id);
            if (!$sale) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Sale not found in this branch.'], 404);
            }

            // A sale with returns cannot be deleted (delete the returns first).
            if (SaleReturn::where('sale_id', $id)->where('deleted', 'No')->exists()) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete this sale because it has sale returns. Delete the returns first.'
                ], 422);
            }

            CustomerProgramController::reverseSale((int) $id);

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

            $this->deletePaymentsBy('sale_id', $id);

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

            $query = BranchController::scope(DB::table('sale_returns'), 'sale_returns.branch_id')
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
                    <td class="text-center">' . action_dropdown([
                        ['label' => 'Credit Note PDF', 'icon' => 'fas fa-file-pdf', 'href' => $invoiceUrl, 'target' => '_blank'],
                        ['divider' => true],
                        ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger deleteSaleReturnBtn', 'attrs' => ['data-id' => $row->id]],
                    ]) . '</td>
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
        $sale = $this->branchSale($id);
        abort_unless($sale, 404, 'Sale not found in this branch.');
        $customer = Party::find($sale->customer_id);
        $party_type = 'Return for ' . $sale->invoice_no;

        return view('admin.inventory.sale.return_create', compact('sale', 'customer', 'party_type'));
    }

    public function returnData($id)
    {
        try {
            $sale = $this->branchSale($id);
            if (!$sale) {
                return response()->json(['status' => false, 'message' => 'Sale not found in this branch.'], 404);
            }
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
            $items = $items->map(function ($item) use ($sale) {
                // Refund per unit (after line + sale discount, VAT and loyalty) — what storeReturn credits.
                $item->refund_price = round(self::returnUnitValue($sale, $item), 4);

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
            $sale = BranchController::scope(Sale::where('id', $request->sale_id)->where('deleted', 'No'), 'branch_id')->lockForUpdate()->first();
            abort_unless($sale, 404, 'Sale not found in this branch.');
            $items = $request->input('items', []);

            $subtotal = 0;
            $itemsToReturn = [];
            $requested = [];   // per sale line, so the same line sent twice cannot exceed what was sold

            foreach ($items as $itemInput) {
                $saleItem = SaleItem::where('id', $itemInput['sale_item_id'])
                    ->where('sale_id', $sale->id)
                    ->where('deleted', 'No')
                    ->first();
                abort_unless($saleItem, 422, 'A returned item does not belong to this sale.');

                $alreadyReturned = DB::table('sale_return_items')
                    ->where('sale_item_id', $saleItem->id)
                    ->where('deleted', 'No')
                    ->sum('qty');

                $maxReturnable = (float)$saleItem->qty - (float)$alreadyReturned;
                $returnQty = (float)$itemInput['qty'];
                $requested[$saleItem->id] = ($requested[$saleItem->id] ?? 0) + $returnQty;

                if ($requested[$saleItem->id] > $maxReturnable + 0.0005) {
                    $name = Product::where('id', $saleItem->product_id)->value('name') ?? 'a product';
                    abort(422, 'Return quantity for "' . $name . '" is more than what is left to return (' . round(max(0, $maxReturnable), 3) . ').');
                }

                $effectiveUnitPrice = self::returnUnitValue($sale, $saleItem);
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
            $returnNo = $this->nextSequentialCode(SaleReturn::class, 'return_no', 'SR');

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

                // Restock inventory (in_qty) — back into the batch the goods were sold from (latest expiry first)
                // Columns qualified: product_batches also has product_id / product_variant_id / deleted.
                $batchId = Stock::where('stocks.sale_id', $sale->id)->where('stocks.product_id', $sItem->product_id)
                    ->where('stocks.product_variant_id', $sItem->product_variant_id)->where('stocks.deleted', 'No')->whereNotNull('stocks.batch_id')
                    ->join('product_batches as pb', 'pb.id', '=', 'stocks.batch_id')
                    ->orderByDesc('pb.expiry_date')->value('stocks.batch_id');
                $stock = new Stock();
                $stock->product_id         = $sItem->product_id;
                $stock->product_variant_id = $sItem->product_variant_id;
                $stock->batch_id           = $batchId;
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

            // Adjust Customer Balance via PaymentTrait
            if ($subtotal > 0) {
                $partyType = ($sale->sale_type === 'Walk-In') ? 'Walk-In Customer' : 'Customer';
                // Must match the payments.payment_type enum values.
                $returnPaymentType = ($sale->sale_type === 'Walk-In') ? 'Walk-In Sale Return' : 'Wholesale Return';

                $this->storeDebitPayment([
                    'party_id'       => $sale->customer_id,
                    'party_type'     => $partyType,
                    'payment_type'   => $returnPaymentType,
                    'datetime'       => $request->datetime ?: (date('Y-m-d H:i:s')),
                    'debit'          => $subtotal,
                    'sale_return_id' => $saleReturn->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'status'     => true,
                'message'    => 'Sale return processed successfully.',
                'return_url' => route('sales.return.invoice.pdf', $saleReturn->id)
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()],
                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getStatusCode() : 500);
        }
    }

    public function returnView($id)
    {
        $saleReturn = $this->branchSaleReturn($id);
        abort_unless($saleReturn, 404, 'Sale return not found in this branch.');
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
        $saleReturn = $this->branchSaleReturn($id);
        abort_unless($saleReturn, 404, 'Sale return not found in this branch.');
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
            $saleReturn = $this->branchSaleReturn($id);
            if (!$saleReturn) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Sale return not found in this branch.'], 404);
            }
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

            $this->deletePaymentsBy('sale_return_id', $id);

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
        $coa=ChartOfAccount::where('slug','liquid-asset')->where('deleted','No')->first();
        $childCoas=ChartOfAccount::where('deleted','No')->where('parent_id',$coa->id)->get();
        return view('admin.inventory.sale_order.create', compact('party_type', 'customers', 'products','childCoas'));
    }

    /*
    |--------------------------------------------------------------------------
    | SALE ORDERS: AJAX List
    |--------------------------------------------------------------------------
    */
    public function orderList()
    {
        try {
            $orders = BranchController::scope(DB::table('sale_orders'), 'sale_orders.branch_id')
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

                $actions = [
                    ['label' => 'Order PDF', 'icon' => 'fas fa-file-pdf', 'href' => $invoiceUrl, 'target' => '_blank'],
                ];

                if ($isOpen) {
                    $actions[] = ['label' => 'Delivery', 'icon' => 'fas fa-truck', 'class' => 'text-success', 'href' => route('sales.orders.delivery_page', $row->id)];
                    $actions[] = ['label' => 'Cancel Order', 'icon' => 'fas fa-ban', 'class' => 'text-warning cancelOrderBtn', 'attrs' => ['data-id' => $row->id]];
                }

                // Delivered orders already created a sale, so they cannot be deleted here
                if ($row->order_status !== 'Delivered') {
                    $actions[] = ['divider' => true];
                    $actions[] = ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger deleteOrderBtn', 'attrs' => ['data-id' => $row->id]];
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
                    <td class="text-center">' . action_dropdown($actions) . '</td>
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
            'customer_id'    => ['required', 'integer', Rule::exists('parties', 'id')->where('deleted', 'No')->whereIn('type', ['Customer', 'Walk-In Customer'])],
            'order_date'     => 'required|date',
            'delivery_date'  => 'nullable|date|after_or_equal:order_date',

            'discount_type'  => 'nullable|in:Percentage,Amount',
            'discount_value' => 'nullable|numeric|min:0',
            'advance_paid'   => 'nullable|numeric|min:0',
            'remarks'        => 'nullable|string',

            'payment_method' => 'nullable|numeric',
            'payment_method_account' => 'nullable|numeric',

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
            $orderNo = $this->nextSequentialCode(SaleOrder::class, 'order_no', 'SO');

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
            $paymentDateTime = $request->order_date . ' ' . date('H:i:s');
            $paymentMethod = $request->payment_method ?: '';
            $paymentMethodAccount = $request->payment_method_account ?: '';

            if ($grandTotal > 0) {
                $this->storeCreditPayment([
                    'party_id'       => $request->customer_id,
                    'party_type'     => 'Customer',
                    'payment_type'   => 'Sale Order',
                    'datetime'       => $paymentDateTime,
                    'credit'         => $grandTotal,
                    'order_id'       => $order->id,
                    'payment_method' => null,
                    'payment_method_account' => null,
                ]);
            }

            if ($advancePaid > 0) {
                $this->storeDebitPayment([
                    'party_id'       => $request->customer_id,
                    'party_type'     => 'Customer',
                    'payment_type'   => 'Sale Order Payment Receive',
                    'datetime'       => $paymentDateTime,
                    'debit'          => $advancePaid,
                    'order_id'       => $order->id,
                    'payment_method' => $paymentMethod,
                    'payment_method_account' => $paymentMethodAccount,
                ]);
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
        $order    = $this->branchOrderQuery($id)->first();
        abort_unless($order, 404, 'Sale order not found in this branch.');
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
        // Older route name; the delivery page posts to orderDelivery().
        return $this->orderDelivery($request, $id);
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
            $order = $this->branchOrderQuery($id)
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

            // The order charged the customer its full total when it was placed. After cancelling they owe only
            // for what was delivered (nothing, or the delivered part of a partial order). Any advance stays
            // on the customer's account as a credit.
            $delivered = 0.0;
            foreach (SaleOrderItem::where('sale_order_id', $order->id)->where('deleted', 'No')->get() as $line) {
                if ((float) $line->qty > 0) {
                    $delivered += (float) $line->total * min(1, (float) $line->delivery_qty / (float) $line->qty);
                }
            }
            if ((float) $order->subtotal > 0) {
                $delivered *= (float) $order->grand_total / (float) $order->subtotal;
            }
            $delivered = round($delivered, 2);

            $charges = Payment::where('order_id', $order->id)->where('payment_type', 'Sale Order')
                ->where('deleted', 'No')->orderBy('id')->get();
            foreach ($charges as $i => $charge) {
                if ($i === 0 && $delivered > 0) {
                    $charge->credit = $delivered;
                    $charge->remarks = trim(($charge->remarks ? $charge->remarks . ' ' : '') . '(cancelled: delivered part only)');
                    $charge->save();
                } else {
                    $this->deletePaymentsBy('id', $charge->id);
                }
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Sale order cancelled.' . ((float) $order->advance_paid > 0
                    ? ' The advance of ' . number_format((float) $order->advance_paid, 2) . ' stays on the customer\'s account as credit.'
                    : '')
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
        $order = $this->branchOrderQuery($id)->first();
        abort_unless($order, 404, 'Sale order not found in this branch.');

        if (in_array($order->order_status, ['Cancelled', 'Delivered'], true)) {
            return redirect()->route('sales.orders.index')->with('warning', $order->order_status === 'Cancelled'
                ? "Order {$order->order_no} is cancelled, so it cannot be delivered."
                : "Order {$order->order_no} is already fully delivered.");
        }

        $customer = Party::find($order->customer_id);

        $items = SaleOrderItem::where('sale_order_id', $id)
            ->where('deleted', 'No')
            ->get();

        return view('admin.inventory.sale_order.delivery', compact('order', 'items', 'customer'));
    }

    /**
     * Deliver (part of) a sale order: checks each quantity against what is still to deliver and the branch's
     * stock, moves the goods out of stock and sets the order Partial / Delivered — all in one transaction.
     */
    public function orderDelivery(Request $request, $id)
    {
        $items = $request->input('items');
        if (is_string($items)) {
            $items = json_decode($items, true);
        }
        if (!is_array($items) || !$items) {
            return response()->json(['status' => false, 'message' => 'Enter the quantity to deliver.'], 422);
        }

        DB::beginTransaction();

        try {
            $saleOrder = $this->branchOrderQuery($id)->lockForUpdate()->first();
            abort_unless($saleOrder, 404, 'Sale order not found in this branch.');
            abort_if($saleOrder->order_status === 'Cancelled', 422, 'A cancelled order cannot be delivered.');
            abort_if($saleOrder->order_status === 'Delivered', 422, 'This order is already delivered.');

            $orderItems = SaleOrderItem::where('sale_order_id', $saleOrder->id)->where('deleted', 'No')
                ->lockForUpdate()->get()->keyBy('id');

            // Sum per order line (the same line sent twice must still fit the remaining quantity).
            $wanted = [];
            foreach ($items as $item) {
                if (!is_array($item) || !isset($item['sale_order_item_id'], $item['delivery_qty'])) {
                    continue;
                }
                abort_unless(is_numeric($item['delivery_qty']), 422, 'Delivery quantity must be a number.');
                $qty = (float) $item['delivery_qty'];
                abort_if($qty < 0, 422, 'Delivery quantity cannot be negative.');
                if ($qty <= 0) {
                    continue;
                }
                $lineId = (int) $item['sale_order_item_id'];
                abort_unless($orderItems->has($lineId), 422, 'An item does not belong to this order.');
                $wanted[$lineId] = ($wanted[$lineId] ?? 0) + $qty;
            }
            abort_if(!$wanted, 422, 'Enter a delivery quantity for at least one item.');

            $branchId = (int) BranchController::activeId();
            $today = now()->toDateString();

            foreach ($wanted as $lineId => $qty) {
                $orderItem = $orderItems[$lineId];
                $name = Product::where('id', $orderItem->product_id)->value('name') ?? ('Product #' . $orderItem->product_id);
                $variantId = $orderItem->product_variant_id ? (int) $orderItem->product_variant_id : null;

                $remaining = max(0, (float) $orderItem->qty - (float) $orderItem->delivery_qty);
                abort_if($qty > $remaining + 0.0005, 422, 'Delivery quantity for "' . $name . '" is more than the remaining ' . round($remaining, 3) . '.');

                $available = StockController::onHand((int) $orderItem->product_id, $variantId, $branchId);
                abort_if($qty > $available + 0.0005, 422, 'Not enough stock for "' . $name . '". Available: ' . round($available, 3) . ', required: ' . round($qty, 3) . '.');

                $orderItem->delivery_qty = (float) $orderItem->delivery_qty + $qty;
                $orderItem->save();

                // Stock Reduction (out_qty) — batch-tracked products leave earliest expiry first
                foreach (StockController::allocate((int) $orderItem->product_id, $variantId, $branchId, $qty) as [$batchId, $part]) {
                    $stock = new Stock();
                    $stock->product_id         = $orderItem->product_id;
                    $stock->product_variant_id = $orderItem->product_variant_id;
                    $stock->batch_id           = $batchId;
                    $stock->in_qty             = 0;
                    $stock->out_qty            = $part;
                    $stock->order_id           = $saleOrder->id;
                    $stock->date               = $today;
                    $stock->reference_type     = 'Order';
                    $stock->reference_id       = $saleOrder->id;
                    $stock->remarks            = 'Order: ' . $saleOrder->order_no;
                    $stock->deleted            = 'No';
                    $stock->status             = 'Active';
                    $stock->created_by         = auth()->id();
                    $stock->created_date       = now();
                    $stock->save();
                }
            }

            $isPartial = $orderItems->contains(fn ($l) => (float) $l->delivery_qty + 0.0005 < (float) $l->qty);

            $saleOrder->order_status      = $isPartial ? 'Partial' : 'Delivered';
            $saleOrder->delivery_datetime = now();
            $saleOrder->updated_by        = auth()->id();
            $saleOrder->updated_date      = now();
            $saleOrder->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => $isPartial
                    ? 'Order marked as partially delivered successfully.'
                    : 'Order marked as delivered successfully.',
                'order_status' => $saleOrder->order_status,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['status' => false, 'message' => $e->getMessage()],
                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getStatusCode() : 500);
        }
    }


    public function orderDelete($id)
    {
        DB::beginTransaction();

        try {
            $order = $this->branchOrderQuery($id)->lockForUpdate()->first();

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
            $this->deletePaymentsBy('order_id', $id);
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



    /**
     * Customer (or walk-in customer) with this phone. Exact match first; the "ends with" match is used only for a
     * full-length number (e.g. +8801711... vs 01711...), so a short number never picks some other customer
     * whose phone merely ends with the same digits. Suppliers are never matched.
     */
    private function customerByPhoneNumber(string $phone): ?Party
    {
        $phone = trim($phone);
        $base = Party::where('deleted', 'No')->whereIn('type', ['Customer', 'Walk-In Customer']);

        $exact = (clone $base)->where('phone', $phone)->orderBy('id')->first();
        if ($exact || strlen($phone) < 11) {
            return $exact;
        }

        return (clone $base)->where('phone', 'like', '%' . substr($phone, -11))->orderBy('id')->first();
    }

    /** A sale of the logged-in branch (null if missing, deleted or another branch's). */
    private function branchSale($id): ?Sale
    {
        return BranchController::scope(Sale::where('id', $id)->where('deleted', 'No'), 'branch_id')->first();
    }

    private function branchSaleReturn($id): ?SaleReturn
    {
        return BranchController::scope(SaleReturn::where('id', $id)->where('deleted', 'No'), 'branch_id')->first();
    }

    private function branchOrderQuery($id)
    {
        return BranchController::scope(SaleOrder::where('id', $id)->where('deleted', 'No'), 'branch_id');
    }

    /**
     * What one returned unit is worth to the customer: the line total after its own discount, less its share of
     * the sale discount, plus its VAT when VAT was added on top, less its share of the loyalty discount.
     * So returning everything credits exactly the sale's grand total.
     */
    private static function returnUnitValue(object $sale, object $item): float
    {
        $qty = (float) $item->qty;
        if ($qty <= 0) {
            return 0.0;
        }
        $subtotal = (float) $sale->subtotal;
        $line = (float) $item->total;
        if ($subtotal > 0) {
            $line -= $line / $subtotal * (float) $sale->discount_amount;
        }
        if (($sale->vat_inclusive ?? 'No') !== 'Yes') {
            $line += (float) ($item->vat_amount ?? 0);
        }
        $beforeLoyalty = (float) $sale->grand_total + (float) ($sale->loyalty_discount ?? 0);
        if ($beforeLoyalty > 0 && (float) ($sale->loyalty_discount ?? 0) > 0) {
            $line *= (float) $sale->grand_total / $beforeLoyalty;
        }

        return max(0.0, $line / $qty);
    }

    private function generatePartyCode(string $type): string
    {
        $prefixes = [
            'Customer'         => 'CUS',
            'Walk-In Customer' => 'WIC',
            'Supplier'         => 'SUP',
        ];
        $prefix = $prefixes[$type] ?? 'WIC';
        $next = (Party::lockForUpdate()->max('id') ?? 0) + 1;

        return $prefix . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
