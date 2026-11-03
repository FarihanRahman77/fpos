<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * BulkSMSBD SMS gateway, templates, due reminders and the SMS log.
 *
 * Static API:
 *   SmsController::send(string $phone, string $message, string $type = 'General', ?int $partyId = null): array
 *       -> ['ok' => bool, 'message' => string]; never throws, every attempt is written to sms_logs.
 *   SmsController::render(string $templateKey, array $vars): string      template keys: invoice_sms, due_reminder
 *   SmsController::enabled(string $feature): bool                         features:      invoice_sms, due_reminder
 *   SmsController::normalize(string $phone): ?string                      01XXXXXXXXX -> 8801XXXXXXXXX
 *   SmsController::balance(): array                                       ['ok' => bool, 'balance' => ?float, 'message' => string]
 */
class SmsController extends Controller
{
    private const SEND_URL = 'http://bulksmsbd.net/api/smsapi';
    private const BALANCE_URL = 'http://bulksmsbd.net/api/getBalanceApi';

    public const TEMPLATES = [
        'invoice_sms'  => ['label' => 'Invoice SMS', 'placeholders' => ['customer', 'invoice', 'amount', 'paid', 'due', 'company']],
        'due_reminder' => ['label' => 'Due reminder', 'placeholders' => ['customer', 'due', 'company']],
    ];

    /** BulkSMSBD error codes (response_code). */
    private const ERRORS = [
        1001 => 'Invalid number',
        1002 => 'Sender ID is not correct or is disabled',
        1003 => 'Required fields are missing (contact the provider)',
        1005 => 'Provider internal error',
        1006 => 'Balance validity not available',
        1007 => 'Insufficient balance',
        1011 => 'User ID not found',
        1012 => 'Masking SMS must be sent in Bengali',
        1013 => 'Sender ID has no gateway for this API key',
        1014 => 'Sender type name not found for this sender ID',
        1015 => 'Sender ID has no valid gateway for this API key',
        1016 => 'Sender type price info not found',
        1017 => 'Sender type price info not found',
        1018 => 'The account owner is disabled',
        1019 => 'The sender type price of this account is disabled',
        1020 => 'The parent of this account was not found',
        1021 => 'The parent price of this account was not found',
        1031 => 'Your account is not verified, contact the provider',
        1032 => 'This server IP is not whitelisted in BulkSMSBD',
    ];

    /* ------------------------------------------------------------------ */
    /* Static API                                                          */
    /* ------------------------------------------------------------------ */

    public static function send(string $phone, string $message, string $type = 'General', ?int $partyId = null): array
    {
        $number = null;
        try {
            $message = trim($message);
            $number = self::normalize($phone);
            $s = SystemSettingController::all();

            if (($s['sms_enabled'] ?? '0') !== '1') {
                return self::log($phone, $message, $type, $partyId, false, 'SMS is turned off in System > Email & SMS.');
            }
            if (trim((string) $s['sms_api_key']) === '' || trim((string) $s['sms_sender_id']) === '') {
                return self::log($phone, $message, $type, $partyId, false, 'SMS API key or sender ID is not set.');
            }
            if (!$number) {
                return self::log($phone, $message, $type, $partyId, false, 'Invalid mobile number: ' . ($phone === '' ? '(empty)' : $phone));
            }
            if ($message === '') {
                return self::log($number, $message, $type, $partyId, false, 'Empty message.');
            }

            $res = Http::connectTimeout(5)->timeout(12)->acceptJson()->asForm()->post(self::SEND_URL, [
                'api_key'  => $s['sms_api_key'],
                'type'     => 'text',
                'number'   => $number,
                'senderid' => $s['sms_sender_id'],
                'message'  => $message,
            ]);

            $body = $res->json();
            $code = is_array($body) ? (int) ($body['response_code'] ?? 0) : 0;
            if ($res->successful() && $code === 202) {
                return self::log($number, $message, $type, $partyId, true, $res->body(), 'SMS sent to ' . $number . '.');
            }

            $reason = is_array($body)
                ? ($body['error_message'] ?? null) ?: (self::ERRORS[$code] ?? null) ?: ($body['success_message'] ?? null)
                : null;
            $reason = $reason ?: ('HTTP ' . $res->status() . ' ' . Str::limit(strip_tags($res->body()), 150));

            return self::log($number, $message, $type, $partyId, false, $res->body() ?: $reason, 'SMS failed: ' . $reason . ($code ? " (code {$code})" : ''));
        } catch (Throwable $e) {
            $error = 'SMS failed: ' . Str::limit(preg_replace('/\s+/', ' ', $e->getMessage()), 250);
            try {
                return self::log($number ?? $phone, $message, $type, $partyId, false, $e->getMessage(), $error);
            } catch (Throwable $e2) {
                Log::error('SMS log error', ['error' => $e2->getMessage()]);

                return ['ok' => false, 'message' => $error];
            }
        }
    }

