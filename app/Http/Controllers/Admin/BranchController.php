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
 * Branches and the branch the user signed in to.
 *
 * session(SESSION_KEY) = loggedInBranchId: set once at login (AuthenticatedSessionController), never changed while
 * signed in. Every screen, list and report shows only that branch; to see another branch the user signs out and
 * signs in to it (if allowed). New rows get branch_id from the database trigger (create_branches migration) via
 * @fpos_branch, which the SetBranch middleware sets to the same id on every request.
 *
 * Reading: BranchController::scope($query, 'alias.branch_id') limits a query to the logged-in branch.
 */
class BranchController extends Controller
{
    use VoucherHelperTrait;

    public const SESSION_KEY = 'loggedInBranchId';

    private static ?array $allowedCache = null;

    /** Branch ids the signed-in user may sign in to (used by the login checks and user setup). */
    public static function allowedIds(): array
    {
        if (self::$allowedCache !== null) {
            return self::$allowedCache;
        }
        $user = Auth::user();
        if (!$user) {
            return DB::table('branches')->where('deleted', 'No')->where('status', 'Active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return self::$allowedCache = self::allowedFor($user);
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

    public static function mainId(): ?int
    {
        return DB::table('branches')->where('is_main', 'Yes')->orderBy('id')->value('id');
    }

    /** The branch the user signed in to (null only outside a signed-in request, e.g. the scheduler). */
    public static function loggedInId(): ?int
    {
        $id = (int) session(self::SESSION_KEY);

        return $id ?: null;
    }

    /** Branch whose data is shown — always the logged-in branch. */
    public static function current(): ?int
    {
        return self::loggedInId();
    }

    /** Branch new records are saved to — the logged-in branch (main branch for console / scheduled jobs). */
    public static function activeId(): ?int
    {
        return self::loggedInId() ?? self::mainId();
    }

    /** Kept for older callers: there is no "all branches" view any more. */
    public static function canSeeAll(): bool
    {
        return false;
    }

    public static function isMainBranch(): bool
    {
        return self::current() !== null && self::current() === (int) self::mainId();
    }

    /** Limit a query to the logged-in branch (all active branches only outside a signed-in request). */
    public static function scope($query, string $column = 'branch_id')
    {
        $id = self::current();

        return $id ? $query->where($column, $id) : $query->whereIn($column, self::allowedIds() ?: [0]);
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

            self::$allowedCache = null;

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

        return response()->json(['status' => true, 'message' => 'Branch access saved.']);
    }

    public function access(int $userId)
    {
        return response()->json(['status' => true, 'branches' => DB::table('branch_user')->where('user_id', $userId)->pluck('branch_id')]);
    }
}
