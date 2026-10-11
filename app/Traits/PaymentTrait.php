<?php

namespace App\Traits;

use App\Models\Admin\Payment;
use Carbon\Carbon;
use Exception;

trait PaymentTrait
{
    /**
     * Generate the next sequential voucher code (e.g. VO-000001).
     *
     * @param int $offset Optional offset to increment the next code
     * @return string
     */
    public function generatePaymentCode(int $offset = 0): string
    {
        $lastPayment = Payment::max('code');
        $nextP = $lastPayment ? ((int) preg_replace('/[^0-9]/', '', $lastPayment)) + 1 : 1;
        $nextP += $offset;

        return 'VO-' . str_pad($nextP, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Store a payment record (can be debit or credit).
     *
     * @param array $data
     * @return Payment
     */
    public function storePayment(array $data): Payment
    {
        $payment = new Payment();

        $payment->party_id       = $data['party_id'] ?? null;
        $payment->party_type     = $data['party_type'] ?? null;
        $payment->code           = !empty($data['code']) ? $data['code'] : $this->generatePaymentCode();
        $payment->payment_type   = $data['payment_type'] ?? null;

        if (!empty($data['datetime'])) {
            $payment->datetime = $data['datetime'];
        } elseif (!empty($data['date'])) {
            $payment->datetime = $data['date'] . ' ' . date('H:i:s');
        } else {
            $payment->datetime = now()->format('Y-m-d H:i:s');
        }

        $payment->debit          = isset($data['debit']) ? (float) $data['debit'] : 0;
        $payment->credit         = isset($data['credit']) ? (float) $data['credit'] : 0;

        // Relation references
        $payment->sale_id            = $data['sale_id'] ?? null;
        $payment->sale_return_id     = $data['sale_return_id'] ?? null;
        $payment->purchase_id        = $data['purchase_id'] ?? null;
        $payment->purchase_return_id = $data['purchase_return_id'] ?? null;
        $payment->expense_id         = $data['expense_id'] ?? null;
        $payment->bill_id            = $data['bill_id'] ?? null;
        $payment->order_id           = $data['order_id'] ?? null;

        // Payment method details
        $payment->payment_method            = $data['payment_method'] ?? 'Cash';
        $payment->payment_method_id         = $data['payment_method_id'] ?? null;
        $payment->payment_method_source     = $data['payment_method_source'] ?? null;
        $payment->payment_method_source_id  = $data['payment_method_source_id'] ?? null;
        $payment->payment_method_account    = $data['payment_method_account'] ?? null;
        $payment->payment_method_account_id = $data['payment_method_account_id'] ?? null;

        $payment->remarks          = $data['remarks'] ?? null;
        $payment->status           = $data['status'] ?? 'Active';
        $payment->deleted          = $data['deleted'] ?? 'No';
        $payment->created_by       = $data['created_by'] ?? (auth()->check() ? auth()->id() : null);
        $payment->created_datetime = now();

        $payment->save();

        return $payment;
    }

    /**
     * Store a Debit payment (Debit > 0, Credit = 0).
     *
     * @param array $data
     * @return Payment
     */
    public function storeDebitPayment(array $data): Payment
    {
        $amount = (float) ($data['amount'] ?? $data['debit'] ?? 0);
        $data['debit']  = $amount;
        $data['credit'] = 0;

        return $this->storePayment($data);
    }

    /**
     * Store a Credit payment (Credit > 0, Debit = 0).
     *
     * @param array $data
     * @return Payment
     */
    public function storeCreditPayment(array $data): Payment
    {
        $amount = (float) ($data['amount'] ?? $data['credit'] ?? 0);
        $data['debit']  = 0;
        $data['credit'] = $amount;

        return $this->storePayment($data);
    }

    /**
     * Soft delete payments by relation column (e.g. sale_id, purchase_id, order_id, etc.).
     *
     * @param string $column
     * @param mixed $value
     * @return int
     */
    public function deletePaymentsBy(string $column, $value): int
    {
        return Payment::where($column, $value)
            ->where('deleted', 'No')
            ->update([
                'deleted'          => 'Yes',
                'status'           => 'Inactive',
                'deleted_by'       => auth()->check() ? auth()->id() : null,
                'updated_by'       => auth()->check() ? auth()->id() : null,
                'updated_datetime' => now(),
                'deleted_datetime' => now(),
            ]);
    }
}

