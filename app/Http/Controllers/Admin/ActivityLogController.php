<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Activity log: who changed what.
 *
 * Static API:
 *   ActivityLogController::record($request, $response)   - called by the LogActivity middleware
 *   ActivityLogController::event('Logged in', $userId)    - login / logout / custom events
 *   ActivityLogController::prune(?int $days)               - deletes rows older than the retention setting
 */
class ActivityLogController extends Controller
{
    /** Input keys never stored (matched case-insensitively as substrings). */
    private const SECRET_KEYS = ['password', 'token', 'secret', 'api_key', 'apikey', 'smtp', 'mail_password', 'sms_api', 'card_number', 'cvv'];
    private const SECRET_EXACT = ['pin', 'otp', 'code_verifier'];

    /** Route name endings that only read data (POST lists, searches): not logged. */
    private const SKIP_SUFFIXES = ['.list', '.data', '.datatable', '.search', '.preview', '.lookup', '.calculate', '.info', '.balance'];

    private const MAX_VALUE = 300;
    private const MAX_JSON = 60000;

    /* ------------------------------------------------------------------ */
    /* Recording                                                           */
    /* ------------------------------------------------------------------ */

    /** Records an authenticated non-GET request. Never throws. */
    public static function record(Request $request, $response): void
    {
        try {
            $route = $request->route();
            $routeName = $route ? $route->getName() : null;
            if ($routeName && (Str::endsWith($routeName, self::SKIP_SUFFIXES) || in_array($routeName, ['login', 'logout'], true))) {
                return;
            }
            if (Str::startsWith((string) $routeName, 'admin.system.activity.list')) {
                return;
            }

            [$subjectType, $subjectId] = self::subject($request, $response, $routeName);

            self::insert([
                'method'       => strtoupper($request->method()),
                'route_name'   => $routeName,
                'url'          => Str::limit('/' . ltrim($request->path(), '/'), 495, ''),
                'action'       => self::label($routeName, $request->method(), $request->path()),
                'subject_type' => $subjectType,
                'subject_id'   => $subjectId,
                'changes'      => self::changes($request),
                'status_code'  => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
            ], $request);
        } catch (Throwable $e) {
            Log::warning('Activity log could not be written.', ['error' => $e->getMessage()]);
        }
    }

    /** Logs a named event (login, logout, backup, ...). Never throws. */
    public static function event(string $action, ?int $userId = null, array $changes = [], ?string $routeName = null): void
    {
        try {
            $request = request();
            self::insert([
                'user_id'     => $userId,
                'method'      => $request ? strtoupper($request->method()) : 'CLI',
                'route_name'  => $routeName ?? ($request && $request->route() ? $request->route()->getName() : null),
                'url'         => $request ? Str::limit('/' . ltrim($request->path(), '/'), 495, '') : null,
                'action'      => Str::limit($action, 188),
                'changes'     => $changes ? self::encode(self::clean($changes)) : null,
                'status_code' => null,
            ], $request);
        } catch (Throwable $e) {
            Log::warning('Activity event could not be written.', ['error' => $e->getMessage()]);
        }
    }

    /** Deletes log rows older than $days (default: the retention setting). Returns the number deleted. */
    public static function prune(?int $days = null): int
    {
        $days = $days ?? (int) SystemSettingController::get('activity_retention_days', 90);
        if ($days < 1) {
            return 0;
        }

        return DB::table('activity_logs')->where('created_at', '<', now()->subDays($days))->delete();
    }

    /** Readable labels for routes whose name does not end in a verb. */
    private const LABELS = [
        'admin.system.email-sms.mail'      => 'Saved email settings',
        'admin.system.email-sms.mail.test' => 'Sent test email',
        'admin.system.email-sms.sms'       => 'Saved SMS settings',
        'admin.system.email-sms.sms.test'  => 'Sent test SMS',
        'admin.system.backups.run'         => 'Created backup',
        'admin.system.backups.settings'    => 'Saved backup settings',
        'admin.system.backups.delete'      => 'Deleted backup',
        'admin.system.activity.retention'  => 'Saved activity log retention',
        'admin.system.activity.prune'      => 'Pruned activity log',
        'admin.system.sms.due.send'        => 'Sent due reminder SMS',
        'admin.branch.switch'              => 'Switched branch',
        'admin.settings.update'            => 'Updated general settings',
    ];

