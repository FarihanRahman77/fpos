<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Throwable;

/** Small helpers shared by the Journal, Bill and Amount Transfer controllers. */
trait VoucherHelperTrait
{
    /** Columns for a soft delete. */
    protected function deletedColumns(): array
    {
        return [
            'deleted'          => 'Yes',
            'status'           => 'Inactive',
            'deleted_by'       => Auth::id(),
            'deleted_datetime' => now(),
        ];
    }

    /**
     * View / Edit / Delete dropdown calling view{Label}(id), edit{Label}(id), delete{Label}(id).
     * $extra = more action_dropdown() items, placed before Delete.
     */
    protected function actionButtons(string $label, int $id, array $extra = []): string
    {
        return action_dropdown(array_merge(
            [
                ['label' => 'View', 'icon' => 'fas fa-eye', 'onclick' => 'view' . $label . '(' . $id . ')'],
                ['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => 'edit' . $label . '(' . $id . ')'],
            ],
            $extra,
            [
                ['divider' => true],
                ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => 'delete' . $label . '(' . $id . ')'],
            ]
        ));
    }

    protected function nullIfBlank($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    protected function ctx(Throwable $e): array
    {
        return ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    }
}
