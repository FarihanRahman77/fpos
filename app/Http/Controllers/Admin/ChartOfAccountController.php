<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Chart of Accounts
 *
 * Code structure (8 digits):
 *   1st layer : D0000000   e.g. 10000000, 60000000   (seeded, never deletable)
 *   2nd layer : DNN00000   e.g. 10100000, 10200000   (NN = group sequence 01-99)
 *   3rd layer : DNNMM000   e.g. 10101000, 10102000   (MM = ledger sequence 01-99)
 */
class ChartOfAccountController extends Controller
{
    private const LAYERS = [1 => '1st Layer', 2 => '2nd Layer', 3 => '3rd Layer'];

    private const TYPE_COLORS = [
        'Asset'        => 'primary',
        'Liquid Asset' => 'info',
        'Liability'    => 'warning',
        'Equity'       => 'secondary',
        'Income'       => 'success',
        'Expense'      => 'danger',
    ];

    /* ------------------------------------------------------------------ */
    /*  Page                                                              */
    /* ------------------------------------------------------------------ */

    public function page()
    {
        return view('admin.chart_of_accounts.index');
    }

    /* ------------------------------------------------------------------ */
    /*  DataTable source (rows are fully rendered here)                   */
    /* ------------------------------------------------------------------ */

    public function index(): JsonResponse
    {
        try {
            $accounts = DB::table('chart_of_accounts')
                ->where('deleted', 'No')
                ->orderBy('account_code')   // code order == tree order
                ->orderBy('id')
                ->get();

            $byId    = $accounts->keyBy('id');
            $rows    = [];
            $parents = [];
            $counts  = ['roots' => 0, 'groups' => 0, 'ledgers' => 0];

            foreach ($accounts as $i => $a) {
                $layer = (int) $a->layer_level;

                if ($layer === 1) {
                    $counts['roots']++;
                } elseif ($layer === 2) {
                    $counts['groups']++;
                } elseif ($layer === 3) {
                    $counts['ledgers']++;
                }

                $rows[] = [
                    $i + 1,                         // 0 SL
                    $this->codeCell($a),            // 1 Code
                    $this->nameCell($a),            // 2 Name
                    $this->layerCell($layer),       // 3 Layer
                    $this->typeCell($a),            // 4 Type
                    e($a->balance ?: '-'),          // 5 Normal balance
                    $this->moneyCell($a),           // 6 Opening balance
                    $this->dateCell($a),            // 7 Opening date
                    $this->statusCell($a),          // 8 Status
                    $this->actionCell($a),          // 9 Action
                ];

                // Parent dropdown: active 1st + 2nd layer accounts.
                if ($layer <= 2 && $a->status === 'Active') {
                    $root = $layer === 2 ? $byId->get($a->parent_id) : $a;

                    $parents[] = [
                        'id'            => $a->id,
                        'account_code'  => $a->account_code,
                        'account_name'  => $a->account_name,
                        'layer_level'   => $layer,
                        'root_name'     => $root->account_name ?? '',
                    ];
                }
            }

            return response()->json([
                'data'    => $rows,
                'counts'  => $counts,
                'parents' => $parents,
            ]);
        } catch (Throwable $e) {
            Log::error('COA list error', $this->ctx($e));

            return response()->json([
                'data'    => [],
                'message' => 'Unable to load chart of accounts.',
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Show one account (for the edit modal)                             */
    /* ------------------------------------------------------------------ */

    public function show($id): JsonResponse
    {
        try {
            $account = DB::table('chart_of_accounts')
                ->where('id', $id)
                ->where('deleted', 'No')
                ->first();

            if (!$account) {
                return response()->json(['status' => false, 'message' => 'Account not found.'], 404);
            }

            $parent = $account->parent_id
                ? DB::table('chart_of_accounts')->where('id', $account->parent_id)->first()
                : null;

            return response()->json([
                'status' => true,
                'data'   => [
                    'id'              => $account->id,
                    'account_code'    => $account->account_code,
                    'account_name'    => $account->account_name,
                    'layer_level'     => (int) $account->layer_level,
                    'layer_label'     => self::LAYERS[(int) $account->layer_level] ?? '',
                    'parent_id'       => $account->parent_id,
                    'parent_label'    => $parent
                        ? $parent->account_code . ' - ' . $parent->account_name
                        : '— (Top level account)',
                    'account_type'    => $account->account_type,
                    'normal_balance'  => $account->balance,
                    'status'          => $account->status,
                    'is_system'       => (int) $account->is_system,
                    'opening_balance' => number_format((float) ($account->opening_balance ?? 0), 2, '.', ''),
                    'opening_balance_entry_datetime' => $account->opening_balance_entry_datetime
                        ? Carbon::parse($account->opening_balance_entry_datetime)->format('Y-m-d\TH:i')
                        : '',
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('COA show error', $this->ctx($e, ['id' => $id]));

            return response()->json(['status' => false, 'message' => 'Unable to load account.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Preview the next code for a chosen parent                         */
    /* ------------------------------------------------------------------ */

    public function nextCode($parentId): JsonResponse
    {
        try {
            [$parent, $root, $newLayer] = $this->resolveParent((int) $parentId, false);

            return response()->json([
                'status' => true,
                'data'   => [
                    'account_code'   => $this->generateCode($parent, $newLayer),
                    'account_type'   => $root->account_type,
                    'normal_balance' => $root->balance,
                    'layer_level'    => $newLayer,
                    'layer_label'    => self::LAYERS[$newLayer],
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        } catch (Throwable $e) {
            Log::error('COA next-code error', $this->ctx($e, ['parent_id' => $parentId]));

            return response()->json(['status' => false, 'message' => 'Unable to generate account code.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Create (1st layer parent => 2nd layer, 2nd layer parent => 3rd)   */
    /* ------------------------------------------------------------------ */

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'parent_id'       => ['required', 'integer', 'min:1'],
            'account_name'    => ['required', 'string', 'max:150'],
            'status'          => ['sometimes', 'required', Rule::in(['Active', 'Inactive'])],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'opening_balance_entry_datetime' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => (float) $request->input('opening_balance', 0) > 0),
            ],
        ], $this->messages());

        if ($validator->fails()) {
            return $this->validatorResponse($validator);
        }

        try {
            $account = DB::transaction(function () use ($request) {
                [$parent, $root, $newLayer] = $this->resolveParent((int) $request->input('parent_id'), true);

                $name = trim((string) $request->input('account_name'));
                $this->assertUniqueName((int) $parent->id, $name);

                $code = $this->generateCode($parent, $newLayer);

                if (DB::table('chart_of_accounts')->where('account_code', $code)->exists()) {
                    $this->fail('account_name', 'The generated account code already exists. Please retry.');
                }

                $today = now()->toDateString();

                $id = DB::table('chart_of_accounts')->insertGetId([
                    'account_code'    => $code,
                    'account_name'    => $name,
                    'slug'            => strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-')),
                    'layer_level'     => $newLayer,
                    'parent_id'       => $parent->id,
                    'account_type'    => $root->account_type,
                    'balance'         => $root->balance,
                    'opening_balance' => round((float) $request->input('opening_balance', 0), 2),
                    'opening_balance_entry_datetime' => $request->filled('opening_balance_entry_datetime')
                        ? Carbon::parse($request->input('opening_balance_entry_datetime'))
                        : null,
                    'is_system'       => 0,
                    'deleted'         => 'No',
                    'status'          => $request->input('status', 'Active'),
                    'created_by'      => Auth::id(),
                    'updated_by'      => Auth::id(),
                    'created_date'    => $today,
                    'updated_date'    => $today,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                return DB::table('chart_of_accounts')->where('id', $id)->first();
            }, 3);

            return response()->json([
                'status'  => true,
                'message' => ((int) $account->layer_level === 2 ? '2nd layer' : '3rd layer')
                    . ' account created. Code: ' . $account->account_code,
                'data'    => $account,
            ], 201);
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        } catch (Throwable $e) {
            Log::error('COA create error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to create the account. Please try again.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Update (name, status, opening balance + date)                     */
    /* ------------------------------------------------------------------ */

    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_name'    => ['sometimes', 'required', 'string', 'max:150'],
            'status'          => ['sometimes', 'required', Rule::in(['Active', 'Inactive'])],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'opening_balance_entry_datetime' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => (float) $request->input('opening_balance', 0) > 0),
            ],
        ], $this->messages());

        if ($validator->fails()) {
            return $this->validatorResponse($validator);
        }

        if (!$request->hasAny(['account_name', 'status', 'opening_balance'])) {
            return response()->json(['status' => false, 'message' => 'Nothing to update.'], 422);
        }

        try {
            $account = DB::transaction(function () use ($request, $id) {
                $account = $this->lockAccount($id);

                $updates = [
                    'updated_by'   => Auth::id(),
                    'updated_date' => now()->toDateString(),
                    'updated_at'   => now(),
                ];

                if ($request->exists('account_name')) {
                    $name = trim((string) $request->input('account_name'));
                    $this->assertUniqueName($account->parent_id, $name, (int) $account->id);
                    $updates['account_name'] = $name;
                    $updates['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

                }

                if ($request->exists('status') && $request->input('status') !== $account->status) {
                    $this->assertStatusChange($account, $request->input('status'));
                    $updates['status'] = $request->input('status');
                }

                if ($request->exists('opening_balance')) {
                    $updates['opening_balance'] = round((float) $request->input('opening_balance'), 2);
                    $updates['opening_balance_entry_datetime'] = $request->filled('opening_balance_entry_datetime')
                        ? Carbon::parse($request->input('opening_balance_entry_datetime'))
                        : null;
                }

                DB::table('chart_of_accounts')->where('id', $account->id)->update($updates);

                return DB::table('chart_of_accounts')->where('id', $account->id)->first();
            }, 3);

            return response()->json([
                'status'  => true,
                'message' => 'Account updated successfully.',
                'data'    => $account,
            ]);
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('COA update error', $this->ctx($e, ['id' => $id]));

            return response()->json(['status' => false, 'message' => 'Unable to update the account. Please try again.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Toggle Active / Inactive                                          */
    /* ------------------------------------------------------------------ */

    public function status($id): JsonResponse
    {
        try {
            $account = DB::transaction(function () use ($id) {
                $account   = $this->lockAccount($id);
                $newStatus = $account->status === 'Active' ? 'Inactive' : 'Active';

                $this->assertStatusChange($account, $newStatus);

                DB::table('chart_of_accounts')->where('id', $account->id)->update([
                    'status'       => $newStatus,
                    'updated_by'   => Auth::id(),
                    'updated_date' => now()->toDateString(),
                    'updated_at'   => now(),
                ]);

                return DB::table('chart_of_accounts')->where('id', $account->id)->first();
            }, 3);

            return response()->json([
                'status'  => true,
                'message' => 'Account marked as ' . $account->status . '.',
                'data'    => $account,
            ]);
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('COA status error', $this->ctx($e, ['id' => $id]));

            return response()->json(['status' => false, 'message' => 'Unable to change account status.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Soft delete (2nd + 3rd layer only)                                */
    /* ------------------------------------------------------------------ */

    public function destroy($id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $account = $this->lockAccount($id);

                if ((int) $account->layer_level === 1) {
                    abort(409, '1st layer accounts can never be deleted.');
                }

                if ((int) $account->is_system === 1) {
                    abort(409, 'System accounts cannot be deleted.');
                }

                if (DB::table('chart_of_accounts')
                    ->where('parent_id', $account->id)
                    ->where('deleted', 'No')
                    ->exists()) {
                    abort(409, 'This account has child accounts. Delete or move them first.');
                }

                if (Schema::hasTable('journal_entry_lines')
                    && DB::table('journal_entry_lines')->where('account_id', $account->id)->exists()) {
                    abort(409, 'This account has journal history and cannot be deleted. Mark it Inactive instead.');
                }

                if (abs((float) ($account->opening_balance ?? 0)) > 0.0001) {
                    abort(409, 'Set the opening balance to zero before deleting this account.');
                }

                DB::table('chart_of_accounts')->where('id', $account->id)->update([
                    'deleted'      => 'Yes',
                    'deleted_by'   => Auth::id(),
                    'deleted_date' => now()->toDateString(),
                    'updated_by'   => Auth::id(),
                    'updated_date' => now()->toDateString(),
                    'updated_at'   => now(),
                ]);
            }, 3);

            return response()->json(['status' => true, 'message' => 'Account deleted successfully.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('COA delete error', $this->ctx($e, ['id' => $id]));

            return response()->json(['status' => false, 'message' => 'Unable to delete the account. Please try again.'], 500);
        }
    }

    /* ================================================================== */
    /*  Business rules                                                    */
    /* ================================================================== */

    private function lockAccount($id): object
    {
        $account = DB::table('chart_of_accounts')
            ->where('id', $id)
            ->where('deleted', 'No')
            ->lockForUpdate()
            ->first();

        if (!$account) {
            abort(404, 'Account not found.');
        }

        return $account;
    }

    /**
     * Returns [parent, root, newLayer]. Parent must be an active 1st or 2nd layer account.
     */
    private function resolveParent(int $parentId, bool $lock): array
    {
        $query = DB::table('chart_of_accounts')->where('id', $parentId)->where('deleted', 'No');
        if ($lock) {
            $query->lockForUpdate();
        }
        $parent = $query->first();

        if (!$parent) {
            $this->fail('parent_id', 'The selected parent account does not exist.');
        }

        if ($parent->status !== 'Active') {
            $this->fail('parent_id', 'The selected parent account is inactive.');
        }

        $layer = (int) $parent->layer_level;

        if (!in_array($layer, [1, 2], true)) {
            $this->fail('parent_id', 'Choose a 1st layer or 2nd layer account as the parent.');
        }

        if ($layer === 1) {
            $root = $parent;
        } else {
            $rootQuery = DB::table('chart_of_accounts')
                ->where('id', $parent->parent_id)
                ->where('layer_level', 1)
                ->where('deleted', 'No');
            if ($lock) {
                $rootQuery->lockForUpdate();
            }
            $root = $rootQuery->first();

            if (!$root) {
                $this->fail('parent_id', 'The selected group has an invalid root account.');
            }

            if ($root->status !== 'Active') {
                $this->fail('parent_id', 'The root account is inactive.');
            }
        }

        return [$parent, $root, $layer + 1];
    }

    /**
     * 1st layer 10000000 -> 2nd layer 10100000, 10200000 ... 19900000
     * 2nd layer 10100000 -> 3rd layer 10101000, 10102000 ... 10199000
     * Soft-deleted rows are included so a code is never reused.
     */
    private function generateCode(object $parent, int $newLayer): string
    {
        $parentCode = (string) $parent->account_code;

        if ($newLayer === 2) {
            if (!preg_match('/^[1-9]0{7}$/', $parentCode)) {
                $this->fail('parent_id', 'A 1st layer account code must look like 10000000.');
            }

            $prefix   = substr($parentCode, 0, 1);
            $pattern  = '/^' . $prefix . '(\d{2})00000$/';
            $suffix   = '00000';
            $limitMsg = 'This root account has reached its 99 group limit.';
        } else {
            if (!preg_match('/^[1-9](0[1-9]|[1-9]\d)00000$/', $parentCode)) {
                $this->fail('parent_id', 'A 2nd layer account code must look like 10100000.');
            }

            $prefix   = substr($parentCode, 0, 3);
            $pattern  = '/^' . $prefix . '(\d{2})000$/';
            $suffix   = '000';
            $limitMsg = 'This group has reached its 99 ledger limit.';
        }

        $max = 0;

        $codes = DB::table('chart_of_accounts')
            ->where('parent_id', $parent->id)
            ->where('layer_level', $newLayer)
            ->pluck('account_code');

        foreach ($codes as $code) {
            if (preg_match($pattern, (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        $next = $max + 1;

        if ($next > 99) {
            $this->fail('account_name', $limitMsg);
        }

        return $prefix . str_pad((string) $next, 2, '0', STR_PAD_LEFT) . $suffix;
    }

    private function assertUniqueName($parentId, string $name, ?int $ignoreId = null): void
    {
        $query = DB::table('chart_of_accounts')
            ->where('deleted', 'No')
            ->whereRaw('LOWER(account_name) = ?', [mb_strtolower($name)]);

        $parentId === null
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $parentId);

        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        if ($query->exists()) {
            $this->fail('account_name', 'An account with this name already exists under the same parent.');
        }
    }

    private function assertStatusChange(object $account, string $newStatus): void
    {
        if ((int) $account->layer_level === 1) {
            $this->fail('status', '1st layer accounts cannot be deactivated.');
        }

        if ($newStatus === 'Inactive') {
            if (DB::table('chart_of_accounts')
                ->where('parent_id', $account->id)
                ->where('deleted', 'No')
                ->where('status', 'Active')
                ->exists()) {
                $this->fail('status', 'Deactivate all child accounts first.');
            }

            if (abs($this->postedBalance((int) $account->id)) > 0.0001) {
                $this->fail('status', 'An account with a nonzero posted balance cannot be deactivated.');
            }

            return;
        }

        $parent = DB::table('chart_of_accounts')->where('id', $account->parent_id)->first();

        if (!$parent || $parent->status !== 'Active') {
            $this->fail('status', 'Activate the parent account first.');
        }
    }

    private function postedBalance(int $accountId): float
    {
        if (!Schema::hasTable('journal_details') || !Schema::hasTable('journals')) {
            return 0.0;
        }

        return (float) DB::table('journal_details as line')
            ->join('journals as entry', 'entry.id', '=', 'line.journal_id')
            ->where('line.account_id', $accountId)
            ->where('line.deleted', 'No')
            ->where('entry.deleted', 'No')
            ->selectRaw('COALESCE(SUM(line.debit), 0) - COALESCE(SUM(line.credit), 0) AS balance')
            ->value('balance');
    }

    /* ================================================================== */
    /*  DataTable cell renderers                                          */
    /* ================================================================== */

    private function codeCell(object $a): string
    {
        return '<span class="coa-code">' . e($a->account_code) . '</span>';
    }

    private function nameCell(object $a): string
    {
        $layer  = (int) $a->layer_level;
        $indent = $layer === 2 ? 'coa-indent-1' : ($layer === 3 ? 'coa-indent-2' : '');
        $icon   = $layer === 1 ? 'fa-folder' : ($layer === 2 ? 'fa-folder-open' : 'fa-file-alt');
        $weight = $layer === 1 ? 'fw-bold' : ($layer === 2 ? 'fw-semibold' : '');
        $system = (int) $a->is_system === 1
            ? '<span class="badge bg-light text-muted border ms-2">System</span>'
            : '';

        return '<div class="coa-name ' . $indent . '">'
            . '<i class="fas ' . $icon . ' me-2 text-muted"></i>'
            . '<span class="' . $weight . '">' . e($a->account_name) . '</span>'
            . $system . '</div>';
    }

    private function layerCell(int $layer): string
    {
        $class = [1 => 'coa-layer-1', 2 => 'coa-layer-2', 3 => 'coa-layer-3'][$layer] ?? '';

        return '<span class="badge coa-layer ' . $class . '">' . e(self::LAYERS[$layer] ?? 'Unknown') . '</span>';
    }

    private function typeCell(object $a): string
    {
        $color = self::TYPE_COLORS[$a->account_type] ?? 'secondary';

        return '<span class="badge bg-' . $color . '">' . e($a->account_type) . '</span>';
    }

    private function moneyCell(object $a): string
    {
        $amount = (float) ($a->opening_balance ?? 0);
        $class  = abs($amount) < 0.0001 ? 'text-muted' : 'fw-semibold';

        return '<span class="d-block text-end ' . $class . '">' . number_format($amount, 2) . '</span>';
    }

    private function dateCell(object $a): string
    {
        if (!$a->opening_balance_entry_datetime) {
            return '<span class="text-muted">-</span>';
        }

        return '<span class="small">'
            . e(Carbon::parse($a->opening_balance_entry_datetime)->format('d M Y, h:i A'))
            . '</span>';
    }

    private function statusCell(object $a): string
    {
        $color = $a->status === 'Active' ? 'success' : 'secondary';

        if ((int) $a->layer_level === 1) {
            return '<span class="badge bg-' . $color . '">' . e($a->status) . '</span>';
        }

        return '<span role="button" class="badge bg-' . $color . ' coa-status-toggle"'
            . ' data-id="' . (int) $a->id . '"'
            . ' data-name="' . e($a->account_name) . '"'
            . ' data-status="' . e($a->status) . '"'
            . ' title="Click to change status">' . e($a->status) . '</span>';
    }

    private function actionCell(object $a): string
    {
        $id    = (int) $a->id;
        $layer = (int) $a->layer_level;
        $items = [];

        // Add child: 1st layer -> 2nd layer, 2nd layer -> 3rd layer.
        if ($layer < 3 && $a->status === 'Active') {
            $items[] = ['label' => 'Add Child Account', 'icon' => 'fas fa-plus', 'class' => 'coa-add-child', 'attrs' => ['data-id' => $id]];
        }

        $items[] = ['label' => 'Edit', 'icon' => 'fas fa-edit', 'class' => 'coa-edit', 'attrs' => ['data-id' => $id]];

        if ($layer === 1 || (int) $a->is_system === 1) {
            $items[] = ['label' => 'Locked (cannot delete)', 'icon' => 'fas fa-lock', 'class' => 'disabled text-muted'];
        } else {
            $items[] = ['divider' => true];
            $items[] = ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger coa-delete', 'attrs' => ['data-id' => $id, 'data-name' => $a->account_name]];
        }

        return action_dropdown($items);
    }

    /* ================================================================== */
    /*  Response helpers                                                  */
    /* ================================================================== */

    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }

    private function validationResponse(ValidationException $e): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => collect($e->errors())->flatten()->first() ?? 'Please correct the validation errors.',
            'errors'  => $e->errors(),
        ], 422);
    }

    private function validatorResponse($validator): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => 'Please correct the validation errors.',
            'errors'  => $validator->errors(),
        ], 422);
    }

    private function messages(): array
    {
        return [
            'opening_balance_entry_datetime.required' => 'Opening balance date & time is required when an opening balance is entered.',
            'opening_balance.min'                     => 'Opening balance cannot be negative.',
        ];
    }

    private function ctx(Throwable $e, array $extra = []): array
    {
        return $extra + [
            'error' => $e->getMessage(),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
        ];
    }



    /** JSON: active 3rd layer accounts under a 2nd layer group (Journal / Bill / Transfer dropdowns). */
    public function ledgers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'parent_id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'A valid group is required.', 'data' => []], 422);
        }

        $rows = DB::table('chart_of_accounts')
            ->where('parent_id', (int) $request->query('parent_id'))
            ->where('layer_level', 3)
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name', 'current_balance']);

        return response()->json(['status' => true, 'data' => $rows]);
    }

    function getCoaChilds(Request $request){
        $childs=DB::table('chart_of_accounts')
            ->where('parent_id',$request->parent_id)
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);
        $html='<option value="">Select Account</option>';
        foreach($childs as $child){
            $html .='<option value="'.$child->id.'">'.$child->account_name.'</option>';
        }
        return $html;
    }
}
