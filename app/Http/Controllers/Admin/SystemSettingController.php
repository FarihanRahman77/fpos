<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * System settings (key/value table `system_settings`) and the "Email & SMS" settings page.
 *
 * Static API used by the other system features:
 *   SystemSettingController::get('mail_host', 'default')
 *   SystemSettingController::put(['key' => 'value'])          (keys in SECRETS are stored encrypted)
 *   SystemSettingController::applyMailConfig()                (called from AppServiceProvider::boot)
 */
class SystemSettingController extends Controller
{
    /** Keys stored Crypt-encrypted. Never sent back to the browser. */
    public const SECRETS = ['mail_password', 'sms_api_key'];

    public const DEFAULTS = [
        // mail
        'mail_enabled'      => '0',
        'mail_host'         => '',
        'mail_port'         => '587',
        'mail_username'     => '',
        'mail_password'     => '',
        'mail_encryption'   => 'tls',
        'mail_from_address' => '',
        'mail_from_name'    => '',
        // sms (BulkSMSBD)
        'sms_enabled'             => '0',
        'sms_api_key'             => '',
        'sms_sender_id'           => '',
        'sms_invoice_sms_enabled' => '0',
        'sms_due_reminder_enabled' => '0',
        'sms_tpl_invoice_sms'     => 'Dear {customer}, thanks for your purchase. Invoice {invoice}: total {amount}, paid {paid}, due {due}. - {company}',
        'sms_tpl_due_reminder'    => 'Dear {customer}, your outstanding due is {due}. Please pay at your earliest convenience. - {company}',
        // backups
        'backup_keep'            => '7',
        'backup_include_uploads' => '0',
        'backup_time'            => '02:00',
        // activity log
        'activity_retention_days' => '90',
    ];

    /** Per-request cache of the decrypted settings. */
    private static ?array $cache = null;

    /* ------------------------------------------------------------------ */
    /* Static key/value API                                                */
    /* ------------------------------------------------------------------ */

    /** All settings (defaults merged with stored values, secrets decrypted). Never throws. */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $values = self::DEFAULTS;
        try {
            if (Schema::hasTable('system_settings')) {
                foreach (DB::table('system_settings')->get(['key', 'value', 'is_encrypted']) as $row) {
                    $value = $row->value;
                    if ($row->is_encrypted && $value !== null && $value !== '') {
                        try {
                            $value = Crypt::decryptString($value);
                        } catch (Throwable $e) {
                            Log::warning('System setting could not be decrypted (APP_KEY changed?)', ['key' => $row->key]);
                            $value = '';
                        }
                    }
                    $values[$row->key] = $value;
                }
            }
        } catch (Throwable $e) {
            Log::warning('Unable to read system settings.', ['error' => $e->getMessage()]);

            return $values; // do not cache a failed read
        }

        return self::$cache = $values;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    public static function flag(string $key): bool
    {
        return (string) self::get($key, '0') === '1';
    }

