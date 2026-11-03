<?php

if (!function_exists('action_dropdown')) {

    /**
     * Standard DataTable "Action" cell: a cog button opening a dropdown menu.
     * The JS twin is actionDropdown() in public/backend/js/main.js - keep them in step.
     *
     * Each item:
     *   label   string  (required)
     *   icon    string  e.g. 'fas fa-edit'
     *   onclick string  JS call, e.g. 'editCategory(5)'          } one of
     *   href    string  link URL                                  } these
     *   target  string  e.g. '_blank' (with href)
     *   class   string  extra classes, e.g. 'text-danger deleteBtn'
     *   attrs   array   extra attributes, e.g. ['data-id' => 5]
     *   divider bool    renders a separator instead of an item
     *   disabled bool   rendered greyed out, not clickable; title = its tooltip
     *   change  bool    the item changes the record (edit, delete, cancel, return, pay...). Guessed from the label
     *                   when not given.
     *
     * $branchId: the row's branch. When it is not the working branch, change items are disabled with the tooltip
     * "Sign in to <Branch> to change this" (records change only in their own branch).
     */
    function action_dropdown(array $items, $branchId = null): string
    {
        if ($branchId !== null && !\App\Http\Controllers\Admin\BranchController::owns($branchId)) {
            $items = branch_lock_actions($items, $branchId);
        }

        $html = '
                <div class="dropdown">
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary dropdown-toggle"
                            data-bs-toggle="dropdown"
                            data-bs-popper-config=\'{"strategy":"fixed"}\'
                            aria-expanded="false" title="Actions">
                        <i class="fas fa-cog"></i>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end">';

        foreach ($items as $item) {
            if (!empty($item['divider'])) {
                $html .= '<li><hr class="dropdown-divider"></li>';
                continue;
            }

            $attrs = '';
            foreach ($item['attrs'] ?? [] as $name => $value) {
                $attrs .= ' ' . $name . '="' . e((string) $value) . '"';
            }

            if (!empty($item['disabled'])) {
                $html .= '
                        <li><span class="dropdown-item disabled" aria-disabled="true" title="' . e($item['title'] ?? '') . '">'
                            . (isset($item['icon']) ? '<i class="' . e($item['icon']) . ' me-2"></i>' : '') . e($item['label'])
                            . (!empty($item['title']) ? '<small class="d-block text-muted">' . e($item['title']) . '</small>' : '') . '</span></li>';
                continue;
            }

            $link = isset($item['href'])
                ? 'href="' . e($item['href']) . '"' . (isset($item['target']) ? ' target="' . e($item['target']) . '"' : '')
                : 'href="javascript:void(0)"' . (isset($item['onclick']) ? ' onclick="' . e($item['onclick']) . '"' : '');

            $html .= '
                        <li>
                            <a ' . $link . '
                               class="dropdown-item' . (isset($item['class']) ? ' ' . e($item['class']) : '') . '"' . $attrs . '>
                                ' . (isset($item['icon']) ? '<i class="' . e($item['icon']) . ' me-2"></i>' : '') . '
                                ' . e($item['label']) . '
                            </a>
                        </li>';
        }

        return $html . '
                    </ul>
                </div>';
    }
}

if (!function_exists('branch_lock_actions')) {

    /** Disables the change items of a row that belongs to another branch (see action_dropdown()). */
    function branch_lock_actions(array $items, $branchId): array
    {
        $tip = 'Sign in to ' . \App\Http\Controllers\Admin\BranchController::name((int) $branchId) . ' to change this';

        return array_map(function ($item) use ($tip) {
            if (!empty($item['divider'])) {
                return $item;
            }
            $change = $item['change'] ?? (bool) preg_match('/\b(edit|delete|remove|cancel|return|deliver|delivery|receive|pay|payment|refund|settle|repay|repayment|change|reschedule|discard|continue|re-price|send|share|mark|reverse|approve|reject|post|process|adjust|convert|revise|reopen|close|status|activate|deactivate|recover)\b/i',
                ($item['label'] ?? '') . ' ' . ($item['class'] ?? ''));

            return $change ? ['label' => $item['label'], 'icon' => $item['icon'] ?? null, 'disabled' => true, 'title' => $tip] : $item;
        }, $items);
    }
}

if (!function_exists('branch_tag')) {

    /**
     * Compact branch label for a list row or report line — only when the user sees several branches
     * (a single-branch user sees no label). $plain = text only (PDF / Excel).
     */
    function branch_tag($branchId, bool $plain = false): string
    {
        $name = \App\Http\Controllers\Admin\BranchController::label($branchId);
        if ($name === '') {
            return '';
        }

        return $plain ? $name : '<small class="d-block text-muted text-nowrap" title="Branch"><i class="fas fa-code-branch me-1"></i>' . e($name) . '</small>';
    }
}

if (!function_exists('amount_in_words')) {

    /**
     * Convert a number to words using the lakh/crore system.
     * 150000.50 => "One Lakh Fifty Thousand and Fifty Paisa"
     */
    function amount_in_words($amount)
    {
        $amount = round((float) $amount, 2);
        $taka   = (int) floor($amount);
        $paisa  = (int) round(($amount - $taka) * 100);

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven',
                 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $two = function ($n) use ($ones, $tens) {
            return $n < 20 ? $ones[$n] : trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
        };

        $words = '';

        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$div, $name]) {
            if ($taka >= $div) {
                $words .= $two(intdiv($taka, $div)) . " $name ";
                $taka %= $div;
            }
        }

        $words = trim($words . ' ' . $two($taka));
        $words = $words ?: 'Zero';

        return $paisa > 0 ? "$words and " . $two($paisa) . ' Paisa' : $words;
    }
}
