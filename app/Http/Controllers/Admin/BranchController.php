<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\VoucherHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Branches, the branch the user signed in to (working branch) and the branches the user may see.
 *
 * session(SESSION_KEY) = loggedInBranchId: the WORKING branch, set once at login (AuthenticatedSessionController) and
 * never changed while signed in. New rows get branch_id from the database trigger (create_branches migration) via
 * @fpos_branch, which the SetBranch middleware sets to the working branch on every request.
 *
 * Visibility: a user permitted in one branch sees that branch; a user permitted in several branches (or all_branches)
 * sees all of them together in every list, report and dashboard (visibleIds()).
 *   - READS:   BranchController::scope($query, 'alias.branch_id')    → the visible branches.
 *   - CHANGES: BranchController::scopeOwn($query, 'alias.branch_id') → the working branch only. Editing, deleting,
 *     cancelling, returning, delivering, receiving or paying a record is allowed only in its own branch (stock and
 *     cash reversals must book in that branch); guardOwn() refuses other-branch records with a clear message.
 */
class BranchController extends Controller
{
    use VoucherHelperTrait;

    public const SESSION_KEY = 'loggedInBranchId';

    /**
     * Per-request memo: values live on the current request's attributes, so they never outlive the request (no stale
     * branch data in long-running processes or between tests). Console jobs share the one console request.
     */
    public static function memo(string $key, callable $make)
    {
        $attrs = app('request')->attributes;
        $key = 'fpos.branch.' . $key;
        if (!$attrs->has($key)) {
            $attrs->set($key, $make());
        }

        return $attrs->get($key);
    }

    /** Forget the per-request memo (after branch setup or user access changes). */
    public static function forget(): void
    {
        $attrs = app('request')->attributes;
        foreach (array_keys($attrs->all()) as $k) {
            if (str_starts_with($k, 'fpos.branch.')) {
                $attrs->remove($k);
            }
        }
    }

    /** Branch ids the signed-in user may sign in to (used by the login checks and user setup). */
    public static function allowedIds(): array
    {
        $user = Auth::user();
        if (!$user) {
            return self::memo('active', fn () => DB::table('branches')->where('deleted', 'No')->where('status', 'Active')->pluck('id')->map(fn ($id) => (int) $id)->all());
        }

        return self::memo('allowed.' . $user->id, fn () => self::allowedFor($user));
    }

