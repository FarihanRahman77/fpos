<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

/**
 * Database (+ optional uploads) backups in storage/app/backups.
 *
 * Static API:
 *   BackupController::run(?bool $withUploads = null): array   ['ok' => bool, 'message' => string, 'files' => [...]]
 *   BackupController::prune(?int $keep = null): int            keeps the newest N of each kind, returns files deleted
 *   BackupController::scheduleTime(): string                   "HH:MM" for the scheduler (Settings > Backups)
 */
class BackupController extends Controller
{
    private const NAME_RULE = '/^fpos-(uploads-)?\d{8}-\d{6}\.(sql\.gz|zip)$/';

    public static function dir(): string
    {
        $dir = storage_path('app' . DIRECTORY_SEPARATOR . 'backups');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function scheduleTime(): string
    {
        try {
            $t = (string) SystemSettingController::get('backup_time', '02:00');

            return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t : '02:00';
        } catch (Throwable $e) {
            return '02:00';
        }
    }

    /** mysqldump from XAMPP, else the one on PATH. */
    public static function mysqldumpPath(): string
    {
        foreach (['C:\\xampp\\mysql\\bin\\mysqldump.exe', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump'] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return 'mysqldump';
    }

    /** Runs a backup now. Never throws. */
    public static function run(?bool $withUploads = null): array
    {
        @set_time_limit(0);
        $stamp = now()->format('Ymd-His');
        $dir = self::dir();
        $files = [];

        try {
            $sql = self::dumpDatabase($dir . DIRECTORY_SEPARATOR . "fpos-{$stamp}.sql.gz");
            $files[] = basename($sql);

            $withUploads = $withUploads ?? SystemSettingController::flag('backup_include_uploads');
            $uploadNote = '';
            if ($withUploads) {
                try {
                    $zip = self::zipUploads($dir . DIRECTORY_SEPARATOR . "fpos-uploads-{$stamp}.zip");
                    if ($zip) {
                        $files[] = basename($zip);
                    } else {
                        $uploadNote = ' (public/uploads is empty or missing; no zip made)';
                    }
                } catch (Throwable $e) {
                    $uploadNote = ' (uploads zip failed: ' . $e->getMessage() . ')';
                    Log::error('Uploads backup failed', ['error' => $e->getMessage()]);
                }
            }

            $pruned = self::prune();
            if (!request()->route()) { // artisan / scheduler; web requests are logged by the LogActivity middleware
                ActivityLogController::event('Created backup (scheduled)', null, ['files' => implode(', ', $files), 'pruned' => $pruned], 'backup:run');
            }

            return ['ok' => true, 'message' => 'Backup created: ' . implode(', ', $files) . $uploadNote . '.', 'files' => $files, 'pruned' => $pruned];
        } catch (Throwable $e) {
            Log::error('Database backup failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Backup failed: ' . $e->getMessage(), 'files' => $files];
        }
    }

    /** Keeps the newest $keep database dumps and the newest $keep upload zips. */
    public static function prune(?int $keep = null): int
    {
        $keep = max(1, $keep ?? (int) SystemSettingController::get('backup_keep', 7));
        $deleted = 0;
        foreach (['/^fpos-\d{8}-\d{6}\.sql\.gz$/', '/^fpos-uploads-\d{8}-\d{6}\.zip$/'] as $pattern) {
            $group = array_values(array_filter(self::files(), fn ($f) => preg_match($pattern, $f['name'])));
            foreach (array_slice($group, $keep) as $old) {
                if (@unlink(self::dir() . DIRECTORY_SEPARATOR . $old['name'])) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /** Backup files, newest first. */
    public static function files(): array
    {
        $out = [];
        foreach (glob(self::dir() . DIRECTORY_SEPARATOR . 'fpos-*') ?: [] as $path) {
            $name = basename($path);
            if (is_file($path) && preg_match(self::NAME_RULE, $name)) {
                $out[] = ['name' => $name, 'size' => filesize($path), 'time' => filemtime($path)];
            }
        }
        // the timestamp in the name orders correctly; fall back to mtime
        usort($out, fn ($a, $b) => strcmp(preg_replace('/^fpos-(uploads-)?/', '', $b['name']), preg_replace('/^fpos-(uploads-)?/', '', $a['name'])) ?: $b['time'] <=> $a['time']);

        return $out;
    }

    private static function dumpDatabase(string $target): string
    {
        $db = config('database.connections.' . config('database.default'));
        if (($db['driver'] ?? '') !== 'mysql' && ($db['driver'] ?? '') !== 'mariadb') {
            throw new \RuntimeException('Only MySQL / MariaDB databases can be backed up.');
        }

        // Credentials go in a temporary option file, not on the command line.
        $cnf = tempnam(sys_get_temp_dir(), 'fpos');
        $quote = fn ($v) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $v) . '"';
        file_put_contents($cnf, "[client]\nuser=" . $quote($db['username'] ?? 'root') . "\npassword=" . $quote($db['password'] ?? '')
            . "\nhost=" . $quote($db['host'] ?? '127.0.0.1') . "\nport=" . (int) ($db['port'] ?? 3306) . "\n");
        $errFile = tempnam(sys_get_temp_dir(), 'fpos');

        $cmd = [
            self::mysqldumpPath(), '--defaults-extra-file=' . $cnf,
            '--single-transaction', '--quick', '--routines', '--triggers', '--add-drop-table',
            '--default-character-set=utf8mb4', '--hex-blob', (string) $db['database'],
        ];

        try {
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['file', $errFile, 'w']], $pipes, null, null, ['bypass_shell' => true]);
            if (!is_resource($proc)) {
                throw new \RuntimeException('Could not start mysqldump (' . self::mysqldumpPath() . ').');
            }
            $gz = gzopen($target, 'wb6');
            if (!$gz) {
                fclose($pipes[1]);
                proc_close($proc);
                throw new \RuntimeException('Cannot write ' . $target);
            }
            $bytes = 0;
            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], 1024 * 512);
                if ($chunk === false) {
                    break;
                }
                $bytes += strlen($chunk);
                gzwrite($gz, $chunk);
            }
            fclose($pipes[1]);
            gzclose($gz);
            $code = proc_close($proc);

            $err = trim((string) @file_get_contents($errFile));
            if ($code !== 0 || $bytes === 0) {
                @unlink($target);
                throw new \RuntimeException('mysqldump exited with code ' . $code . ($err ? ': ' . mb_strimwidth($err, 0, 400, '…') : ''));
            }

            return $target;
        } finally {
            @unlink($cnf);
            @unlink($errFile);
        }
    }