    /** Fills {placeholders}; {company} defaults to the company name. Numeric amount/paid/due are formatted to 2 decimals. */
    public static function render(string $templateKey, array $vars): string
    {
        $template = (string) SystemSettingController::get('sms_tpl_' . $templateKey, SystemSettingController::DEFAULTS['sms_tpl_' . $templateKey] ?? '');
        if (!array_key_exists('company', $vars)) {
            $vars['company'] = self::companyName();
        }
        $replace = [];
        foreach ($vars as $key => $value) {
            if (in_array($key, ['amount', 'paid', 'due'], true) && is_numeric($value)) {
                $value = number_format((float) $value, 2);
            }
            $replace['{' . $key . '}'] = (string) $value;
        }

        return trim(strtr($template, $replace));
    }

    /** SMS is on, the gateway is configured and the feature (invoice_sms, due_reminder) is switched on. */
    public static function enabled(string $feature): bool
    {
        try {
            $s = SystemSettingController::all();

            return ($s['sms_enabled'] ?? '0') === '1'
                && trim((string) $s['sms_api_key']) !== ''
                && trim((string) $s['sms_sender_id']) !== ''
                && ($s['sms_' . $feature . '_enabled'] ?? '0') === '1';
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Bangladeshi mobile numbers -> 8801XXXXXXXXX; null when not a valid BD mobile. */
    public static function normalize(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '00880')) {
            $digits = substr($digits, 2);
        }
        if (preg_match('/^01[3-9]\d{8}$/', $digits)) {
            return '88' . $digits;
        }
        if (preg_match('/^1[3-9]\d{8}$/', $digits)) {
            return '880' . $digits;
        }
        if (preg_match('/^8801[3-9]\d{8}$/', $digits)) {
            return $digits;
        }

        return null;
    }

    public static function balance(): array
    {
        try {
            $key = (string) SystemSettingController::get('sms_api_key', '');
            if ($key === '') {
                return ['ok' => false, 'balance' => null, 'message' => 'Save the BulkSMSBD API key first.'];
            }
            $res = Http::connectTimeout(5)->timeout(10)->acceptJson()->get(self::BALANCE_URL, ['api_key' => $key]);
            $body = $res->json();
            if ($res->successful() && is_array($body) && isset($body['balance'])) {
                return ['ok' => true, 'balance' => (float) $body['balance'], 'message' => 'SMS balance: ' . number_format((float) $body['balance'], 2) . ' BDT'];
            }
            $code = is_array($body) ? (int) ($body['response_code'] ?? 0) : 0;
            $reason = (is_array($body) ? ($body['error_message'] ?? null) : null) ?: (self::ERRORS[$code] ?? null)
                ?: ('HTTP ' . $res->status() . ' ' . Str::limit(strip_tags($res->body()), 150));

            return ['ok' => false, 'balance' => null, 'message' => 'Balance check failed: ' . $reason . ($code ? " (code {$code})" : '')];
        } catch (Throwable $e) {
            return ['ok' => false, 'balance' => null, 'message' => 'Balance check failed: ' . Str::limit($e->getMessage(), 250)];
        }
    }

