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

    /** View / Edit / Delete buttons calling view{Label}(id), edit{Label}(id), delete{Label}(id). */
    protected function actionButtons(string $label, int $id, string $extra = ''): string
    {
        return '
            <div class="btn-group btn-group-sm" role="group">' . $extra . '
                <button type="button" class="btn btn-outline-secondary" title="View" onclick="view' . $label . '(' . $id . ')">
                    <i class="fas fa-eye"></i>
                </button>
                <button type="button" class="btn btn-outline-primary" title="Edit" onclick="edit' . $label . '(' . $id . ')">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn btn-outline-danger" title="Delete" onclick="delete' . $label . '(' . $id . ')">
                    <i class="fas fa-trash"></i>
                </button>
            </div>';
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
