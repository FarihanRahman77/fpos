<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Role;
use App\Models\Admin\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        return view('admin.users.roles.index');
    }

    public function list(Request $request)
    {
        $roles = Role::where('deleted', 'No')
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        $status='';
        foreach ($roles as $role) {

            $permissionCount = DB::table('role_permissions')
                ->where('role_id', $role->id)
                ->count();

            $status = $role->status == 'Active'
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';

            $items = [];
            if (PermissionController::allows('roles.edit')) {
                $items[] = ['label' => 'Edit', 'icon' => 'fas fa-edit', 'class' => 'editRole', 'attrs' => ['data-id' => $role->id]];
            }
            $items[] = ['label' => 'Permissions', 'icon' => 'fas fa-key', 'href' => route('admin.roles.permissions', $role->id)];
            if (PermissionController::allows('roles.delete') && $role->slug !== PermissionController::SUPERADMIN) {
                $items[] = ['divider' => true];
                $items[] = ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger deleteRole', 'attrs' => ['data-id' => $role->id]];
            }
            $actions = action_dropdown($items);
            if ($role->slug === PermissionController::SUPERADMIN) {
                $permissionCount = 'All (always)';
            }

            $data[] = [
                'id' => $role->id,
                'name' => e($role->name),
                'slug' => e($role->slug),
                'description' => e($role->description ?? ''),
                'permissions' => $permissionCount,
                'status' => $status,
                'actions' => $actions,
            ];
        }

        return response()->json([
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);

        if (Str::slug($request->name) === PermissionController::SUPERADMIN) {
            return response()->json(['status' => false, 'message' => 'There is already a Superadmin role. Choose another name.',
                'errors' => ['name' => ['There is already a Superadmin role. Choose another name.']]], 422);
        }

        $role = new Role();

        $role->name = $request->name;
        $role->slug = Str::slug($request->name);
        $role->description = $request->description;
        $role->status = $request->status;
        $role->deleted = 'No';
        $role->created_by = Auth::id();
        $role->updated_by = Auth::id();

        $role->save();

        return response()->json([
            'status' => true,
            'message' => 'Role created successfully.'
        ]);
    }

    public function edit($id)
    {
        $role = Role::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $role
        ]);
    }

    public function update(Request $request, $id)
    {
        $role = Role::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found.'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);

        // The Superadmin role is recognised by its slug and must stay active; nobody may switch off their own role.
        $isSuper = $role->slug === PermissionController::SUPERADMIN;
        $problem = null;
        if (($isSuper || (int) $role->id === (int) Auth::user()->role_id) && $request->status !== 'Active') {
            $problem = $isSuper ? 'The Superadmin role must stay active.' : 'You cannot make your own role inactive — you would lock yourself out.';
        } elseif (!$isSuper && Str::slug($request->name) === PermissionController::SUPERADMIN) {
            $problem = 'There is already a Superadmin role. Choose another name.';
        }
        if ($problem) {
            return response()->json(['status' => false, 'message' => $problem, 'errors' => ['status' => [$problem]]], 422);
        }

        $role->name = $request->name;
        $role->slug = $isSuper ? PermissionController::SUPERADMIN : Str::slug($request->name);
        $role->description = $request->description;
        $role->status = $request->status;
        $role->updated_by = Auth::id();

        $role->save();

        return response()->json([
            'status' => true,
            'message' => 'Role updated successfully.'
        ]);
    }

    public function destroy($id)
    {
        $role = Role::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found.'
            ], 404);
        }

        if ($role->slug === PermissionController::SUPERADMIN) {
            return response()->json(['status' => false, 'message' => 'The Superadmin role cannot be deleted.'], 422);
        }

        $inUse = DB::table('users')->where('role_id', $role->id)->where('deleted', 'No')->count();
        if ($inUse) {
            return response()->json([
                'status' => false,
                'message' => $inUse . ' user(s) still have this role. Give them another role first.'
            ], 422);
        }

        $role->deleted = 'Yes';
        $role->updated_by = Auth::id();
        $role->save();

        DB::table('role_permissions')
            ->where('role_id', $id)
            ->delete();

        return response()->json([
            'status' => true,
            'message' => 'Role deleted successfully.'
        ]);
    }

    /** Role permission grid: modules × actions, with presets. */
    public function permissions($id)
    {
        $role = Role::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$role) {
            abort(404);
        }

        $permissions = Permission::where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        $assignedPermissions = DB::table('role_permissions')
            ->where('role_id', $id)
            ->pluck('permission_id')
            ->toArray();

        $idBySlug = $permissions->pluck('id', 'slug')->all();
        $catalogue = PermissionController::catalogue();

        // group => [module => [label, [action => permission id|null]]]
        $grid = [];
        foreach (PermissionController::MODULES as $module => [$label, $group, $actions]) {
            $cells = [];
            foreach (array_keys(PermissionController::ACTIONS) as $action) {
                $cells[$action] = in_array($action, $actions, true) ? ($idBySlug[$module . '.' . $action] ?? null) : null;
            }
            $grid[$group][$module] = [$label, $cells];
        }
        $others = $permissions->filter(fn ($p) => !isset($catalogue[$p->slug]))->values();

        $presets = [];
        foreach (PermissionController::presets() as $key => [$label, $slugs]) {
            $presets[$key] = ['label' => $label, 'ids' => array_values(array_filter(array_map(fn ($s) => $idBySlug[$s] ?? null, $slugs)))];
        }

        $isSuper = $role->slug === PermissionController::SUPERADMIN;
        $canEdit = PermissionController::allows('roles.edit');

        return view(
            'admin.users.roles.permissions',
            compact('role', 'grid', 'others', 'assignedPermissions', 'presets', 'isSuper', 'canEdit')
        );
    }

    public function updatePermissions(Request $request, $id)
    {
        $role = Role::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found.'
            ], 404);
        }

        $permissionIds = $request->input('permissions', []);

        if (!is_array($permissionIds)) {
            $permissionIds = [];
        }

        $chosen = Permission::whereIn('id', $permissionIds)
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->pluck('slug', 'id');
        $permissionIds = $chosen->keys()->all();

        // Nobody may take Roles & permissions away from their own role (they could not undo it).
        if ((int) $role->id === (int) Auth::user()->role_id && $role->slug !== PermissionController::SUPERADMIN
            && (!$chosen->contains('roles.view') || !$chosen->contains('roles.edit'))) {
            return response()->json([
                'status' => false,
                'message' => 'This is your own role: keep "View" and "Edit" on Roles & permissions, or you would lock yourself out.',
            ], 422);
        }

        DB::beginTransaction();

        try {

            DB::table('role_permissions')
                ->where('role_id', $id)
                ->delete();

            foreach ($permissionIds as $permissionId) {

                DB::table('role_permissions')->insert([
                    'role_id' => $id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            PermissionController::flushCache();

            return response()->json([
                'status' => true,
                'message' => 'Permissions saved: ' . count($permissionIds) . ' allowed.'
                    . ($role->slug === PermissionController::SUPERADMIN ? ' (Superadmin always has full access.)' : ''),
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => self::safeError($e)
            ], 500);
        }
    }
}
