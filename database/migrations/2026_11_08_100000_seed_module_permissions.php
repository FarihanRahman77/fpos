<?php

use App\Http\Controllers\Admin\PermissionController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Roles & permissions start being enforced (CheckPermission middleware). Seeds one permission per
     * module × action (PermissionController::MODULES), retires the old unused ones (user.index …), and gives
     * EVERY existing role every permission — so nothing changes until an administrator restricts a role.
     */
    public function up(): void
    {
        PermissionController::syncPermissions();

        $catalogue = array_keys(PermissionController::catalogue());
        $retired = DB::table('permissions')->whereNotIn('slug', $catalogue)->where('slug', 'not like', '%.%')->pluck('id');
        if ($retired->count()) {
            DB::table('role_permissions')->whereIn('permission_id', $retired)->delete();
            DB::table('permissions')->whereIn('id', $retired)->update(['deleted' => 'Yes', 'updated_at' => now()]);
        }

        $ids = DB::table('permissions')->whereIn('slug', $catalogue)->pluck('id')->all();
        foreach (DB::table('roles')->where('deleted', 'No')->pluck('id') as $roleId) {
            $have = DB::table('role_permissions')->where('role_id', $roleId)->pluck('permission_id')->all();
            $rows = [];
            foreach (array_diff($ids, $have) as $permissionId) {
                $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now()];
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('role_permissions')->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', array_keys(PermissionController::catalogue()))->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
