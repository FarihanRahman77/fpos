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
     */
    function action_dropdown(array $items): string
    {
        $html = '
                <div class="dropdown">
                    <button type="button"
                            class="btn btn-sm btn-primary dropdown-toggle"
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
