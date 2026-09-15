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

            $actions = '';

            $actions .= '
                <button type="button"
                        class="btn btn-sm btn-info editRole"
                        data-id="' . $role->id . '"
                        title="Edit">
                    <i class="fa fa-edit"></i>
                </button>
            ';

            $actions .= '
                <a href="' . route('admin.roles.permissions', $role->id) . '"
                class="btn btn-sm btn-primary"
                title="Assign Permissions">
                    <i class="fa fa-key"></i>
                </a>
            ';

            $actions .= '
                <button type="button"
                        class="btn btn-sm btn-danger deleteRole"
                        data-id="' . $role->id . '"
                        title="Delete">
                    <i class="fa fa-trash"></i>
                </button>
            ';

            $data[] = [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description ?? '',
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

        $role->name = $request->name;
        $role->slug = Str::slug($request->name);
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

        return view(
            'admin.users.roles.permissions',
            compact(
                'role',
                'permissions',
                'assignedPermissions'
            )
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

        $permissionIds = Permission::whereIn('id', $permissionIds)
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->pluck('id')
            ->toArray();

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

            return response()->json([
                'status' => true,
                'message' => 'Permissions assigned successfully.'
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