    private static function log(string $phone, string $message, string $type, ?int $partyId, bool $ok, string $response, ?string $userMessage = null): array
    {
        $branchId = null;
        try {
            $branchId = Auth::check() ? BranchController::activeId() : null;
        } catch (Throwable $e) {
            $branchId = null;
        }
        try {
            DB::table('sms_logs')->insert([
                'phone'      => Str::limit($phone, 30, ''),
                'message'    => $message,
                'type'       => Str::limit($type, 50, ''),
                'party_id'   => $partyId,
                'status'     => $ok ? 'Sent' : 'Failed',
                'response'   => Str::limit($response, 2000),
                'user_id'    => Auth::id(),
                'branch_id'  => $branchId,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('SMS log insert error', ['error' => $e->getMessage()]);
        }

        return ['ok' => $ok, 'message' => $userMessage ?? $response];
    }

    private static function companyName(): string
    {
        try {
            $s = DB::table('settings')->where('deleted', 'No')->first(['company_short_name', 'company_name']);

            return (string) (($s->company_short_name ?? null) ?: ($s->company_name ?? null) ?: config('app.name'));
        } catch (Throwable $e) {
            return (string) config('app.name');
        }
    }

    /**
     * Customers with due > 0 (opening + credit - debit over active payments, same rule as PartyDueTrait::partyDue).
     * $ids limits to those parties.
     */
    public static function customerDues(?array $ids = null)
    {
        return DB::table('parties as p')->where('p.deleted', 'No')->where('p.status', 'Active')
            ->whereIn('p.type', ['Customer', 'Walk-In Customer'])
            ->when($ids !== null, fn ($q) => $q->whereIn('p.id', $ids ?: [0]))
            ->leftJoin('payments as pm', function ($j) {
                $j->on('pm.party_id', '=', 'p.id')->where('pm.deleted', 'No')->where('pm.status', 'Active')
                    ->whereIn('pm.branch_id', BranchController::visibleIds())
                    // party_id also holds employee ids on employee expense rows
                    ->where(fn ($q) => $q->whereNull('pm.party_type')->orWhere('pm.party_type', '<>', 'Employee'));
            })
            ->groupBy('p.id', 'p.code', 'p.name', 'p.phone', 'p.type', 'p.opening_balance', 'p.opening_balance_type')
            ->selectRaw('p.id, p.code, p.name, p.phone, p.type,
                ' . (BranchController::mainVisible() ? "CASE WHEN p.opening_balance_type = 'Advance' THEN -1 ELSE 1 END * COALESCE(p.opening_balance, 0)" : '0') . ' + COALESCE(SUM(pm.credit), 0) - COALESCE(SUM(pm.debit), 0) AS due')
            ->havingRaw('due > 0.004')
            ->orderByDesc('due')
            ->get();
    }

    /* ------------------------------------------------------------------ */
    /* Routes                                                              */
    /* ------------------------------------------------------------------ */

    public function balancePost()
    {
        $r = self::balance();

        return response()->json(['status' => $r['ok'], 'message' => $r['message'], 'balance' => $r['balance']], $r['ok'] ? 200 : 422);
    }

    public function test(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone'   => ['required', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:600'],
        ], ['phone.required' => 'Enter a mobile number.', 'message.required' => 'Enter a message.']);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }
        $r = self::send($request->input('phone'), $request->input('message'), 'Test');

