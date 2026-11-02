<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Zk\ZkClient;
use App\Http\Controllers\Controller;
use App\Traits\VoucherHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/**
 * ZKTeco attendance devices: setup, "Test connection" and "Sync now".
 * Sync reads the device's attendance log (UDP 4370, see Zk\ZkClient), stores new punches in
 * attendance_punches, maps them to employees by employees.device_user_id and rebuilds those days
 * (AttendanceController::rebuildFromPunches()).
 *
 * syncAll() syncs every active device; the scheduler can call it, e.g. in app/Console/Kernel.php:
 *   $schedule->call(fn () => \App\Http\Controllers\Admin\AttendanceDeviceController::syncAll())->everyThirtyMinutes();
 */
class AttendanceDeviceController extends Controller
{
    use VoucherHelperTrait;

    public const TIMEOUT = 4.0;

    public function list()
    {
        $unmapped = DB::table('attendance_punches')->where('device_id', '>', 0)->whereNull('employee_id')
            ->groupBy('device_id')->selectRaw('device_id, COUNT(DISTINCT device_user_id) as n')->pluck('n', 'device_id');

        $rows = DB::table('attendance_devices as d')->leftJoin('branches as b', 'b.id', '=', 'd.branch_id')
            ->where('d.deleted', 'No')->orderBy('d.name')->get(['d.*', 'b.name as branch'])
            ->map(function ($d) use ($unmapped) {
                $d->unmapped_users = (int) ($unmapped[$d->id] ?? 0);
                $d->punches = DB::table('attendance_punches')->where('device_id', $d->id)->count();

                return $d;
            });

        return response()->json(['status' => true, 'data' => $rows]);
    }

