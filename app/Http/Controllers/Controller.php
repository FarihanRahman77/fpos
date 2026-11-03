<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Message for a caught exception that is safe to show a user: our own abort() messages pass through,
     * anything else (SQL errors, file paths, stack details) is written to the log and replaced by a plain sentence.
     */
    public static function safeError(Throwable $e, string $fallback = 'Something went wrong. Please try again or contact the administrator.'): string
    {
        if ($e instanceof HttpExceptionInterface && $e->getMessage() !== '') {
            return $e->getMessage();
        }
        report($e);

        return $fallback;
    }

    /** Attempts for a money-posting save that hits a deadlock / lock wait timeout (DB::transaction(fn, self::SAVE_ATTEMPTS) too). */
    public const SAVE_ATTEMPTS = 3;

    /**
     * Runs a save written with DB::beginTransaction() / commit() again when two users posted at once and MySQL
     * chose it as the deadlock victim (1213) or it timed out waiting for a lock (1205): up to SAVE_ATTEMPTS times
     * with a short pause. The body rolls back its own transaction and calls rethrowIfConcurrency() in its catch, so
     * such an exception reaches this loop while attempts are left; the last attempt's catch answers as usual.
     */
    public static function retryOnDeadlock(callable $body, int $attempts = self::SAVE_ATTEMPTS)
    {
        $outer = [self::$retriesLeft, self::$retryLevel];
        $level = DB::transactionLevel();   // 0 in a request; a test may wrap everything in one transaction
        for ($attempt = 1; ; $attempt++) {
            try {
                self::$retriesLeft = $attempts - $attempt;
                self::$retryLevel = $level;

                return $body($attempt);
            } catch (Throwable $e) {
                // retry only once the body has rolled its own transaction back
                if ($attempt >= $attempts || !self::isConcurrencyError($e) || DB::transactionLevel() !== $level) {
                    throw $e;
                }
                usleep(random_int(50, 150) * 1000 * $attempt);
            } finally {
                [self::$retriesLeft, self::$retryLevel] = $outer;
            }
        }
    }

    /** Attempts left in the retryOnDeadlock() loop now running (0 = last attempt, or none running). */
    private static int $retriesLeft = 0;

    /** Transaction level the running retryOnDeadlock() loop started at. */
    private static int $retryLevel = 0;

    /** Deadlock (1213 / SQLSTATE 40001) or lock wait timeout (1205), anywhere in the exception chain. */
    public static function isConcurrencyError(Throwable $e): bool
    {
        for ($x = $e; $x; $x = $x->getPrevious()) {
            $info = $x instanceof \PDOException ? ($x->errorInfo ?? null) : null;
            if ((string) $x->getCode() === '40001' || in_array((int) ($info[1] ?? 0), [1213, 1205], true)
                || str_contains($x->getMessage(), 'Deadlock found when trying to get lock')
                || str_contains($x->getMessage(), 'Lock wait timeout exceeded')) {
                return true;
            }
        }

        return false;
    }

    /** In a save's catch block, after DB::rollBack(): hand deadlocks / lock timeouts to retryOnDeadlock(). */
    public static function rethrowIfConcurrency(Throwable $e): void
    {
        // on the last attempt the save's own catch answers with its normal "please try again" message
        if (self::$retriesLeft > 0 && DB::transactionLevel() === self::$retryLevel && self::isConcurrencyError($e)) {
            throw $e;
        }
    }
}
