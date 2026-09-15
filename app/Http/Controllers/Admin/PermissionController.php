<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PermissionController extends Controller
{
    public function index()
    {
        return view('admin.users.permissions.index');
    }

    public function list(Request $request)
    {
        $permissions = Permission::where('deleted', 'No')
            ->orderBy('module')
            ->orderBy('id', 'desc')
            ->get();

        $data = [];

        foreach ($permissions as $permission) {

            $status = $permission->status == 'Active'
                ? '<span class="badge badge-success">Active</span>'
                : '<span class="badge badge-danger">Inactive</span>';

            $actions = '';

            $actions .= '
                <button type="button"
                        class="btn btn-sm btn-info editPermission"
                        data-id="' . $permission->id . '"
                        title="Edit">
                    <i class="fa fa-edit"></i>
                </button>
            ';

            $actions .= '
                <button type="button"
                        class="btn btn-sm btn-danger deletePermission"
                        data-id="' . $permission->id . '"
                        title="Delete">
                    <i class="fa fa-trash"></i>
                </button>
            ';

            $data[] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'module' => $permission->module ?? '',
                'description' => $permission->description ?? '',
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
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:200|unique:permissions,slug',
            'module' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);

        $permission = new Permission();

        $permission->name = $request->name;

        if ($request->filled('slug')) {
            $permission->slug = Str::slug($request->slug);
        } else {
            $permission->slug = Str::slug($request->name);
        }

        $permission->module = $request->module;
        $permission->description = $request->description;
        $permission->status = $request->status;
        $permission->deleted = 'No';

        $permission->created_by = Auth::id();
        $permission->updated_by = Auth::id();

        $permission->save();

        return response()->json([
            'status' => true,
            'message' => 'Permission created successfully.'
        ]);
    }

    public function edit($id)
    {
        $permission = Permission::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$permission) {
            return response()->json([
                'status' => false,
                'message' => 'Permission not found.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $permission
        ]);
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$permission) {
            return response()->json([
                'status' => false,
                'message' => 'Permission not found.'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:200|unique:permissions,slug,' . $id,
            'module' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);

        $permission->name = $request->name;

        if ($request->filled('slug')) {
            $permission->slug = Str::slug($request->slug);
        } else {
            $permission->slug = Str::slug($request->name);
        }

        $permission->module = $request->module;
        $permission->description = $request->description;
        $permission->status = $request->status;
        $permission->updated_by = Auth::id();

        $permission->save();

        return response()->json([
            'status' => true,
            'message' => 'Permission updated successfully.'
        ]);
    }

    public function destroy($id)
    {
        $permission = Permission::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$permission) {
            return response()->json([
                'status' => false,
                'message' => 'Permission not found.'
            ], 404);
        }

        $permission->deleted = 'Yes';
        $permission->updated_by = Auth::id();
        $permission->save();

        return response()->json([
            'status' => true,
            'message' => 'Permission deleted successfully.'
        ]);
    }
}