    private const VERBS = [
        'store' => 'Created', 'create' => 'Created', 'add' => 'Created', 'insert' => 'Created',
        'update' => 'Updated', 'edit' => 'Updated', 'save' => 'Saved', 'change' => 'Changed',
        'destroy' => 'Deleted', 'delete' => 'Deleted', 'remove' => 'Deleted', 'trash' => 'Deleted',
        'restore' => 'Restored', 'status' => 'Changed status of', 'toggle' => 'Toggled',
        'approve' => 'Approved', 'reject' => 'Rejected', 'cancel' => 'Cancelled', 'void' => 'Voided',
        'import' => 'Imported', 'export' => 'Exported', 'send' => 'Sent', 'pay' => 'Paid',
        'repay' => 'Repaid', 'reschedule' => 'Rescheduled', 'post' => 'Posted', 'process' => 'Processed',
        'generate' => 'Generated', 'upload' => 'Uploaded', 'convert' => 'Converted', 'return' => 'Returned',
        'reverse' => 'Reversed', 'close' => 'Closed', 'open' => 'Opened', 'assign' => 'Assigned',
    ];

    /**
     * "admin.employee-loans.repay" + POST -> "Repaid employee loan";
     * a last segment that is not a known verb: "admin.leave.calendar.rules" -> "Rules (calendar)".
     */
    public static function label(?string $routeName, string $method, string $path = ''): string
    {
        if ($routeName && isset(self::LABELS[$routeName])) {
            return self::LABELS[$routeName];
        }
        $method = strtoupper($method);
        $parts = $routeName ? explode('.', $routeName) : array_values(array_filter(explode('/', $path), fn ($p) => $p !== '' && !ctype_digit($p)));
        $parts = array_values(array_filter($parts, fn ($p) => !in_array($p, ['admin', 'system'], true)));

        $last = count($parts) > 1 ? array_pop($parts) : ($method === 'DELETE' ? 'delete' : 'save');
        $module = self::words(end($parts) ?: 'record');
        $verbKey = strtolower($last);

        if ($method === 'DELETE') {
            $verb = 'Deleted';
        } elseif (isset(self::VERBS[$verbKey])) {
            $verb = self::VERBS[$verbKey];
        } elseif ($method === 'PUT' || $method === 'PATCH') {
            $verb = 'Updated';
        } else {
            return Str::limit(ucfirst(self::words($last, false)) . ' (' . $module . ')', 188);
        }

        return Str::limit($verb . ' ' . $module, 188);
    }

    /** "employee-loans" -> "employee loan" (no singular for short words / acronyms such as "sms"). */
    private static function words(string $segment, bool $singular = true): string
    {
        $words = explode(' ', strtolower(trim(preg_replace('/[\s_-]+/', ' ', Str::snake($segment)))));
        $lastWord = end($words);
        if ($singular && strlen($lastWord) > 3 && preg_match('/s$/', $lastWord) && !preg_match('/(us|is|ss|ms)$/', $lastWord)) {
            $words[count($words) - 1] = Str::singular($lastWord);
        }

        return implode(' ', $words);
    }

    private static function insert(array $row, ?Request $request): void
    {
        $branchId = null;
        try {
            if (Auth::check()) {
                $branchId = BranchController::activeId();
            }
        } catch (Throwable $e) {
            $branchId = null;
        }

        DB::table('activity_logs')->insert($row + [
            'user_id'    => Auth::id(),
            'branch_id'  => $branchId,
            'ip'         => $request ? $request->ip() : null,
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            'created_at' => now(),
        ]);
    }

    /** First numeric route parameter, else an id in the JSON response. */
    private static function subject(Request $request, $response, ?string $routeName): array
    {
        $type = null;
        if ($routeName) {
            $parts = array_values(array_filter(explode('.', $routeName), fn ($p) => !in_array($p, ['admin', 'system'], true)));
            if (count($parts) > 1) {
                array_pop($parts);
            }
            $type = Str::studly(self::words(end($parts) ?: 'record'));
        }

        $route = $request->route();
        foreach ($route ? $route->parameters() : [] as $name => $value) {
            if (is_object($value) && method_exists($value, 'getKey')) {
                return [class_basename($value), (int) $value->getKey()];
            }
            if (is_scalar($value) && ctype_digit((string) $value)) {
                return [$type ?? Str::studly($name), (int) $value];
            }
        }

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            $id = $data['id'] ?? ($data['data']['id'] ?? null);
            if (is_scalar($id) && ctype_digit((string) $id)) {
                return [$type, (int) $id];
            }
        }