    public function save(Request $request)
    {
        $id = $request->filled('id') ? (int) $request->input('id') : null;
        $validator = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:100'],
            'ip'        => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'port'      => ['required', 'integer', 'min:1', 'max:65535'],
            'comm_key'  => ['nullable', 'integer', 'min:0', 'max:999999'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('deleted', 'No')],
            'status'    => ['required', Rule::in(['Active', 'Inactive'])],
        ], ['ip.regex' => 'Enter an IP address like 192.168.1.201.']);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = [
            'name'      => trim($request->input('name')),
            'ip'        => trim($request->input('ip')),
            'port'      => (int) $request->input('port'),
            'comm_key'  => (int) $request->input('comm_key', 0),
            'branch_id' => $request->input('branch_id') ?: null,
            'status'    => $request->input('status'),
        ];

        if ($id) {
            DB::table('attendance_devices')->where('id', $id)->update($data + ['updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now()]);
        } else {
            DB::table('attendance_devices')->insert($data + ['created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['status' => true, 'message' => 'Device saved.']);
    }

    public function delete(int $id)
    {
        DB::table('attendance_devices')->where('id', $id)->update($this->deletedColumns());

        return response()->json(['status' => true, 'message' => 'Device removed. Punches already read are kept.']);
    }

    public function test(int $id)
    {
        $device = $this->find($id);
        if (!$device) {
            return response()->json(['status' => false, 'message' => 'Device not found.'], 404);
        }

        $result = self::testDevice($device);
        DB::table('attendance_devices')->where('id', $id)->update(['last_message' => mb_substr($result['message'], 0, 255), 'updated_at' => now()]);

        return response()->json($result, $result['status'] ? 200 : 422);
    }

    public function sync(int $id)
    {
        $device = $this->find($id);
        if (!$device) {
            return response()->json(['status' => false, 'message' => 'Device not found.'], 404);
        }

        $result = self::syncDevice($device);

        return response()->json($result, $result['status'] ? 200 : 422);
    }

    /* ------------------------------------------------------------------ */
    /*  Shared (scheduler-friendly)                                        */
    /* ------------------------------------------------------------------ */

    /** Syncs every active device; returns one result per device. Safe to call from the scheduler. */
    public static function syncAll(): array
    {
        $out = [];
        foreach (DB::table('attendance_devices')->where('deleted', 'No')->where('status', 'Active')->orderBy('id')->get() as $device) {
            $out[$device->id] = self::syncDevice($device);
        }

        return $out;
    }

    /** Connects, reads device info, disconnects. ['status' => bool, 'message' => string, 'info' => [...]] */
    public static function testDevice(object $device): array
    {
        $zk = new ZkClient($device->ip, (int) $device->port, (int) $device->comm_key, self::TIMEOUT);
        $started = microtime(true);
        try {
            $zk->connect();
            $info = ['version' => $zk->version(), 'serial' => $zk->option('~SerialNumber'), 'name' => $zk->option('~DeviceName')] + $zk->sizes();
            $zk->disconnect();

            return [
                'status'  => true,
                'message' => 'Connected to ' . ($info['name'] ?: $device->name) . ($info['serial'] ? ' (S/N ' . $info['serial'] . ')' : '')
                    . ': ' . $info['users'] . ' user(s), ' . $info['records'] . ' attendance record(s).',
                'info'    => $info,
                'seconds' => round(microtime(true) - $started, 2),
            ];
        } catch (RuntimeException $e) {
            $zk->disconnect();

            return ['status' => false, 'message' => $e->getMessage(), 'seconds' => round(microtime(true) - $started, 2)];
        } catch (Throwable $e) {
            $zk->disconnect();
            Log::error('Attendance device test error', ['device' => $device->id, 'error' => $e->getMessage()]);

            return ['status' => false, 'message' => 'Could not talk to the device: ' . $e->getMessage(), 'seconds' => round(microtime(true) - $started, 2)];
        }
    }

    /** Reads the attendance log, stores new punches, rebuilds the affected days. */
    public static function syncDevice(object $device): array
    {
        $zk = new ZkClient($device->ip, (int) $device->port, (int) $device->comm_key, self::TIMEOUT);
        try {
            $zk->connect();
            $zk->disableDevice();   // no punching while the log is read
            try {
                $logs = $zk->attendance();
            } finally {
                // Always try to re-enable punching, but never let a failure here hide the real error.
                try {
                    $zk->enableDevice();
                } catch (Throwable $e) {
                    Log::warning('Attendance device could not be re-enabled after sync', ['device' => $device->id, 'error' => $e->getMessage()]);
                }
                $zk->disconnect();
            }

            $result = self::storePunches((int) $device->id, $logs);
            $message = count($logs) . ' record(s) on the device, ' . $result['new'] . ' new. ' . $result['days'] . ' attendance day(s) updated'
                . ($result['skipped'] ? ', ' . $result['skipped'] . ' kept (entered by hand)' : '')
                . ($result['unmapped'] ? '. ' . $result['unmapped'] . ' device user(s) are not linked to an employee (set "Device User ID" on the employee)' : '') . '.';

            DB::table('attendance_devices')->where('id', $device->id)->update(['last_synced_at' => now(), 'last_message' => mb_substr($message, 0, 255), 'updated_at' => now()]);

            return ['status' => true, 'message' => $message] + $result;
        } catch (RuntimeException $e) {
            $zk->disconnect();
            DB::table('attendance_devices')->where('id', $device->id)->update(['last_message' => mb_substr('Sync failed: ' . $e->getMessage(), 0, 255), 'updated_at' => now()]);

            return ['status' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $zk->disconnect();
            Log::error('Attendance device sync error', ['device' => $device->id, 'error' => $e->getMessage(), 'line' => $e->getLine()]);

            return ['status' => false, 'message' => 'Sync failed: ' . $e->getMessage()];
        }
    }

    /**
     * Saves device log lines ([['user_id' => .., 'time' => 'Y-m-d H:i:s'], ...]) as punches, links them to
     * employees by device_user_id (also older punches of users linked since) and rebuilds those days.
     */
    public static function storePunches(int $deviceId, array $logs): array
    {
        // Drop records with an impossible clock value (e.g. 31 February from a device with a bad RTC or a
        // corrupt record): MySQL would store them as 0000-00-00 via INSERT IGNORE and the rebuild range
        // below would then start at year 0.
        $logs = array_values(array_filter($logs, function ($l) {
            $t = (string) ($l['time'] ?? '');
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $t, $m)) {
                return false;
            }

            return (int) $m[1] >= 2000 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                && (int) $m[4] < 24 && (int) $m[5] < 60 && (int) $m[6] < 60 && trim((string) ($l['user_id'] ?? '')) !== '';
        }));

        return DB::transaction(function () use ($deviceId, $logs) {
            $new = 0;
            foreach (array_chunk($logs, 500) as $chunk) {
                $new += DB::table('attendance_punches')->insertOrIgnore(array_map(fn ($l) => [
                    'device_id' => $deviceId, 'device_user_id' => mb_substr((string) $l['user_id'], 0, 30), 'punched_at' => $l['time'],
                    'source' => 'Device', 'created_at' => now(),
                ], $chunk));
            }

            // Link punches to employees (exact device user id, or the same number without leading zeros).
            $map = [];
            foreach (DB::table('employees')->where('deleted', 'No')->whereNotNull('device_user_id')->where('device_user_id', '<>', '')->get(['id', 'device_user_id']) as $e) {
                $map[(string) $e->device_user_id] = $e->id;
                $map[ltrim((string) $e->device_user_id, '0')] ??= $e->id;
            }
            $pending = DB::table('attendance_punches')->where('device_id', $deviceId)->whereNull('employee_id')
                ->groupBy('device_user_id')->selectRaw('device_user_id, MIN(punched_at) as first_at, MAX(punched_at) as last_at')->get();

            $touched = [];
            $unmapped = 0;
            foreach ($pending as $p) {
                $empId = $map[$p->device_user_id] ?? $map[ltrim($p->device_user_id, '0')] ?? null;
                if (!$empId) {
                    $unmapped++;
                    continue;
                }
                DB::table('attendance_punches')->where('device_id', $deviceId)->where('device_user_id', $p->device_user_id)->whereNull('employee_id')
                    ->update(['employee_id' => $empId]);
                $touched[$empId] = [min($touched[$empId][0] ?? $p->first_at, $p->first_at), max($touched[$empId][1] ?? $p->last_at, $p->last_at)];
            }

            $days = $skipped = 0;
            if ($touched) {
                $from = substr(min(array_column($touched, 0)), 0, 10);
                $to = substr(max(array_column($touched, 1)), 0, 10);
                $r = AttendanceController::rebuildFromPunches(array_keys($touched), $from, $to, true);
                $days = $r['days'];
                $skipped = $r['skipped'];
            }

            return ['new' => $new, 'days' => $days, 'skipped' => $skipped, 'unmapped' => $unmapped];
        });
    }

    private function find(int $id): ?object
    {
        return DB::table('attendance_devices')->where('id', $id)->where('deleted', 'No')->first();
    }
}