    /** Branch ids a given user may sign in to / work in (no cache; used at login and in user setup). */
    public static function allowedFor(object $user): array
    {
        if (!self::accountActive($user)) {
            return []; // a deleted or inactive account may not work in any branch
        }
        $active = DB::table('branches')->where('deleted', 'No')->where('status', 'Active');
        if (($user->all_branches ?? 'No') === 'Yes') {
            return $active->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        $ids = DB::table('branch_user')->where('user_id', $user->id)->pluck('branch_id')->push($user->branch_id)->filter()->unique();

        return $active->whereIn('id', $ids->all())->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** False for a deleted or inactive user account (refused at login, signed out of open sessions by SetBranch). */
    public static function accountActive(object $user): bool
    {
        return ($user->deleted ?? 'No') !== 'Yes' && ($user->status ?? 'Active') === 'Active';
    }

    /** Active branches for the login form. */
    public static function loginOptions()
    {
        try {
            return DB::table('branches')->where('deleted', 'No')->where('status', 'Active')
                ->orderByDesc('is_main')->orderBy('name')->get(['id', 'name', 'code', 'address']);
        } catch (Throwable $e) {
            return collect();
        }
    }

    /** Null when the user may sign in to the chosen branch, otherwise the reason. */
    public static function loginProblem(object $user, ?int $branchId): ?string
    {
        if (!self::accountActive($user)) {
            return 'Your account is inactive. Ask an administrator to activate it.';
        }
        if (!$branchId) {
            return 'Choose the branch you are working in.';
        }

        return in_array($branchId, self::allowedFor($user), true)
            ? null
            : 'Your account is not allowed in ' . self::name($branchId) . '. Ask an administrator for access.';
    }

    /** The main (head office) branch id, read once per request. */
    public static function mainId(): ?int
    {
        return self::memo('main', fn () => ($id = DB::table('branches')->where('is_main', 'Yes')->orderBy('id')->value('id')) ? (int) $id : null);
    }

    /** The branch the user signed in to (null only outside a signed-in request, e.g. the scheduler). */
    public static function loggedInId(): ?int
    {
        $id = (int) session(self::SESSION_KEY);

        return $id ?: null;
    }

    /** The working branch (kept for older callers; same as loggedInId()). */
    public static function current(): ?int
    {
        return self::loggedInId();
    }

    /** Branch new records are saved to — the working branch (main branch for console / scheduled jobs). */
    public static function activeId(): ?int
    {
        return self::loggedInId() ?? self::mainId();
    }


    /**
     * Branch ids whose data the signed-in user sees: every active branch the user is permitted in (all active
     * branches for all_branches = Yes), always including the working branch. Cached per user + working branch.
     */
    public static function visibleIds(): array
    {
        return self::memo('visible.' . (Auth::id() ?? 0) . ':' . (self::loggedInId() ?? 0), function () {
            $user = Auth::user();
            $ids = $user ? self::allowedFor($user) : self::allowedIds();
            $own = self::loggedInId();
            if ($own && !in_array($own, $ids, true)) {
                $ids[] = $own; // SetBranch signs such a session out; keep the working branch visible meanwhile
            }
            sort($ids);

            return $ids ?: [0];
        });
    }

    /** True when the user sees more than one branch (lists then show a Branch column). */
    public static function multiBranch(): bool
    {
        return count(self::visibleIds()) > 1;
    }

    /** True when the given branch is one the user may see. */
    public static function sees($branchId): bool
    {
        return in_array((int) $branchId, self::visibleIds(), true);
    }

    /** True when the given branch is the working branch (records the user may change). */
    public static function owns($branchId): bool
    {
        return (int) $branchId === (int) self::activeId();
    }

    /** True when the main branch is among the visible ones (a party's opening balance belongs to the main branch). */
    public static function mainVisible(): bool
    {
        return self::sees(self::mainId());
    }

    /** Kept for older callers. */
    public static function canSeeAll(): bool
    {
        return self::multiBranch();
    }

    /** True when the working branch is the main branch (head-office screens). */
    public static function isMainBranch(): bool
    {
        return self::current() !== null && self::current() === (int) self::mainId();
    }

    /** READS: limit a query to the branches the user sees. */
    public static function scope($query, string $column = 'branch_id')
    {
        $ids = self::visibleIds();

        return count($ids) === 1 ? $query->where($column, $ids[0]) : $query->whereIn($column, $ids);
    }

    /** CHANGES: limit a query to the working branch (records the user may edit, delete, cancel, return, pay...). */
    public static function scopeOwn($query, string $column = 'branch_id')
    {
        return $query->where($column, (int) self::activeId());
    }

    /** The message shown when a record of another (visible) branch is about to be changed. */
    public static function otherBranchMessage($branchId): string
    {
        $name = self::name((int) $branchId);

        return 'This record belongs to ' . $name . '. Sign in to ' . $name . ' to change it.';
    }

    /**
     * Before changing a record: aborts 403 with otherBranchMessage() when the row (or table + id) belongs to a
     * visible branch other than the working one. A row the user cannot see at all is left to the caller's own
     * (working-branch) lookup, which answers "not found".
     */
    public static function guardOwn($rowOrTable, $id = null, string $column = 'branch_id'): void
    {
        if ($problem = self::otherBranchProblem($rowOrTable, $id, $column)) {
            abort(403, $problem);
        }
    }

    /** Same check as guardOwn(), returning the message (null when the record may be changed or is not visible). */
    public static function otherBranchProblem($rowOrTable, $id = null, string $column = 'branch_id'): ?string
    {
        $branchId = is_string($rowOrTable)
            ? DB::table($rowOrTable)->where('id', (int) $id)->value($column)
            : ($rowOrTable->{$column} ?? null);

        return $branchId !== null && !self::owns($branchId) && self::sees($branchId) ? self::otherBranchMessage($branchId) : null;
    }

    /**
     * Validation rule for an account money is paid from / into: shared ledgers, or the working branch's own ledgers
     * (never another branch's cash drawer or bank ledger, even one the user can see).
     */
    public static function ownLedgerRule()
    {
        return Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('deleted', 'No')
            ->where(fn ($b) => $b->whereNull('branch_id')->orWhere('branch_id', (int) self::activeId())));
    }


    /** id => name of the visible branches (Branch columns, badge tooltip). */
    public static function visibleNames(): array
    {
        return self::memo('names.' . implode(',', self::visibleIds()), fn () => DB::table('branches')->whereIn('id', self::visibleIds())
            ->orderByDesc('is_main')->orderBy('name')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all());
    }

    /** "Dhanmondi Branch" or "Dhanmondi Branch, Uttara Branch" (report headers); "All branches" when it is every active one. */
    public static function visibleLabel(): string
    {
        $names = self::visibleNames();
        if (count($names) > 1 && count($names) === DB::table('branches')->where('deleted', 'No')->where('status', 'Active')->count()) {
            return 'All branches';
        }

        return implode(', ', $names) ?: self::name(self::current());
    }

    /** Short label for a Branch column (empty when the user sees one branch only). */
    public static function label($branchId): string
    {
        return self::multiBranch() ? (self::visibleNames()[(int) $branchId] ?? self::name((int) $branchId)) : '';
    }

    public static function name(?int $id): string
    {
        return $id ? (string) DB::table('branches')->where('id', $id)->value('name') : '-';
    }

    /* ------------------------------------------------------------------ */
    /*  Branch setup                                                       */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        return view('admin.setting.branches', [
            'users' => DB::table('users')->where('deleted', 'No')->orderBy('name')->get(['id', 'name', 'email', 'branch_id', 'all_branches']),
        ]);
    }