    private static function zipUploads(string $target): ?string
    {
        $source = public_path('uploads');
        if (!is_dir($source)) {
            return null;
        }
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is not enabled.');
        }
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create ' . basename($target));
        }
        $count = 0;
        $base = strlen(realpath($source)) + 1;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile()) {
                $zip->addFile($file->getRealPath(), 'uploads/' . str_replace('\\', '/', substr($file->getRealPath(), $base)));
                $count++;
            }
        }
        $zip->close();
        if (!$count) {
            @unlink($target);

            return null;
        }

        return $target;
    }

    /* ------------------------------------------------------------------ */
    /* Page                                                                */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        $s = SystemSettingController::all();
        $settings = [
            'backup_keep'            => (int) $s['backup_keep'],
            'backup_include_uploads' => $s['backup_include_uploads'] === '1',
            'backup_time'            => self::scheduleTime(),
        ];
        $mysqldump = self::mysqldumpPath();

        return view('admin.system.backups', compact('settings', 'mysqldump'));
    }

    public function list()
    {
        $files = self::files();

        return response()->json([
            'data' => array_map(function ($f, $i) {
                $name = $f['name'];
                $isZip = str_ends_with($name, '.zip');
                $items = [
                    ['label' => 'Download', 'icon' => 'fas fa-download', 'href' => route('admin.system.backups.download', $name)],
                    ['divider' => true],
                    ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => "Backup.remove('" . $name . "')"],
                ];

                return [
                    $i + 1,
                    '<span class="fw-semibold text-break">' . e($name) . '</span>',
                    '<span class="badge ' . ($isZip ? 'bg-info text-dark' : 'bg-primary') . '">' . ($isZip ? 'Uploads' : 'Database') . '</span>',
                    '<span class="d-block text-end">' . e(self::size($f['size'])) . '</span>',
                    e(Carbon::createFromTimestamp($f['time'])->format('d M Y h:i A')),
                    action_dropdown($items),
                ];
            }, $files, array_keys($files)),
            'total' => self::size(array_sum(array_column($files, 'size'))),
        ]);
    }

    public function runNow(Request $request)
    {
        $result = self::run($request->has('include_uploads') ? $request->boolean('include_uploads') : null);

        return response()->json(['status' => $result['ok'], 'message' => $result['message']], $result['ok'] ? 200 : 500);
    }

    public function download(string $file)
    {
        $path = $this->pathOf($file);
        abort_unless($path, 404, 'Backup not found.');

        return response()->download($path, $file, ['Content-Type' => str_ends_with($file, '.zip') ? 'application/zip' : 'application/gzip']);
    }

    public function destroy(string $file)
    {
        $path = $this->pathOf($file);
        if (!$path) {
            return response()->json(['status' => false, 'message' => 'Backup not found.'], 404);
        }
        if (!@unlink($path)) {
            return response()->json(['status' => false, 'message' => 'Unable to delete the file.'], 500);
        }

        return response()->json(['status' => true, 'message' => $file . ' deleted.']);
    }

    public function saveSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'backup_keep' => ['required', 'integer', 'between:1,365'],
            'backup_time' => ['required', 'date_format:H:i'],
        ], [
            'backup_keep.required' => 'Enter how many backups to keep.',
            'backup_time.required' => 'Enter the daily backup time.',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        SystemSettingController::put([
            'backup_keep'            => (int) $request->input('backup_keep'),
            'backup_include_uploads' => $request->boolean('backup_include_uploads') ? '1' : '0',
            'backup_time'            => $request->input('backup_time'),
        ]);

        return response()->json(['status' => true, 'message' => 'Backup settings saved.']);
    }

    private function pathOf(string $file): ?string
    {
        if (!preg_match(self::NAME_RULE, $file)) {
            return null;
        }
        $path = self::dir() . DIRECTORY_SEPARATOR . $file;

        return is_file($path) ? $path : null;
    }

    private static function size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < 3) {
            $v /= 1024;
            $i++;
        }

        return number_format($v, $i ? 1 : 0) . ' ' . $units[$i];
    }
}
