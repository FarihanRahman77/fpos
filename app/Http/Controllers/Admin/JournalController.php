<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Journal;
use App\Models\Admin\JournalDetail;
use App\Traits\AccountBalanceTrait;
use App\Traits\SequentialCodeTrait;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class JournalController extends Controller
{
    use AccountBalanceTrait, SequentialCodeTrait, VoucherHelperTrait;

    private const MAX_LINES = 100;

    /* ------------------------------------------------------------------ */
    /*  Page                                                              */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        return view('admin.accounts.journal.index', [
            // Every active 2nd layer group, any account type.
            'groups' => $this->coaGroups(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  DataTable                                                         */
    /* ------------------------------------------------------------------ */

    public function list(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date', 'after_or_equal:from'],
            'account_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json(['data' => [], 'message' => $validator->errors()->first()], 422);
        }

        try {
            $query = DB::table('journals as j')->where('j.deleted', 'No')->select('j.*');

            if ($request->filled('from')) {
                $query->whereDate('j.journal_date', '>=', $request->query('from'));
            }

            if ($request->filled('to')) {
                $query->whereDate('j.journal_date', '<=', $request->query('to'));
            }

            if ($request->filled('account_id')) {
                $accountId = (int) $request->query('account_id');

                $query->whereExists(function ($q) use ($accountId) {
                    $q->select(DB::raw(1))->from('journal_details as d')
                        ->whereColumn('d.journal_id', 'j.id')
                        ->where('d.deleted', 'No')
                        ->where('d.account_id', $accountId);
                });
            }

            $journals = $query->orderByDesc('j.journal_date')->orderByDesc('j.id')->get();

            // Account names per journal (single query).
            $lines = DB::table('journal_details as d')
                ->join('chart_of_accounts as c', 'c.id', '=', 'd.account_id')
                ->whereIn('d.journal_id', $journals->pluck('id'))
                ->where('d.deleted', 'No')
                ->orderBy('d.id')
                ->get(['d.journal_id', 'd.debit', 'c.account_name'])
                ->groupBy('journal_id');

            $output = ['data' => []];
            $i = 1;

            foreach ($journals as $journal) {
                $id   = (int) $journal->id;
                $rows = $lines->get($journal->id, collect());

                $accountsCell = $rows->map(function ($l) {
                    $side = (float) $l->debit > 0 ? 'Dr' : 'Cr';
                    $tone = $side === 'Dr' ? 'text-primary' : 'text-success';

                    return '<div class="small"><span class="fw-semibold ' . $tone . '">' . $side . '</span> ' . e($l->account_name) . '</div>';
                })->take(3)->implode('');

                if ($rows->count() > 3) {
                    $accountsCell .= '<span class="text-muted small">+' . ($rows->count() - 3) . ' more</span>';
                }

                $date = Carbon::parse($journal->journal_date);

                $output['data'][] = [
                    $i++,
                    '<span class="fw-semibold">' . e($journal->journal_no) . '</span>',
                    '<span class="d-none">' . $date->format('Y-m-d') . '</span>' . e($date->format('d M Y')),
                    e($journal->reference_no ?: '-'),
                    $accountsCell ?: '<span class="text-muted">-</span>',
                    '<span class="d-none">' . sprintf('%021.2f', (float) $journal->total_debit) . '</span>'
                        . '<span class="d-block text-end fw-semibold">' . number_format((float) $journal->total_debit, 2) . '</span>',
                    $this->actionButtons('Journal', $id),
                ];
            }

            $output['summary'] = [
                'filtered_total' => number_format((float) $journals->sum('total_debit'), 2),
                'filtered_count' => $journals->count(),
                'month_total'    => number_format((float) DB::table('journals')->where('deleted', 'No')
                    ->whereBetween('journal_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->sum('total_debit'), 2),
                'today_total'    => number_format((float) DB::table('journals')->where('deleted', 'No')
                    ->whereDate('journal_date', now()->toDateString())->sum('total_debit'), 2),
            ];

            return response()->json($output);
        } catch (Throwable $e) {
            Log::error('Journal list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load journals.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  One journal with its lines (view / edit modal)                    */
    /* ------------------------------------------------------------------ */

    public function show(Request $request)
    {
        try {
            $journal = DB::table('journals')
                ->where('id', (int) $request->query('id'))->where('deleted', 'No')->first();

            if (!$journal) {
                return response()->json(['status' => false, 'message' => 'Journal not found.'], 404);
            }

            $details = DB::table('journal_details as d')
                ->leftJoin('chart_of_accounts as g', 'g.id', '=', 'd.account_group_id')
                ->leftJoin('chart_of_accounts as c', 'c.id', '=', 'd.account_id')
                ->where('d.journal_id', $journal->id)
                ->where('d.deleted', 'No')
                ->orderBy('d.id')
                ->get([
                    'd.account_group_id', 'd.account_id', 'd.description', 'd.debit', 'd.credit',
                    'g.account_code as g_code', 'g.account_name as g_name',
                    'c.account_code as c_code', 'c.account_name as c_name',
                ])
                ->map(fn ($d) => [
                    'account_group_id' => $d->account_group_id,
                    'account_id'       => $d->account_id,
                    'group_label'      => $d->g_code . ' - ' . $d->g_name,
                    'account_label'    => $d->c_code . ' - ' . $d->c_name,
                    'description'      => $d->description,
                    'debit'            => (float) $d->debit > 0 ? number_format((float) $d->debit, 2, '.', '') : '',
                    'credit'           => (float) $d->credit > 0 ? number_format((float) $d->credit, 2, '.', '') : '',
                ]);

            return response()->json([
                'status' => true,
                'data'   => [
                    'id'           => $journal->id,
                    'journal_no'   => $journal->journal_no,
                    'journal_date' => Carbon::parse($journal->journal_date)->format('Y-m-d'),
                    'reference_no' => $journal->reference_no,
                    'remarks'      => $journal->remarks,
                    'total_debit'  => number_format((float) $journal->total_debit, 2, '.', ''),
                    'total_credit' => number_format((float) $journal->total_credit, 2, '.', ''),
                    'details'      => $details,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Journal show error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to load journal.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Create / update (update when "id" is posted)                      */
    /* ------------------------------------------------------------------ */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'           => ['nullable', 'integer'],
            'journal_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'remarks'      => ['nullable', 'string', 'max:1000'],

            'details'                    => ['required', 'array', 'min:2', 'max:' . self::MAX_LINES],
            'details.*.account_group_id' => ['required', 'integer', $this->coaExists(2)],
            'details.*.account_id'       => ['required', 'integer', $this->coaExists(3)],
            'details.*.description'      => ['nullable', 'string', 'max:255'],
            'details.*.debit'            => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'details.*.credit'           => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ], [
            'details.required'                    => 'Add at least two lines.',
            'details.min'                         => 'A journal needs at least two lines.',
            'details.max'                         => 'You can add at most ' . self::MAX_LINES . ' lines.',
            'details.*.account_group_id.required' => 'Select a group.',
            'details.*.account_group_id.exists'   => 'Select a valid group.',
            'details.*.account_id.required'       => 'Select an account.',
            'details.*.account_id.exists'         => 'Select a valid account.',
        ]);

        // Line rules that need the whole row: one side only, and debit = credit overall.
        $validator->after(function ($v) use ($request) {
            $totalDebit = $totalCredit = 0;

            foreach ((array) $request->input('details', []) as $i => $line) {
                $debit  = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);

                if ($debit > 0 && $credit > 0) {
                    $v->errors()->add("details.$i.debit", 'Enter either a debit or a credit, not both.');
                } elseif ($debit <= 0 && $credit <= 0) {
                    $v->errors()->add("details.$i.debit", 'Enter a debit or a credit amount.');
                }

                if (!empty($line['account_id']) && !empty($line['account_group_id'])
                    && !DB::table('chart_of_accounts')->where('id', (int) $line['account_id'])->where('parent_id', (int) $line['account_group_id'])->exists()) {
                    $v->errors()->add("details.$i.account_id", 'This account does not belong to the selected group.');
                }

                $totalDebit  += $debit;
                $totalCredit += $credit;
            }

            if (!$v->errors()->has('details.*') && abs($totalDebit - $totalCredit) >= 0.005) {
                $v->errors()->add('details', 'Total debit (' . number_format($totalDebit, 2) . ') must equal total credit (' . number_format($totalCredit, 2) . ').');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $lines = collect($request->input('details'))->map(fn ($d) => [
                'account_group_id' => (int) $d['account_group_id'],
                'account_id'       => (int) $d['account_id'],
                'description'      => $this->nullIfBlank($d['description'] ?? null),
                'debit'            => round((float) ($d['debit'] ?? 0), 2),
                'credit'           => round((float) ($d['credit'] ?? 0), 2),
            ]);

            $total = round($lines->sum('debit'), 2);

            if ($request->filled('id')) {
                $journal = Journal::where('id', (int) $request->input('id'))->where('deleted', 'No')->lockForUpdate()->first();

                if (!$journal) {
                    abort(404, 'Journal not found.');
                }

                $this->reverseJournal($journal->id);

                $journal->updated_by   = Auth::id();
                $journal->updated_date = now();
            } else {
                $journal = new Journal();
                $journal->journal_no   = $this->nextSequentialCode(Journal::class, 'journal_no', 'JV');
                $journal->created_by   = Auth::id();
                $journal->created_date = now();
            }

            $journal->journal_date = $request->input('journal_date');
            $journal->reference_no = $this->nullIfBlank($request->input('reference_no'));
            $journal->remarks      = $this->nullIfBlank($request->input('remarks'));
            $journal->total_debit  = $total;
            $journal->total_credit = $total;
            $journal->save();

            foreach ($lines as $line) {
                $detail = new JournalDetail();
                $detail->journal_id       = $journal->id;
                $detail->account_group_id = $line['account_group_id'];
                $detail->account_id       = $line['account_id'];
                $detail->description      = $line['description'];
                $detail->debit            = $line['debit'];
                $detail->credit           = $line['credit'];
                $detail->created_by       = Auth::id();
                $detail->created_date     = now();
                $detail->save();
            }

            $this->postBalances(
                $this->sumByColumn($lines, 'account_id', 'debit'),
                $this->sumByColumn($lines, 'account_id', 'credit'),
                1
            );

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Journal ' . $journal->journal_no . ($request->filled('id') ? ' updated' : ' created') . ' successfully.',
            ]);
        } catch (HttpExceptionInterface $e) {
            DB::rollBack();

            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Journal store error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the journal. Please try again.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Delete (soft) and reverse the balances                            */
    /* ------------------------------------------------------------------ */

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), ['id' => ['required', 'integer', 'min:1']]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'A valid journal id is required.'], 422);
        }

        try {
            DB::transaction(function () use ($request) {
                $journal = DB::table('journals')
                    ->where('id', (int) $request->input('id'))->where('deleted', 'No')
                    ->lockForUpdate()->first();

                if (!$journal) {
                    abort(404, 'Journal not found.');
                }

                $this->reverseJournal($journal->id);

                DB::table('journals')->where('id', $journal->id)->update($this->deletedColumns());
            }, 3);

            return response()->json(['status' => true, 'message' => 'Journal deleted and balances reversed.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Journal delete error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to delete the journal. Please try again.'], 500);
        }
    }

    /* ================================================================== */
    /*  Helpers                                                           */
    /* ================================================================== */

    /** Takes the active lines' amounts back out of the balances and soft-deletes the lines. */
    private function reverseJournal(int $journalId): void
    {
        $old = DB::table('journal_details')->where('journal_id', $journalId)->where('deleted', 'No')
            ->get(['account_id', 'debit', 'credit']);

        $this->postBalances(
            $this->sumByColumn($old, 'account_id', 'debit'),
            $this->sumByColumn($old, 'account_id', 'credit'),
            -1
        );

        DB::table('journal_details')->where('journal_id', $journalId)->where('deleted', 'No')
            ->update($this->deletedColumns());
    }
}