    public function list()
    {
        $counts = fn ($table) => DB::table($table)->groupBy('branch_id')->selectRaw('branch_id, COUNT(*) n')->pluck('n', 'branch_id');
        $sales = $counts('sales');
        $staff = DB::table('employees')->where('deleted', 'No')->groupBy('branch_id')->selectRaw('branch_id, COUNT(*) n')->pluck('n', 'branch_id');

        $rows = DB::table('branches')->where('deleted', 'No')->orderByDesc('is_main')->orderBy('name')->get()
            ->map(function ($b) use ($sales, $staff) {
                $b->sales = (int) ($sales[$b->id] ?? 0);
                $b->employees = (int) ($staff[$b->id] ?? 0);
                $b->users = DB::table('users')->where('deleted', 'No')
                    ->where(fn ($q) => $q->where('branch_id', $b->id)->orWhereIn('id', DB::table('branch_user')->where('branch_id', $b->id)->pluck('user_id')))
                    ->pluck('name')->all();

                return $b;
            });

        return response()->json(['status' => true, 'data' => $rows]);
    }

    public function save(Request $request)
    {
        $id = $request->input('id') ? (int) $request->input('id') : null;
        $validator = Validator::make($request->all(), [
            'name'    => ['required', 'string', 'max:120', Rule::unique('branches', 'name')->where('deleted', 'No')->ignore($id)],
            'code'    => ['nullable', 'string', 'max:20', Rule::unique('branches', 'code')->where('deleted', 'No')->ignore($id)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:50'],
            'email'   => ['nullable', 'email', 'max:120'],
            'status'  => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $data = $validator->validated();
            foreach (['code', 'address', 'phone', 'email'] as $k) {
                $data[$k] = $this->nullIfBlank($data[$k] ?? null);
            }
            if ($id) {
                abort_if($data['status'] === 'Inactive' && DB::table('branches')->where('id', $id)->value('is_main') === 'Yes', 422, 'The main branch cannot be inactive.');
                DB::table('branches')->where('id', $id)->update($data + ['updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now()]);
            } else {
                DB::table('branches')->insert($data + ['is_main' => 'No', 'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }

            self::forget();

            return response()->json(['status' => true, 'message' => 'Branch saved.']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Branch save error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the branch.'], 500);
        }
    }

    public function setMain(int $id)
    {
        DB::transaction(function () use ($id) {
            DB::table('branches')->update(['is_main' => 'No']);
            DB::table('branches')->where('id', $id)->update(['is_main' => 'Yes', 'status' => 'Active']);
            self::forget();
        });

        return response()->json(['status' => true, 'message' => 'Main branch changed.']);
    }

    public function delete(int $id)
    {
        $b = DB::table('branches')->where('id', $id)->first();
        if (!$b || $b->is_main === 'Yes') {
            return response()->json(['status' => false, 'message' => 'The main branch cannot be removed.'], 422);
        }
        foreach (['sales', 'purchases', 'stocks', 'payments', 'employees'] as $t) {
            if (DB::table($t)->where('branch_id', $id)->exists()) {
                return response()->json(['status' => false, 'message' => 'This branch has ' . $t . '. Set it Inactive instead.'], 422);
            }
        }
        DB::table('branches')->where('id', $id)->update($this->deletedColumns());

        return response()->json(['status' => true, 'message' => 'Branch removed.']);
    }

    /** Which branches a user works in. */
    public function saveAccess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'      => ['required', 'integer', Rule::exists('users', 'id')],
            'branch_id'    => ['required', 'integer', Rule::exists('branches', 'id')->where('deleted', 'No')],
            'all_branches' => ['required', Rule::in(['Yes', 'No'])],
            'branches'     => ['nullable', 'array'],
            'branches.*'   => ['integer', Rule::exists('branches', 'id')->where('deleted', 'No')],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        DB::transaction(function () use ($request) {
            $userId = (int) $request->input('user_id');
            DB::table('users')->where('id', $userId)->update([
                'branch_id' => (int) $request->input('branch_id'), 'all_branches' => $request->input('all_branches'),
            ]);
            DB::table('branch_user')->where('user_id', $userId)->delete();
            foreach (collect($request->input('branches', []))->push($request->input('branch_id'))->unique() as $b) {
                DB::table('branch_user')->insert(['user_id' => $userId, 'branch_id' => (int) $b]);
            }
        });

        self::forget();

        return response()->json(['status' => true, 'message' => 'Branch access saved.']);
    }

    public function access(int $userId)
    {
        return response()->json(['status' => true, 'branches' => DB::table('branch_user')->where('user_id', $userId)->pluck('branch_id')]);
    }
}