    /** Saves the given key => value pairs (secrets encrypted). */
    public static function put(array $values): void
    {
        $now = now();
        $userId = Auth::id();
        foreach ($values as $key => $value) {
            $encrypted = in_array($key, self::SECRETS, true);
            $value = $value === null ? null : (string) $value;
            if ($encrypted && $value !== null && $value !== '') {
                $value = Crypt::encryptString($value);
            }
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'is_encrypted' => $encrypted ? 1 : 0, 'updated_by' => $userId, 'updated_at' => $now, 'created_at' => $now]
            );
        }
        self::$cache = null;
    }

    /**
     * Applies the saved SMTP settings to config('mail') when "Send email through these settings" is on.
     * Called from AppServiceProvider::boot(); never throws.
     */
    public static function applyMailConfig(?array $s = null): void
    {
        try {
            $s = $s ?? self::all();
            if (($s['mail_enabled'] ?? '0') !== '1' || trim((string) ($s['mail_host'] ?? '')) === '') {
                return;
            }
            config(self::mailConfig($s));
        } catch (Throwable $e) {
            Log::warning('Unable to apply the mail settings.', ['error' => $e->getMessage()]);
        }
    }

    /** config() array for the given mail settings. */
    private static function mailConfig(array $s): array
    {
        $enc = $s['mail_encryption'] ?? 'tls';
        $from = trim((string) ($s['mail_from_address'] ?? ''));
        if ($from === '' && filter_var(trim((string) ($s['mail_username'] ?? '')), FILTER_VALIDATE_EMAIL)) {
            $from = trim((string) $s['mail_username']); // Gmail etc.: the login is the address
        }

        return [
            'mail.default'                   => 'smtp',
            'mail.mailers.smtp.transport'    => 'smtp',
            'mail.mailers.smtp.host'         => trim((string) $s['mail_host']),
            'mail.mailers.smtp.port'         => (int) ($s['mail_port'] ?: ($enc === 'ssl' ? 465 : 587)),
            'mail.mailers.smtp.encryption'   => $enc === 'none' ? null : $enc,
            // ssl = implicit TLS (usually 465); tls = STARTTLS (usually 587)
            'mail.mailers.smtp.scheme'       => $enc === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username'     => ($s['mail_username'] ?? '') !== '' ? $s['mail_username'] : null,
            'mail.mailers.smtp.password'     => ($s['mail_password'] ?? '') !== '' ? $s['mail_password'] : null,
            'mail.mailers.smtp.timeout'      => 15,
            'mail.from.address'              => $from ?: config('mail.from.address'),
            'mail.from.name'                 => trim((string) ($s['mail_from_name'] ?? '')) ?: (config('mail.from.name') ?: 'FPOS'),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Email & SMS page                                                    */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        $s = self::all();
        foreach (self::SECRETS as $key) {
            $s[$key . '_set'] = ($s[$key] ?? '') !== '';
            unset($s[$key]);
        }

        return view('admin.system.email-sms', ['s' => $s]);
    }

    public function saveMail(Request $request)
    {
        $validator = $this->mailValidator($request);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $values = $this->mailInput($request);
            if ($request->input('mail_password', '') === '' && !$request->boolean('clear_password')) {
                unset($values['mail_password']); // blank = keep the saved password
            }
            self::put($values);

            return response()->json(['status' => true, 'message' => 'Email settings saved.']);
        } catch (Throwable $e) {
            Log::error('Save mail settings error', ['error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => 'Unable to save the email settings.'], 500);
        }
    }

    /** Sends a test email with the settings in the form (blank password = the saved one) and reports the SMTP error. */
    public function testMail(Request $request)
    {
        $validator = $this->mailValidator($request, true);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $s = $this->mailInput($request);
        if ($s['mail_password'] === '') {
            $s['mail_password'] = (string) self::get('mail_password', '');
        }
        if (trim($s['mail_host']) === '') {
            return response()->json(['status' => false, 'message' => 'Enter the SMTP host first.'], 422);
        }

        $previous = config('mail');
        try {
            config(self::mailConfig($s));
            config(['mail.mailers.smtp.timeout' => 10]);
            Mail::purge('smtp');

            $company = $this->companyName();
            $html = '<p>This is a test email from <b>' . e($company) . '</b>.</p>'
                . '<p>Your SMTP settings (' . e($s['mail_host']) . ':' . e($s['mail_port']) . ', ' . e($s['mail_encryption']) . ') work.</p>'
                . '<p><small>Sent ' . e(now()->format('d M Y h:i A')) . '</small></p>';
            Mail::mailer('smtp')->html($html, function ($m) use ($request, $company) {
                $m->to($request->input('test_to'))->subject('Test email from ' . $company);
            });

            return response()->json(['status' => true, 'message' => 'Test email sent to ' . $request->input('test_to') . '.']);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'SMTP error: ' . $this->cleanError($e->getMessage())], 422);
        } finally {
            config(['mail' => $previous]);
            Mail::purge('smtp');
        }
    }

    public function saveSms(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sms_sender_id'        => ['nullable', 'string', 'max:30'],
            'sms_api_key'          => ['nullable', 'string', 'max:200'],
            'sms_tpl_invoice_sms'  => ['required', 'string', 'max:600'],
            'sms_tpl_due_reminder' => ['required', 'string', 'max:600'],
        ], [
            'sms_tpl_invoice_sms.required'  => 'Enter the invoice SMS template.',
            'sms_tpl_due_reminder.required' => 'Enter the due reminder template.',
        ]);
        if ($request->boolean('sms_enabled')) {
            $validator->after(function ($v) use ($request) {
                if (trim((string) $request->input('sms_api_key')) === '' && (string) self::get('sms_api_key', '') === '') {
                    $v->errors()->add('sms_api_key', 'Enter the BulkSMSBD API key to turn SMS on.');
                }
                if (trim((string) $request->input('sms_sender_id')) === '') {
                    $v->errors()->add('sms_sender_id', 'Enter the sender ID given by BulkSMSBD.');
                }
            });
        }
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $values = [
                'sms_enabled'              => $request->boolean('sms_enabled') ? '1' : '0',
                'sms_sender_id'            => trim((string) $request->input('sms_sender_id')),
                'sms_invoice_sms_enabled'  => $request->boolean('sms_invoice_sms_enabled') ? '1' : '0',
                'sms_due_reminder_enabled' => $request->boolean('sms_due_reminder_enabled') ? '1' : '0',
                'sms_tpl_invoice_sms'      => trim((string) $request->input('sms_tpl_invoice_sms')),
                'sms_tpl_due_reminder'     => trim((string) $request->input('sms_tpl_due_reminder')),
            ];
            if (trim((string) $request->input('sms_api_key')) !== '') {
                $values['sms_api_key'] = trim((string) $request->input('sms_api_key'));
            }
            self::put($values);

            return response()->json(['status' => true, 'message' => 'SMS settings saved.']);
        } catch (Throwable $e) {
            Log::error('Save SMS settings error', ['error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => 'Unable to save the SMS settings.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */

    private function mailValidator(Request $request, bool $test = false)
    {
        $rules = [
            'mail_host'         => ['nullable', 'string', 'max:191'],
            'mail_port'         => ['nullable', 'integer', 'between:1,65535'],
            'mail_username'     => ['nullable', 'string', 'max:191'],
            'mail_password'     => ['nullable', 'string', 'max:191'],
            'mail_encryption'   => ['required', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email', 'max:191'],
            'mail_from_name'    => ['nullable', 'string', 'max:191'],
        ];
        if ($test) {
            $rules['test_to'] = ['required', 'email', 'max:191'];
        }
        $validator = Validator::make($request->all(), $rules, ['test_to.required' => 'Enter the address to send the test email to.']);
        if (!$test && $request->boolean('mail_enabled')) {
            $validator->after(function ($v) use ($request) {
                if (trim((string) $request->input('mail_host')) === '') {
                    $v->errors()->add('mail_host', 'Enter the SMTP host to turn email on.');
                }
                if (trim((string) $request->input('mail_from_address')) === '' && trim((string) $request->input('mail_username')) === '') {
                    $v->errors()->add('mail_from_address', 'Enter the "From" address.');
                }
            });
        }

        return $validator;
    }

    private function mailInput(Request $request): array
    {
        return [
            'mail_enabled'      => $request->boolean('mail_enabled') ? '1' : '0',
            'mail_host'         => trim((string) $request->input('mail_host')),
            'mail_port'         => (string) ((int) $request->input('mail_port') ?: 587),
            'mail_username'     => trim((string) $request->input('mail_username')),
            'mail_password'     => (string) $request->input('mail_password', ''),
            'mail_encryption'   => (string) $request->input('mail_encryption', 'tls'),
            'mail_from_address' => trim((string) $request->input('mail_from_address')),
            'mail_from_name'    => trim((string) $request->input('mail_from_name')),
        ];
    }

    private function cleanError(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', strip_tags($message)));

        return mb_strimwidth($message, 0, 500, '…');
    }

    private function companyName(): string
    {
        try {
            return (string) (DB::table('settings')->where('deleted', 'No')->value('company_name') ?: config('app.name'));
        } catch (Throwable $e) {
            return (string) config('app.name');
        }
    }
}