        return [$type, null];
    }

    private static function changes(Request $request): ?string
    {
        $input = $request->except(['_token', '_method']);
        foreach ($request->allFiles() as $key => $file) {
            $input[$key] = self::fileNames($file);
        }

        return $input ? self::encode(self::clean($input)) : null;
    }

    private static function fileNames($file)
    {
        if (is_array($file)) {
            return array_map([self::class, 'fileNames'], $file);
        }

        return $file instanceof UploadedFile ? '[file] ' . $file->getClientOriginalName() : '[file]';
    }

    private static function clean(array $data, int $depth = 0): array
    {
        $out = [];
        $count = 0;
        foreach ($data as $key => $value) {
            if (++$count > 200) {
                $out['…'] = (count($data) - 200) . ' more';
                break;
            }
            $k = strtolower((string) $key);
            if (in_array($k, self::SECRET_EXACT, true)) {
                $value = '[hidden]';
            }
            foreach (self::SECRET_KEYS as $secret) {
                if (str_contains($k, $secret)) {
                    $value = '[hidden]';
                    break;
                }
            }
            if (is_array($value)) {
                $out[$key] = $depth >= 4 ? '[nested]' : self::clean($value, $depth + 1);
            } elseif (is_string($value)) {
                $out[$key] = mb_strlen($value) > self::MAX_VALUE ? mb_substr($value, 0, self::MAX_VALUE) . '…' : $value;
            } elseif (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            } else {
                $out[$key] = '[' . get_debug_type($value) . ']';
            }
        }

        return $out;
    }

    private static function encode(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (strlen($json) > self::MAX_JSON) {
            $json = json_encode(['_truncated' => 'Input too large to store (' . number_format(strlen($json)) . ' bytes).',
                'keys' => implode(', ', array_slice(array_keys($data), 0, 50))]);
        }

        return $json;
    }

    /* ------------------------------------------------------------------ */
    /* Page                                                                */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        $users = DB::table('users')->orderBy('name')->get(['id', 'name']);
        $modules = [];
        try {
            $modules = BranchController::scope(DB::table('activity_logs'), 'branch_id')->whereNotNull('route_name')
                ->selectRaw("DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(route_name, 'admin.system.', 'admin.'), '.', 2), '.', -1) AS m")
                ->orderBy('m')->pluck('m')->filter()->values()->all();
        } catch (Throwable $e) {
            Log::warning('Activity modules error', ['error' => $e->getMessage()]);
        }
        $retention = (int) SystemSettingController::get('activity_retention_days', 90);

        return view('admin.system.activity-log', compact('users', 'modules', 'retention'));
    }

    /** DataTables server-side source. */
    public function list(Request $request)
    {
        try {
            $base = BranchController::scope(DB::table('activity_logs as a'), 'a.branch_id')->leftJoin('users as u', 'u.id', '=', 'a.user_id');
            $total = (clone $base)->count();

            $q = (clone $base)
                ->when($request->filled('from'), fn ($q) => $q->where('a.created_at', '>=', Carbon::parse($request->query('from'))->startOfDay()))
                ->when($request->filled('to'), fn ($q) => $q->where('a.created_at', '<=', Carbon::parse($request->query('to'))->endOfDay()))
                ->when($request->filled('user_id'), fn ($q) => $q->where('a.user_id', (int) $request->query('user_id')))
                ->when($request->filled('module'), function ($q) use ($request) {
                    $m = str_replace(['%', '_'], ['\%', '\_'], (string) $request->query('module'));
                    $q->where(fn ($w) => $w->where('a.route_name', 'like', $m . '.%')->orWhere('a.route_name', 'like', 'admin.' . $m . '.%')
                        ->orWhere('a.route_name', 'like', 'admin.system.' . $m . '.%')->orWhere('a.route_name', $m));
                })
                ->when($request->filled('method'), fn ($q) => $q->where('a.method', strtoupper((string) $request->query('method'))));

            $term = trim((string) ($request->query('q') ?: data_get($request->query('search'), 'value')));
            if ($term !== '') {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
                $q->where(fn ($w) => $w->where('a.action', 'like', $like)->orWhere('a.url', 'like', $like)
                    ->orWhere('a.changes', 'like', $like)->orWhere('u.name', 'like', $like)->orWhere('a.ip', 'like', $like));
            }
            $filtered = (clone $q)->count();

            $start = max(0, (int) $request->query('start', 0));
            $length = (int) $request->query('length', 25);
            $length = $length < 1 ? 25 : min($length, 200);

            $rows = $q->orderByDesc('a.id')->offset($start)->limit($length)
                ->get(['a.id', 'a.created_at', 'a.method', 'a.route_name', 'a.url', 'a.action', 'a.subject_type', 'a.subject_id', 'a.ip', 'a.status_code', 'a.branch_id', 'u.name as user_name']);

            $methodBadge = ['POST' => 'bg-primary', 'PUT' => 'bg-info text-dark', 'PATCH' => 'bg-info text-dark', 'DELETE' => 'bg-danger'];

            return response()->json([
                'draw'            => (int) $request->query('draw', 0),
                'recordsTotal'    => $total,
                'recordsFiltered' => $filtered,
                'data'            => $rows->values()->map(function ($r, $i) use ($start, $methodBadge) {
                    $code = (int) $r->status_code;
                    $codeBadge = !$code ? '' : '<span class="badge ' . ($code >= 500 ? 'bg-danger' : ($code >= 400 ? 'bg-warning text-dark' : 'bg-success')) . '">' . $code . '</span>';

                    return [
                        $start + $i + 1,
                        e(Carbon::parse($r->created_at)->format('d M Y')) . '<div class="text-muted small">' . e(Carbon::parse($r->created_at)->format('h:i:s A')) . '</div>',
                        e($r->user_name ?? 'System') . branch_tag($r->branch_id),
                        '<span class="fw-semibold">' . e($r->action) . '</span>'
                            . ($r->subject_id ? ' <span class="text-muted small">#' . (int) $r->subject_id . '</span>' : '')
                            . '<div class="text-muted small text-break">' . e($r->route_name ?? $r->url) . '</div>',
                        '<span class="badge ' . ($methodBadge[$r->method] ?? 'bg-secondary') . '">' . e($r->method) . '</span> ' . $codeBadge,
                        e($r->ip),
                        '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="Activity.view(' . (int) $r->id . ')" title="Details"><i class="fas fa-eye"></i></button>',
                    ];
                }),
            ]);
        } catch (Throwable $e) {
            Log::error('Activity log list error', ['error' => $e->getMessage()]);

            return response()->json(['draw' => (int) $request->query('draw', 0), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'message' => 'Unable to load the activity log.'], 500);
        }
    }

    public function show(int $id)
    {
        $row = BranchController::scope(DB::table('activity_logs as a'), 'a.branch_id')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoin('branches as b', 'b.id', '=', 'a.branch_id')
            ->where('a.id', $id)->first(['a.*', 'u.name as user_name', 'b.name as branch_name']);
        if (!$row) {
            return response()->json(['status' => false, 'message' => 'Log entry not found.'], 404);
        }
        $row->changes = $row->changes ? json_decode($row->changes, true) : null;
        $row->created_at = Carbon::parse($row->created_at)->format('d M Y h:i:s A');

        return response()->json(['status' => true, 'data' => $row]);
    }

    public function saveRetention(Request $request)
    {
        $request->validate(['activity_retention_days' => ['required', 'integer', 'between:1,3650']],
            ['activity_retention_days.required' => 'Enter how many days to keep the log.']);
        SystemSettingController::put(['activity_retention_days' => (int) $request->input('activity_retention_days')]);

        return response()->json(['status' => true, 'message' => 'Retention saved: logs older than ' . (int) $request->input('activity_retention_days') . ' days can be pruned.']);
    }

    public function prunePost()
    {
        try {
            $n = self::prune();

            return response()->json(['status' => true, 'message' => number_format($n) . ' old log ' . Str::plural('entry', $n) . ' deleted.']);
        } catch (Throwable $e) {
            Log::error('Activity prune error', ['error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => 'Unable to prune the log.'], 500);
        }
    }
}
