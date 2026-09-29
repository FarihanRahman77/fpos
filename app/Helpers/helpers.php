<?php

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
