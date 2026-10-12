<?php

namespace App\Traits;

trait SequentialCodeTrait
{
    /**
     * Generate the next sequential document code (e.g. INV-000008).
     *
     * Deleted rows are included so a number is never reused, and the read is
     * locked (FOR UPDATE) so two simultaneous saves cannot get the same number.
     * Must be called inside a DB transaction for the lock to hold.
     *
     * @param string $modelClass Eloquent model class, e.g. Sale::class
     * @param string $column     Code column, e.g. 'invoice_no'
     * @param string $prefix     Code prefix without dash, e.g. 'INV' ('' for plain numbers)
     * @param int    $pad        Zero-padding width of the number part
     * @param int    $offset     Extra increment (for several codes in one request)
     */
    public function nextSequentialCode(string $modelClass, string $column, string $prefix, int $pad = 6, int $offset = 0): string
    {
        $head = $prefix === '' ? '' : $prefix . '-';

        $last = $modelClass::query()
            ->when($head !== '', fn ($q) => $q->where($column, 'like', $head . '%'))
            ->lockForUpdate()
            ->max($column);

        $next = 1;
        if ($last && preg_match('/^' . preg_quote($head, '/') . '(\d+)/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $head . str_pad((string) ($next + $offset), $pad, '0', STR_PAD_LEFT);
    }
}
