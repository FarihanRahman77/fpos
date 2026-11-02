<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Setting;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use PDF;

/**
 * Sale quotations: priced product offers sent to customers.
 *
 * - A customer can have any number of quotations.
 * - Every save after the first is a re-price: the old version is kept as a revision
 *   (sale_quotation_revisions), so each quotation has a visual price history.
 * - Everything that happens to a quotation is written to sale_quotation_logs, which also
 *   makes the per-customer quotation log.
 */
class SaleQuotationController extends Controller
{
    use VoucherHelperTrait;

    public const STATUSES = ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'];
    private const CUSTOMER_TYPES = ['Customer', 'Walk-In Customer'];

    /* ------------------------------------------------------------------ */
    /*  List                                                              */
    /* ------------------------------------------------------------------ */

    public function index(Request $request)
    {
        $selected = $request->filled('customer_id')
            ? DB::table('parties')->where('id', (int) $request->query('customer_id'))->first(['id', 'name', 'code'])
            : null;

        return view('admin.sales.quotations.index', [
            'statuses' => self::STATUSES,
            'selected' => $selected,
        ]);
    }

    public function list(Request $request)
    {
        try {
            $this->expireOld();

            $rows = BranchController::scope(DB::table('sale_quotations as q'), 'q.branch_id')
                ->join('parties as c', 'c.id', '=', 'q.customer_id')
                ->where('q.deleted', 'No')
                ->when($request->filled('customer_id'), fn ($x) => $x->where('q.customer_id', (int) $request->query('customer_id')))
                ->when(in_array($request->query('quotation_status'), self::STATUSES, true), fn ($x) => $x->where('q.quotation_status', $request->query('quotation_status')))
                ->when($request->filled('from'), fn ($x) => $x->where('q.quotation_date', '>=', $request->query('from')))
                ->when($request->filled('to'), fn ($x) => $x->where('q.quotation_date', '<=', $request->query('to')))
                ->orderByDesc('q.quotation_date')->orderByDesc('q.id')
                ->get(['q.*', 'c.name as customer_name', 'c.code as customer_code', 'c.phone as customer_phone']);

            $items = DB::table('sale_quotation_items')->whereIn('sale_quotation_id', $rows->pluck('id')->all() ?: [0])
                ->groupBy('sale_quotation_id')->selectRaw('sale_quotation_id, COUNT(*) c')->pluck('c', 'sale_quotation_id');

            $data = $rows->values()->map(function ($q, $i) use ($items) {
                $id = (int) $q->id;
                $actions = [
                    ['label' => 'View & History', 'icon' => 'fas fa-eye', 'href' => route('admin.quotations.show', $id)],
                    ['label' => 'Re-price / Edit', 'icon' => 'fas fa-tags', 'href' => route('admin.quotations.edit', $id)],
                    ['label' => 'PDF', 'icon' => 'fas fa-file-pdf', 'href' => route('admin.quotations.pdf', $id), 'target' => '_blank'],
                    ['label' => 'Send by Email', 'icon' => 'fas fa-paper-plane', 'onclick' => "Quote.send($id)"],
                    ['label' => 'Share on WhatsApp', 'icon' => 'fab fa-whatsapp', 'onclick' => "Quote.share($id)"],
                    ['label' => 'Duplicate', 'icon' => 'fas fa-copy', 'onclick' => "Quote.duplicate($id)"],
                    ['label' => 'Customer Quotation Log', 'icon' => 'fas fa-stream', 'href' => route('admin.quotations.customer', $q->customer_id)],
                    ['divider' => true],
                    ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => "Quote.remove($id)"],
                ];

                return [
                    $i + 1,
                    '<a href="' . route('admin.quotations.show', $id) . '" class="fw-semibold">' . e($q->quotation_no) . '</a>'
                        . ($q->revision_no > 1 ? ' <span class="badge bg-primary-subtle text-primary-emphasis" title="Re-priced ' . ($q->revision_no - 1) . ' time(s)">Rev ' . (int) $q->revision_no . '</span>' : '')
                        . ($q->title ? '<div class="text-muted small">' . e($q->title) . '</div>' : ''),
                    '<a href="' . route('admin.quotations.customer', $q->customer_id) . '" class="fw-semibold">' . e($q->customer_name) . '</a>'
                        . '<div class="text-muted small">' . e($q->customer_code ?: '') . ($q->customer_phone ? ' · ' . e($q->customer_phone) : '') . '</div>',
                    '<span class="d-none">' . $q->quotation_date . '</span>' . e(Carbon::parse($q->quotation_date)->format('d M Y'))
                        . ($q->valid_until ? '<div class="text-muted small">valid till ' . e(Carbon::parse($q->valid_until)->format('d M Y')) . '</div>' : ''),
                    '<span class="d-block text-center">' . (int) ($items[$id] ?? 0) . '</span>',
                    '<span class="d-block text-end fw-semibold">' . number_format((float) $q->grand_total, 2) . '</span>',
                    self::statusBadge($q->quotation_status) . ($q->sent_count ? '<div class="text-muted small">sent ' . (int) $q->sent_count . '×</div>' : ''),
                    action_dropdown($actions),
                ];
            });

            $all = DB::table('sale_quotations')->where('deleted', 'No');

            return response()->json([
                'data'    => $data,
                'summary' => [
                    'total'    => (clone $all)->count(),
                    'open'     => (clone $all)->whereIn('quotation_status', ['Draft', 'Sent'])->count(),
                    'accepted' => (clone $all)->where('quotation_status', 'Accepted')->count(),
                    'value'    => number_format((float) (clone $all)->whereIn('quotation_status', ['Draft', 'Sent'])->sum('grand_total'), 2),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Quotation list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load quotations.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Create / re-price                                                 */
    /* ------------------------------------------------------------------ */

    public function create(Request $request)
    {
        $setting = Setting::where('deleted', 'No')->first();

        return view('admin.sales.quotations.form', [
            'quotation' => null,
            'items'     => collect(),
            'customer'  => $request->filled('customer_id')
                ? DB::table('parties')->where('id', (int) $request->query('customer_id'))->first(['id', 'name', 'code', 'phone'])
                : null,
            'defaults'  => [
                'tax_percent' => (float) ($setting->tax_rate ?? 0),
                'terms'       => $setting->terms_conditions ?? '',
                'valid_days'  => 15,
            ],
        ]);
    }

    public function edit(int $id)
    {
        $quotation = $this->findQuotation($id);
        abort_unless($quotation, 404, 'Quotation not found.');

        return view('admin.sales.quotations.form', [
            'quotation' => $quotation,
            'items'     => $this->itemsOf($id),
            'customer'  => DB::table('parties')->where('id', $quotation->customer_id)->first(['id', 'name', 'code', 'phone']),
            'defaults'  => ['tax_percent' => (float) $quotation->tax_percent, 'terms' => $quotation->terms, 'valid_days' => 15],
        ]);
    }

    /** Create (no id) or re-price (id): every change of items / prices / totals adds a revision. */
    public function store(Request $request)
    {
        $id = $request->filled('id') ? (int) $request->input('id') : null;

        $validator = Validator::make($request->all(), [
            'id'                         => ['nullable', 'integer'],
            'customer_id'                => ['required', 'integer', Rule::exists('parties', 'id')->where('deleted', 'No')->whereIn('type', self::CUSTOMER_TYPES)],
            'title'                      => ['nullable', 'string', 'max:200'],
            'quotation_date'             => ['required', 'date'],
            'valid_until'                => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'discount_amount'            => ['nullable', 'numeric', 'min:0'],
            'tax_percent'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'                      => ['nullable', 'string', 'max:2000'],
            'terms'                      => ['nullable', 'string', 'max:5000'],
            'reason'                     => ['nullable', 'string', 'max:255'],
            'items'                      => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id'         => ['required', 'integer', Rule::exists('products', 'id')->where('deleted', 'No')],
            'items.*.product_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('deleted', 'No')],
            'items.*.description'        => ['nullable', 'string', 'max:500'],
            'items.*.qty'                => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'items.*.unit_price'         => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.discount'           => ['nullable', 'numeric', 'min:0'],
        ], [
            'customer_id.required'      => 'Select the customer.',
            'items.required'            => 'Add at least one product.',
            'items.*.qty.gt'            => 'Quantity must be more than zero.',
            'valid_until.after_or_equal' => 'Valid until cannot be before the quotation date.',
        ]);

        $validator->after(function ($v) use ($request) {
            foreach ((array) $request->input('items', []) as $i => $item) {
                if (!empty($item['product_variant_id'])
                    && !DB::table('product_variants')->where('id', (int) $item['product_variant_id'])->where('product_id', (int) ($item['product_id'] ?? 0))->exists()) {
                    $v->errors()->add("items.$i.product_variant_id", 'This variant belongs to another product.');
                }
                if ((float) ($item['discount'] ?? 0) > (float) ($item['qty'] ?? 0) * (float) ($item['unit_price'] ?? 0)) {
                    $v->errors()->add("items.$i.discount", 'Discount is more than the line amount.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $id) {
                [$lines, $totals] = $this->priceLines($request);
                abort_if($totals['discount_amount'] > $totals['subtotal'], 422, 'The overall discount is more than the subtotal.');

                $header = [
                    'customer_id'     => (int) $request->input('customer_id'),
                    'title'           => $this->nullIfBlank($request->input('title')),
                    'quotation_date'  => $request->input('quotation_date'),
                    'valid_until'     => $request->input('valid_until') ?: null,
                    'notes'           => $this->nullIfBlank($request->input('notes')),
                    'terms'           => $this->nullIfBlank($request->input('terms')),
                ] + $totals;

                if (!$id) {
                    $last = DB::table('sale_quotations')->lockForUpdate()->max('quotation_no');
                    $next = $last ? ((int) substr($last, 3)) + 1 : 1;

                    $id = DB::table('sale_quotations')->insertGetId($header + [
                        'quotation_no' => 'QT-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                        'revision_no' => 1, 'quotation_status' => 'Draft', 'deleted' => 'No', 'status' => 'Active',
                        'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $this->saveLines($id, $lines);
                    $this->saveRevision($id, 1, 'First version', $lines, $header);
                    $quotation = $this->findQuotation($id);
                    $this->log($quotation, 'created', 'Quotation created for ' . number_format($totals['grand_total'], 2) . ' (' . count($lines) . ' item(s)).');

                    return ['quotation' => $quotation, 'repriced' => false];
                }

                $current = BranchController::scope(DB::table('sale_quotations'), 'branch_id')->where('id', $id)->where('deleted', 'No')->lockForUpdate()->first();
                abort_unless($current, 404, 'Quotation not found.');

                // A re-price = items, prices, discounts, VAT or validity changed.
                $before = $this->itemsOf($id)->map(fn ($l) => $this->lineKey((array) $l))->all();
                $after  = array_map(fn ($l) => $this->lineKey($l), $lines);
                $changed = $before !== $after
                    || abs((float) $current->discount_amount - $totals['discount_amount']) > 0.004
                    || abs((float) $current->tax_percent - $totals['tax_percent']) > 0.004
                    || (string) $current->valid_until !== (string) ($header['valid_until'] ?? '');

                DB::table('sale_quotations')->where('id', $id)->update($header + [
                    'revision_no' => $changed ? $current->revision_no + 1 : $current->revision_no,
                    'updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now(),
                ]);

                if ($changed) {
                    $revision = $current->revision_no + 1;
                    $this->saveLines($id, $lines);
                    $this->saveRevision($id, $revision, $this->nullIfBlank($request->input('reason')) ?? 'Re-priced', $lines, $header);
                    $quotation = $this->findQuotation($id);
                    $diff = (float) $totals['grand_total'] - (float) $current->grand_total;
                    $this->log($quotation, 'repriced', 'Re-priced to revision ' . $revision . ': ' . number_format((float) $current->grand_total, 2)
                        . ' → ' . number_format($totals['grand_total'], 2) . ' (' . ($diff >= 0 ? '+' : '−') . number_format(abs($diff), 2) . ')'
                        . ($request->filled('reason') ? ' — ' . trim($request->input('reason')) : '') . '.',
                        ['revision' => $revision, 'old_total' => (float) $current->grand_total, 'new_total' => $totals['grand_total']]);
                } else {
                    $quotation = $this->findQuotation($id);
                    $this->log($quotation, 'updated', 'Details updated (no price change).');
                }

                return ['quotation' => $quotation, 'repriced' => $changed];
            });

            $q = $result['quotation'];

            return response()->json([
                'status'   => true,
                'message'  => $id && $result['repriced']
                    ? $q->quotation_no . ' re-priced — revision ' . $q->revision_no . ' saved.'
                    : $q->quotation_no . ($id ? ' updated.' : ' created.'),
                'redirect' => route('admin.quotations.show', $q->id),
            ]);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Quotation save error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the quotation. Please try again.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  View, history, PDF                                                */
    /* ------------------------------------------------------------------ */

    public function show(int $id)
    {
        $this->expireOld();
        $quotation = $this->findQuotation($id, true);
        abort_unless($quotation, 404, 'Quotation not found.');

        $revisions = DB::table('sale_quotation_revisions as r')->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->where('r.sale_quotation_id', $id)->orderBy('r.revision_no')
            ->get(['r.*', 'u.name as user_name']);

        return view('admin.sales.quotations.show', [
            'quotation' => $quotation,
            'items'     => $this->itemsOf($id),
            'history'   => $this->revisionHistory($revisions),
            'logs'      => DB::table('sale_quotation_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.created_by')
                ->where('l.sale_quotation_id', $id)->orderByDesc('l.id')->get(['l.*', 'u.name as user_name']),
            'statuses'  => self::STATUSES,
        ]);
    }

    /** ?revision=n prints an older revision; ?download=1 downloads (and is logged). */
    public function pdf(Request $request, int $id)
    {
        $quotation = $this->findQuotation($id, true);
        abort_unless($quotation, 404, 'Quotation not found.');

        $revisionNo = $request->filled('revision') ? (int) $request->query('revision') : null;
        $pdf = $this->buildPdf($quotation, $revisionNo);
        $fileName = $this->pdfName($quotation, $revisionNo);

        if ($request->boolean('download')) {
            $this->log($quotation, 'downloaded', 'PDF downloaded' . ($revisionNo ? ' (revision ' . $revisionNo . ')' : '') . '.');

            return $pdf->download($fileName);
        }

        return $pdf->stream($fileName);
    }

    /* ------------------------------------------------------------------ */
    /*  Send / share / status                                             */
    /* ------------------------------------------------------------------ */

    /** Data for the Send dialog (customer email, default subject and message). */
    public function sendInfo(int $id)
    {
        $q = $this->findQuotation($id, true);
        abort_unless($q, 404, 'Quotation not found.');
        $company = $this->companyName();

        return response()->json([
            'status'  => true,
            'to'      => $q->customer_email,
            'subject' => 'Quotation ' . $q->quotation_no . ($q->title ? ' — ' . $q->title : '') . ' from ' . $company,
            'message' => "Dear {$q->customer_name},\n\nPlease find attached our quotation {$q->quotation_no} for "
                . number_format((float) $q->grand_total, 2) . '.'
                . ($q->valid_until ? ' The prices are valid until ' . Carbon::parse($q->valid_until)->format('d M Y') . '.' : '')
                . "\n\nWe look forward to working with you.\n\nRegards,\n{$company}",
            'whatsapp' => $this->whatsappText($q),
            'phone'    => $this->whatsappNumber($q->customer_phone),
            'no'       => $q->quotation_no,
        ]);
    }

    public function send(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'to'      => ['required', 'email', 'max:150'],
            'cc'      => ['nullable', 'email', 'max:150'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ], ['to.required' => 'Enter the customer email address.']);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $q = $this->findQuotation($id, true);
        if (!$q) {
            return response()->json(['status' => false, 'message' => 'Quotation not found.'], 404);
        }

        try {
            $pdf = $this->buildPdf($q, null)->output();
            $html = view('admin.sales.quotations.email', [
                'quotation' => $q,
                'body'      => $request->input('message'),
                'company'   => $this->companyName(),
            ])->render();

            Mail::html($html, function ($m) use ($request, $q, $pdf) {
                $m->to($request->input('to'))->subject($request->input('subject'))
                    ->attachData($pdf, $this->pdfName($q, null), ['mime' => 'application/pdf']);
                if ($request->filled('cc')) {
                    $m->cc($request->input('cc'));
                }
            });
        } catch (Throwable $e) {
            Log::error('Quotation email error', $this->ctx($e));

            return response()->json([
                'status'  => false,
                'message' => 'The email could not be sent. Check the mail settings (MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD in .env). '
                    . 'You can download the PDF or share it on WhatsApp meanwhile.',
            ], 422);
        }

        $this->markSent($q);
        $this->log($q, 'emailed', 'Emailed to ' . $request->input('to') . ($request->filled('cc') ? ' (cc ' . $request->input('cc') . ')' : '')
            . ' — revision ' . $q->revision_no . ', ' . number_format((float) $q->grand_total, 2) . '.', ['to' => $request->input('to'), 'revision' => $q->revision_no]);

        return response()->json(['status' => true, 'message' => $q->quotation_no . ' emailed to ' . $request->input('to') . '.']);
    }

    /** Logs a WhatsApp share (the browser opens the chat). */
    public function share(int $id)
    {
        $q = $this->findQuotation($id, true);
        if (!$q) {
            return response()->json(['status' => false, 'message' => 'Quotation not found.'], 404);
        }

        $this->markSent($q);
        $this->log($q, 'shared', 'Shared on WhatsApp' . ($q->customer_phone ? ' with ' . $q->customer_phone : '') . ' — revision ' . $q->revision_no . '.');

        return response()->json(['status' => true, 'message' => 'Logged as shared.']);
    }

    public function status(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'quotation_status' => ['required', Rule::in(self::STATUSES)],
            'note'             => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $q = $this->findQuotation($id, true);
        if (!$q) {
            return response()->json(['status' => false, 'message' => 'Quotation not found.'], 404);
        }

        $new = $request->input('quotation_status');
        DB::table('sale_quotations')->where('id', $id)->update(['quotation_status' => $new, 'updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now()]);
        $this->log($q, 'status', 'Status ' . $q->quotation_status . ' → ' . $new . ($request->filled('note') ? ' — ' . trim($request->input('note')) : '') . '.', ['from' => $q->quotation_status, 'to' => $new]);

        return response()->json(['status' => true, 'message' => $q->quotation_no . ' marked ' . $new . '.']);
    }

    /** Copies the current revision into a new quotation (same or another customer). */
    public function duplicate(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where('deleted', 'No')->whereIn('type', self::CUSTOMER_TYPES)],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $q = $this->findQuotation($id, true);
        if (!$q) {
            return response()->json(['status' => false, 'message' => 'Quotation not found.'], 404);
        }

        try {
            $new = DB::transaction(function () use ($request, $q) {
                $lines = $this->itemsOf($q->id)->map(fn ($l) => (array) $l)->map(fn ($l) => array_intersect_key($l, array_flip(
                    ['product_id', 'product_variant_id', 'item_name', 'description', 'qty', 'unit_price', 'discount', 'line_total']
                )))->all();

                $last = DB::table('sale_quotations')->lockForUpdate()->max('quotation_no');
                $next = $last ? ((int) substr($last, 3)) + 1 : 1;
                $days = $q->valid_until ? Carbon::parse($q->quotation_date)->diffInDays(Carbon::parse($q->valid_until)) : null;

                $header = [
                    'customer_id'     => $request->filled('customer_id') ? (int) $request->input('customer_id') : $q->customer_id,
                    'title'           => $q->title,
                    'quotation_date'  => today()->toDateString(),
                    'valid_until'     => $days !== null ? today()->addDays($days)->toDateString() : null,
                    'subtotal'        => $q->subtotal, 'discount_amount' => $q->discount_amount, 'tax_percent' => $q->tax_percent,
                    'tax_amount'      => $q->tax_amount, 'grand_total' => $q->grand_total,
                    'notes'           => $q->notes, 'terms' => $q->terms,
                ];
                $newId = DB::table('sale_quotations')->insertGetId($header + [
                    'quotation_no' => 'QT-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                    'revision_no' => 1, 'quotation_status' => 'Draft', 'deleted' => 'No', 'status' => 'Active',
                    'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->saveLines($newId, $lines);
                $this->saveRevision($newId, 1, 'Copied from ' . $q->quotation_no, $lines, $header);

                $copy = $this->findQuotation($newId);
                $this->log($copy, 'created', 'Created as a copy of ' . $q->quotation_no . ' (revision ' . $q->revision_no . ').', ['source' => $q->id]);
                $this->log($q, 'duplicated', 'Copied to ' . $copy->quotation_no . ($copy->customer_id != $q->customer_id ? ' for another customer' : '') . '.', ['copy' => $newId]);

                return $copy;
            });

            return response()->json(['status' => true, 'message' => 'Copied to ' . $new->quotation_no . '.', 'redirect' => route('admin.quotations.edit', $new->id)]);
        } catch (Throwable $e) {
            Log::error('Quotation duplicate error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to copy the quotation.'], 500);
        }
    }

    public function delete(int $id)
    {
        $q = $this->findQuotation($id);
        if (!$q) {
            return response()->json(['status' => false, 'message' => 'Quotation not found.'], 404);
        }

        DB::table('sale_quotations')->where('id', $id)->update($this->deletedColumns());
        $this->log($q, 'deleted', 'Quotation deleted.');

        return response()->json(['status' => true, 'message' => $q->quotation_no . ' deleted.']);
    }

    /* ------------------------------------------------------------------ */
    /*  Customer quotation log                                            */
    /* ------------------------------------------------------------------ */

    public function customerLog(int $customerId)
    {
        $this->expireOld();
        $customer = DB::table('parties')->where('id', $customerId)->where('deleted', 'No')->first();
        abort_unless($customer, 404, 'Customer not found.');

        $quotations = BranchController::scope(DB::table('sale_quotations'), 'branch_id')->where('customer_id', $customerId)->where('deleted', 'No')
            ->orderByDesc('quotation_date')->orderByDesc('id')->get();

        $logs = BranchController::scope(DB::table('sale_quotation_logs as l')
            ->join('sale_quotations as q', 'q.id', '=', 'l.sale_quotation_id'), 'q.branch_id')
            ->leftJoin('users as u', 'u.id', '=', 'l.created_by')
            ->where('l.customer_id', $customerId)
            ->orderByDesc('l.id')->limit(300)
            ->get(['l.*', 'q.quotation_no', 'q.deleted as quotation_deleted', 'u.name as user_name']);

        return view('admin.sales.quotations.customer-log', [
            'customer'   => $customer,
            'quotations' => $quotations,
            'logs'       => $logs->groupBy(fn ($l) => Carbon::parse($l->created_at)->format('Y-m-d')),
            'stats'      => [
                'count'    => $quotations->count(),
                'quoted'   => (float) $quotations->sum('grand_total'),
                'accepted' => (float) $quotations->where('quotation_status', 'Accepted')->sum('grand_total'),
                'won'      => $quotations->where('quotation_status', 'Accepted')->count(),
                'repriced' => (int) $quotations->sum(fn ($q) => $q->revision_no - 1),
            ],
        ]);
    }

    /** Select2 search: customers. */
    public function customers(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        $rows = DB::table('parties')->where('deleted', 'No')->where('status', 'Active')->whereIn('type', self::CUSTOMER_TYPES)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('code', 'like', "%$term%")->orWhere('phone', 'like', "%$term%")))
            ->orderBy('name')->limit(25)->get(['id', 'name', 'code', 'phone', 'email']);

        return response()->json(['results' => $rows->map(fn ($r) => [
            'id' => $r->id, 'text' => $r->name . ($r->phone ? ' · ' . $r->phone : '') . ($r->code ? ' (' . $r->code . ')' : ''),
        ])]);
    }

    /* ================================================================== */
    /*  Helpers                                                           */
    /* ================================================================== */

    public static function statusBadge(string $status): string
    {
        $cls = ['Draft' => 'bg-secondary', 'Sent' => 'bg-info', 'Accepted' => 'bg-success', 'Rejected' => 'bg-danger', 'Expired' => 'bg-warning'][$status] ?? 'bg-secondary';

        return '<span class="badge ' . $cls . '">' . e($status) . '</span>';
    }

    /** Draft / Sent quotations past their validity become Expired (logged once). */
    private function expireOld(): void
    {
        $due = DB::table('sale_quotations')->where('deleted', 'No')->whereIn('quotation_status', ['Draft', 'Sent'])
            ->whereNotNull('valid_until')->where('valid_until', '<', today()->toDateString())->get();

        foreach ($due as $q) {
            DB::table('sale_quotations')->where('id', $q->id)->update(['quotation_status' => 'Expired', 'updated_at' => now()]);
            DB::table('sale_quotation_logs')->insert([
                'sale_quotation_id' => $q->id, 'customer_id' => $q->customer_id, 'action' => 'status',
                'message' => 'Expired: valid until ' . Carbon::parse($q->valid_until)->format('d M Y') . ' has passed.',
                'meta' => json_encode(['from' => $q->quotation_status, 'to' => 'Expired']), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function findQuotation(int $id, bool $withCustomer = false): ?object
    {
        // Only the logged-in branch's quotations (show, edit, PDF, send, status, copy, delete all come through here).
        $q = BranchController::scope(DB::table('sale_quotations as q'), 'q.branch_id')->where('q.id', $id)->where('q.deleted', 'No');
        if ($withCustomer) {
            $q->join('parties as c', 'c.id', '=', 'q.customer_id')->leftJoin('users as u', 'u.id', '=', 'q.created_by')
                ->select('q.*', 'c.name as customer_name', 'c.code as customer_code', 'c.phone as customer_phone',
                    'c.email as customer_email', 'c.address as customer_address', 'u.name as created_by_name');
        } else {
            $q->select('q.*');
        }

        return $q->first();
    }

    private function itemsOf(int $id): Collection
    {
        return DB::table('sale_quotation_items as i')
            ->leftJoin('products as p', 'p.id', '=', 'i.product_id')
            ->where('i.sale_quotation_id', $id)->orderBy('i.sort_order')->orderBy('i.id')
            ->get(['i.product_id', 'i.product_variant_id', 'i.item_name', 'i.description', 'i.qty', 'i.unit_price', 'i.discount', 'i.line_total', 'p.sku', 'p.image']);
    }

    /** Lines priced on the server (names come from the products, not the form) + totals. */
    private function priceLines(Request $request): array
    {
        $productIds = collect($request->input('items'))->pluck('product_id')->map(fn ($v) => (int) $v)->unique()->all();
        $products = DB::table('products')->whereIn('id', $productIds)->pluck('name', 'id');
        $variants = DB::table('product_variants as v')->leftJoin('attributes as a', 'a.id', '=', 'v.attribute_id')
            ->whereIn('v.product_id', $productIds)->pluck('a.name', 'v.id');

        $lines = [];
        foreach (array_values((array) $request->input('items')) as $item) {
            $qty = round((float) $item['qty'], 3);
            $price = round((float) $item['unit_price'], 2);
            $discount = round((float) ($item['discount'] ?? 0), 2);
            $variant = !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;

            $lines[] = [
                'product_id'         => (int) $item['product_id'],
                'product_variant_id' => $variant,
                'item_name'          => $products[(int) $item['product_id']] . ($variant && !empty($variants[$variant]) ? ' — ' . $variants[$variant] : ''),
                'description'        => $this->nullIfBlank($item['description'] ?? null),
                'qty'                => $qty,
                'unit_price'         => $price,
                'discount'           => $discount,
                'line_total'         => round($qty * $price - $discount, 2),
            ];
        }

        $subtotal = round(array_sum(array_column($lines, 'line_total')), 2);
        $discount = round((float) $request->input('discount_amount', 0), 2);
        $taxPercent = round((float) $request->input('tax_percent', 0), 2);
        $tax = round(max($subtotal - $discount, 0) * $taxPercent / 100, 2);

        return [$lines, [
            'subtotal'        => $subtotal,
            'discount_amount' => $discount,
            'tax_percent'     => $taxPercent,
            'tax_amount'      => $tax,
            'grand_total'     => round($subtotal - $discount + $tax, 2),
        ]];
    }

    private function lineKey(array $l): string
    {
        return implode('|', [(int) $l['product_id'], (int) ($l['product_variant_id'] ?? 0), number_format((float) $l['qty'], 3, '.', ''),
            number_format((float) $l['unit_price'], 2, '.', ''), number_format((float) $l['discount'], 2, '.', ''), trim((string) ($l['description'] ?? ''))]);
    }

    private function saveLines(int $id, array $lines): void
    {
        DB::table('sale_quotation_items')->where('sale_quotation_id', $id)->delete();
        foreach (array_values($lines) as $i => $l) {
            DB::table('sale_quotation_items')->insert($l + ['sale_quotation_id' => $id, 'sort_order' => $i + 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function saveRevision(int $id, int $revision, ?string $reason, array $lines, array $header): void
    {
        DB::table('sale_quotation_revisions')->insert([
            'sale_quotation_id' => $id,
            'revision_no'       => $revision,
            'reason'            => $reason,
            'items'             => json_encode(array_values($lines)),
            'subtotal'          => $header['subtotal'],
            'discount_amount'   => $header['discount_amount'],
            'tax_percent'       => $header['tax_percent'],
            'tax_amount'        => $header['tax_amount'],
            'grand_total'       => $header['grand_total'],
            'valid_until'       => $header['valid_until'] ?? null,
            'created_by'        => Auth::id(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Each revision with what changed against the one before: price up / down (with %),
     * quantity, discount, items added / removed, and the total.
     */
    private function revisionHistory(Collection $revisions): array
    {
        $out = [];
        $prev = null;

        foreach ($revisions as $r) {
            $items = collect(json_decode($r->items, true));
            $changes = [];

            if ($prev) {
                $key = fn ($l) => $l['product_id'] . '-' . ($l['product_variant_id'] ?? 0);
                $old = $prev['items']->keyBy($key);
                $new = $items->keyBy($key);

                foreach ($new as $k => $l) {
                    if (!$old->has($k)) {
                        $changes[] = ['type' => 'added', 'name' => $l['item_name'], 'text' => 'Added · ' . (float) $l['qty'] . ' × ' . number_format($l['unit_price'], 2)];
                        continue;
                    }
                    $o = $old[$k];
                    if (abs($o['unit_price'] - $l['unit_price']) > 0.004) {
                        $pct = $o['unit_price'] > 0 ? ($l['unit_price'] - $o['unit_price']) / $o['unit_price'] * 100 : null;
                        $changes[] = ['type' => $l['unit_price'] > $o['unit_price'] ? 'up' : 'down', 'name' => $l['item_name'],
                            'old' => (float) $o['unit_price'], 'new' => (float) $l['unit_price'], 'pct' => $pct, 'text' => 'Unit price'];
                    }
                    if (abs($o['qty'] - $l['qty']) > 0.0005) {
                        $changes[] = ['type' => 'qty', 'name' => $l['item_name'], 'old' => (float) $o['qty'], 'new' => (float) $l['qty'], 'text' => 'Quantity'];
                    }
                    if (abs($o['discount'] - $l['discount']) > 0.004) {
                        $changes[] = ['type' => 'discount', 'name' => $l['item_name'], 'old' => (float) $o['discount'], 'new' => (float) $l['discount'], 'text' => 'Line discount'];
                    }
                }
                foreach ($old as $k => $o) {
                    if (!$new->has($k)) {
                        $changes[] = ['type' => 'removed', 'name' => $o['item_name'], 'text' => 'Removed'];
                    }
                }
                if (abs($prev['rev']->discount_amount - $r->discount_amount) > 0.004) {
                    $changes[] = ['type' => 'discount', 'name' => 'Overall discount', 'old' => (float) $prev['rev']->discount_amount, 'new' => (float) $r->discount_amount, 'text' => 'Discount'];
                }
                if (abs($prev['rev']->tax_percent - $r->tax_percent) > 0.004) {
                    $changes[] = ['type' => 'tax', 'name' => 'VAT', 'old' => (float) $prev['rev']->tax_percent, 'new' => (float) $r->tax_percent, 'text' => 'VAT %'];
                }
                if ((string) $prev['rev']->valid_until !== (string) $r->valid_until) {
                    $changes[] = ['type' => 'validity', 'name' => 'Valid until', 'text' => ($r->valid_until ? Carbon::parse($r->valid_until)->format('d M Y') : 'no date')];
                }
            }

            $out[] = [
                'rev'     => $r,
                'items'   => $items,
                'changes' => $changes,
                'delta'   => $prev ? (float) $r->grand_total - (float) $prev['rev']->grand_total : null,
                'pct'     => $prev && $prev['rev']->grand_total > 0 ? ((float) $r->grand_total - (float) $prev['rev']->grand_total) / (float) $prev['rev']->grand_total * 100 : null,
            ];
            $prev = ['rev' => $r, 'items' => $items];
        }

        return array_reverse($out);   // newest first
    }

    private function log(object $quotation, string $action, string $message, array $meta = []): void
    {
        DB::table('sale_quotation_logs')->insert([
            'sale_quotation_id' => $quotation->id,
            'customer_id'       => $quotation->customer_id,
            'action'            => $action,
            'message'           => $message,
            'meta'              => $meta ? json_encode($meta) : null,
            'created_by'        => Auth::id(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private function markSent(object $q): void
    {
        DB::table('sale_quotations')->where('id', $q->id)->update([
            'quotation_status' => in_array($q->quotation_status, ['Draft', 'Expired'], true) ? 'Sent' : $q->quotation_status,
            'sent_at'          => now(),
            'sent_count'       => DB::raw('sent_count + 1'),
            'updated_at'       => now(),
        ]);
    }

    private function buildPdf(object $q, ?int $revisionNo)
    {
        $items = $this->itemsOf($q->id);
        $totals = $q;
        $revision = null;

        if ($revisionNo && $revisionNo !== (int) $q->revision_no) {
            $revision = DB::table('sale_quotation_revisions')->where('sale_quotation_id', $q->id)->where('revision_no', $revisionNo)->first();
            abort_unless($revision, 404, 'Revision not found.');
            $images = DB::table('products')->whereIn('id', collect(json_decode($revision->items, true))->pluck('product_id'))->pluck('image', 'id');
            $items = collect(json_decode($revision->items, true))->map(fn ($l) => (object) ($l + ['image' => $images[$l['product_id']] ?? null, 'sku' => null]));
            $totals = $revision;
        }

        return PDF::loadView('admin.sales.quotations.pdf', [
            'quotation' => $q,
            'items'     => $items->map(function ($l) {
                $l->thumb = $this->imageData($l->image ? 'uploads/products/' . $l->image : null, 160);

                return $l;
            }),
            'totals'    => $totals,
            'revisionNo' => $revisionNo ?: (int) $q->revision_no,
            'isOld'     => (bool) $revision,
            'company'   => $this->company(),
            'printedAt' => now()->format('d M Y, h:i A'),
        ])->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true);
    }

    private function pdfName(object $q, ?int $revisionNo): string
    {
        return 'Quotation-' . $q->quotation_no . '-Rev' . ($revisionNo ?: $q->revision_no) . '.pdf';
    }

    private function whatsappNumber(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }

        // Local Bangladeshi numbers (01XXXXXXXXX) get the country code.
        return str_starts_with($digits, '0') && strlen($digits) === 11 ? '88' . $digits : $digits;
    }

    private function whatsappText(object $q): string
    {
        $lines = $this->itemsOf($q->id)->map(fn ($l) => '• ' . $l->item_name . ' — ' . (float) $l->qty . ' × ' . number_format((float) $l->unit_price, 2)
            . ' = ' . number_format((float) $l->line_total, 2))->implode("\n");

        return "*Quotation {$q->quotation_no}*" . ($q->title ? " — {$q->title}" : '') . "\nFrom " . $this->companyName() . "\n\n"
            . $lines . "\n\n*Total: " . number_format((float) $q->grand_total, 2) . '*'
            . ($q->valid_until ? "\nValid until " . Carbon::parse($q->valid_until)->format('d M Y') : '')
            . "\n\nThe PDF quotation follows.";
    }

    private function companyName(): string
    {
        return Setting::where('deleted', 'No')->value('company_name') ?: config('app.name');
    }

    private function company(): array
    {
        $s = Setting::where('deleted', 'No')->where('status', 'Active')->first();

        return [
            'name'    => $s->company_name ?? 'Company Name',
            'tagline' => $s->company_tagline ?? null,
            'address' => trim(implode(', ', array_filter([$s->address ?? null, $s->city ?? null, $s->country ?? null]))),
            'phone'   => $s->phone ?? null,
            'email'   => $s->email ?? null,
            'website' => $s->website ?? null,
            'logo'    => $this->imageData($s->logo ?? null),
            'signature' => $this->imageData($s->signature ?? null),
        ];
    }

    /**
     * Base64 data URI for dompdf; WEBP is converted to PNG. With $maxSize the image is scaled down
     * first (product thumbnails), which keeps the PDF small.
     */
    private function imageData(?string $path, ?int $maxSize = null): ?string
    {
        if (!$path || !is_file($full = public_path($path))) {
            return null;
        }

        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));

        if ($maxSize && function_exists('imagecreatefromstring')) {
            $img = $ext === 'webp' && function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($full) : @imagecreatefromstring((string) file_get_contents($full));
            if ($img) {
                $w = imagesx($img);
                $h = imagesy($img);
                $scale = min(1, $maxSize / max($w, $h));
                $thumb = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
                imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
                imagecopyresampled($thumb, $img, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb), $w, $h);
                ob_start();
                imagejpeg($thumb, null, 82);
                $jpg = ob_get_clean();
                imagedestroy($img);
                imagedestroy($thumb);

                return 'data:image/jpeg;base64,' . base64_encode($jpg);
            }
        }
        if ($ext === 'webp') {
            if (!function_exists('imagecreatefromwebp') || !($img = @imagecreatefromwebp($full))) {
                return null;
            }
            ob_start();
            imagepng($img);
            $png = ob_get_clean();
            imagedestroy($img);

            return 'data:image/png;base64,' . base64_encode($png);
        }

        return in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true)
            ? 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode(file_get_contents($full))
            : null;
    }
}