        return response()->json(['status' => $r['ok'], 'message' => $r['message']], $r['ok'] ? 200 : 422);
    }

    /** Due reminders page. */
    public function dueIndex()
    {
        $enabled = self::enabled('due_reminder');
        $smsOn = SystemSettingController::flag('sms_enabled');
        $template = (string) SystemSettingController::get('sms_tpl_due_reminder');
        $company = self::companyName();

        return view('admin.system.sms-due', compact('enabled', 'smsOn', 'template', 'company'));
    }

    public function dueList()
    {
        try {
            $rows = self::customerDues();
            $last = DB::table('sms_logs')->where('type', 'Due Reminder')->where('status', 'Sent')
                ->whereIn('party_id', $rows->pluck('id')->all() ?: [0])
                ->groupBy('party_id')->selectRaw('party_id, MAX(created_at) AS at')->pluck('at', 'party_id');

            return response()->json([
                'data' => $rows->values()->map(function ($r) use ($last) {
                    $valid = self::normalize((string) $r->phone) !== null;

                    return [
                        'id'      => (int) $r->id,
                        'name'    => $r->name,
                        'code'    => $r->code,
                        'type'    => $r->type,
                        'phone'   => $r->phone,
                        'valid'   => $valid,
                        'due'     => round((float) $r->due, 2),
                        'last'    => isset($last[$r->id]) ? Carbon::parse($last[$r->id])->format('d M Y h:i A') : null,
                        'preview' => self::render('due_reminder', ['customer' => $r->name, 'due' => (float) $r->due]),
                    ];
                }),
            ]);
        } catch (Throwable $e) {
            Log::error('Due reminder list error', ['error' => $e->getMessage()]);

            return response()->json(['data' => [], 'message' => 'Unable to load customer dues.'], 500);
        }
    }

    public function dueSend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'party_ids'   => ['required', 'array', 'min:1', 'max:300'],
            'party_ids.*' => ['integer'],
        ], ['party_ids.required' => 'Select at least one customer.', 'party_ids.max' => 'Send to at most 300 customers at a time.']);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }
        if (!SystemSettingController::flag('sms_enabled')) {
            return response()->json(['status' => false, 'message' => 'SMS is turned off. Turn it on in System > Email & SMS.'], 422);
        }

        @set_time_limit(0);
        $sent = 0;
        $failed = [];
        foreach (self::customerDues(array_map('intval', $request->input('party_ids'))) as $r) {
            $text = self::render('due_reminder', ['customer' => $r->name, 'due' => (float) $r->due]);
            $res = self::send((string) $r->phone, $text, 'Due Reminder', (int) $r->id);
            if ($res['ok']) {
                $sent++;
            } else {
                $failed[] = $r->name . ': ' . $res['message'];
            }
            if (count($failed) >= 5 && $sent === 0) {
                $failed[] = 'Stopped after 5 failures in a row.';
                break;
            }
        }

        $message = $sent . ' ' . Str::plural('reminder', $sent) . ' sent' . ($failed ? ', ' . count($failed) . ' failed.' : '.');

        return response()->json(['status' => $sent > 0 || !$failed, 'message' => $message, 'sent' => $sent, 'failed' => $failed], $sent > 0 || !$failed ? 200 : 422);
    }

    /** SMS log page. */
    public function logIndex()
    {
        $types = BranchController::scope(DB::table('sms_logs'), 'branch_id')->distinct()->orderBy('type')->pluck('type');

        return view('admin.system.sms-log', compact('types'));
    }

    public function logList(Request $request)
    {
        try {
            $base = BranchController::scope(DB::table('sms_logs as s'), 's.branch_id')->leftJoin('users as u', 'u.id', '=', 's.user_id')->leftJoin('parties as p', 'p.id', '=', 's.party_id');
            $total = (clone $base)->count();

            $q = (clone $base)
                ->when($request->filled('from'), fn ($q) => $q->where('s.created_at', '>=', Carbon::parse($request->query('from'))->startOfDay()))
                ->when($request->filled('to'), fn ($q) => $q->where('s.created_at', '<=', Carbon::parse($request->query('to'))->endOfDay()))
                ->when($request->filled('status'), fn ($q) => $q->where('s.status', $request->query('status')))
                ->when($request->filled('type'), fn ($q) => $q->where('s.type', $request->query('type')));
            $term = trim((string) data_get($request->query('search'), 'value'));
            if ($term !== '') {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
                $q->where(fn ($w) => $w->where('s.phone', 'like', $like)->orWhere('s.message', 'like', $like)->orWhere('p.name', 'like', $like));
            }
            $filtered = (clone $q)->count();
            $start = max(0, (int) $request->query('start', 0));
            $length = (int) $request->query('length', 25);
            $length = $length < 1 ? 25 : min($length, 200);

            $rows = $q->orderByDesc('s.id')->offset($start)->limit($length)
                ->get(['s.*', 'u.name as user_name', 'p.name as party_name']);

            return response()->json([
                'draw'            => (int) $request->query('draw', 0),
                'recordsTotal'    => $total,
                'recordsFiltered' => $filtered,
                'data'            => $rows->values()->map(fn ($r, $i) => [
                    $start + $i + 1,
                    e(Carbon::parse($r->created_at)->format('d M Y')) . '<div class="text-muted small">' . e(Carbon::parse($r->created_at)->format('h:i A')) . '</div>',
                    e($r->phone) . ($r->party_name ? '<div class="text-muted small">' . e($r->party_name) . '</div>' : ''),
                    '<div class="sms-text">' . e($r->message) . '</div>',
                    e($r->type),
                    '<span class="badge ' . ($r->status === 'Sent' ? 'bg-success' : 'bg-danger') . '">' . e($r->status) . '</span>'
                        . ($r->status === 'Failed' ? '<div class="text-muted small text-break">' . e(Str::limit((string) $r->response, 120)) . '</div>' : ''),
                    e($r->user_name ?? 'System'),
                ]),
            ]);
        } catch (Throwable $e) {
            Log::error('SMS log list error', ['error' => $e->getMessage()]);

            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'message' => 'Unable to load the SMS log.'], 500);
        }
    }
}
