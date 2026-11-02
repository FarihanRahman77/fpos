<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Admin\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        $roles = Role::where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $branches = \Illuminate\Support\Facades\DB::table('branches')->where('deleted', 'No')->where('status', 'Active')
            ->orderByDesc('is_main')->orderBy('name')->get(['id', 'name']);

        return view('admin.users.index', compact('roles', 'branches'));
    }


    /*
    |--------------------------------------------------------------------------
    | List Users
    |--------------------------------------------------------------------------
    */
    /** My profile: details, branch access and password change (each user sees only their own). */
    public function profile($id)
    {
        if ((int) $id !== (int) auth()->id()) {
            return redirect()->route('admin.users.profile', auth()->id());
        }

        $user = \Illuminate\Support\Facades\DB::table('users as u')->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
            ->leftJoin('branches as b', 'b.id', '=', 'u.branch_id')->where('u.id', auth()->id())
            ->first(['u.*', 'r.name as role_name', 'b.name as home_branch']);
        $branches = \Illuminate\Support\Facades\DB::table('branches')
            ->whereIn('id', BranchController::allowedFor(auth()->user()) ?: [0])->orderByDesc('is_main')->orderBy('name')->pluck('name');

        return view('admin.users.profile', compact('user', 'branches'));
    }

    public function list(Request $request)
    {
        $users = User::query()
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.deleted', 'No')
            ->select(
                'users.id',
                'users.image',
                'users.name',
                'users.email',
                'users.designation',
                'users.role_id',
                'users.status',
                'roles.name as role_name'
            )
            ->orderBy('users.id', 'DESC')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $users
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Store User
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',

            'email' => 'required|email|max:255|unique:users,email',

            'password' => 'required|string|min:6',

            'role_id' => 'required|exists:roles,id',

            'designation' => 'nullable|string|max:255',

            'status' => 'required|in:Active,Inactive',

            'branch_id' => 'required|integer|exists:branches,id',

            'all_branches' => 'required|in:Yes,No',

            'branches' => 'nullable|array',

            'branches.*' => 'integer|exists:branches,id',

            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);


        $user = new User();

        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->role_id = $request->role_id;
        $user->branch_id = (int) $request->branch_id;
        $user->all_branches = $request->all_branches;
        $user->designation = $request->designation;
        $user->status = $request->status;
        $user->deleted = 'No';

        $user->created_by = Session::get('user_id') ?? auth()->id();
        $user->created_date = now();


        /*
        |--------------------------------------------------------------------------
        | Image Upload
        |--------------------------------------------------------------------------
        */
        if ($request->hasFile('image')) {

            $uploadPath = public_path('uploads/users');

            if (!File::exists($uploadPath)) {
                File::makeDirectory($uploadPath, 0755, true);
            }

            $image = $request->file('image');

            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

            $image->move($uploadPath, $imageName);

            $user->image = 'uploads/users/' . $imageName;
        }


        $user->save();

        // Branches this user may sign in to (home branch always included).
        \Illuminate\Support\Facades\DB::table('branch_user')->where('user_id', $user->id)->delete();
        foreach (collect($request->input('branches', []))->push($request->branch_id)->map(fn ($b) => (int) $b)->unique() as $b) {
            \Illuminate\Support\Facades\DB::table('branch_user')->insert(['user_id' => $user->id, 'branch_id' => $b]);
        }


        return response()->json([
            'status' => true,
            'message' => 'User created successfully.'
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Edit User
    |--------------------------------------------------------------------------
    */
    public function edit($id)
    {
        $user = User::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.'
            ], 404);
        }


        return response()->json([
            'status' => true,
            'data' => $user,
            'branches' => \Illuminate\Support\Facades\DB::table('branch_user')->where('user_id', $user->id)->pluck('branch_id'),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Update User
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        //return $request;
        $user = User::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.'
            ], 404);
        }


        $request->validate([
            'name' => 'required|string|max:255',

            'email' => 'required|email|max:255|unique:users,email,' . $id,

            'role_id' => 'required|exists:roles,id',

            'designation' => 'nullable|string|max:255',

            'status' => 'required|in:Active,Inactive',

            'branch_id' => 'required|integer|exists:branches,id',

            'all_branches' => 'required|in:Yes,No',

            'branches' => 'nullable|array',

            'branches.*' => 'integer|exists:branches,id',

            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);


        if ($request->status !== 'Active' && (int) $user->id === (int) auth()->id()) {
            return response()->json(['status' => false, 'message' => 'You cannot make your own account inactive while signed in to it.',
                'errors' => ['status' => ['You cannot make your own account inactive while signed in to it.']]], 422);
        }
        if ($request->status !== 'Active' && $this->isLastActiveUser($user)) {
            return response()->json(['status' => false, 'message' => 'This is the last active user; it must stay active.',
                'errors' => ['status' => ['This is the last active user; it must stay active.']]], 422);
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role_id = $request->role_id;
        $user->branch_id = (int) $request->branch_id;
        $user->all_branches = $request->all_branches;
        $user->designation = $request->designation;
        $user->status = $request->status;

        $user->updated_by = Session::get('user_id') ?? auth()->id();
        $user->updated_date = now();


        /*
        |--------------------------------------------------------------------------
        | Password
        |--------------------------------------------------------------------------
        */

        if (!empty($request->password)) {

            $request->validate([
                'password' => 'string|min:6'
            ]);

            $user->password = Hash::make($request->password);
        }


        /*
        |--------------------------------------------------------------------------
        | Image
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('image')) {

            $uploadPath = public_path('uploads/users');

            if (!File::exists($uploadPath)) {
                File::makeDirectory($uploadPath, 0755, true);
            }


            // Delete old image
            if (!empty($user->image)) {

                $oldImage = public_path($user->image);

                if (File::exists($oldImage)) {
                    File::delete($oldImage);
                }
            }


            $image = $request->file('image');

            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

            $image->move($uploadPath, $imageName);

            $user->image = 'uploads/users/' . $imageName;
        }


        $user->save();

        // Branches this user may sign in to (home branch always included).
        \Illuminate\Support\Facades\DB::table('branch_user')->where('user_id', $user->id)->delete();
        foreach (collect($request->input('branches', []))->push($request->branch_id)->map(fn ($b) => (int) $b)->unique() as $b) {
            \Illuminate\Support\Facades\DB::table('branch_user')->insert(['user_id' => $user->id, 'branch_id' => $b]);
        }


        return response()->json([
            'status' => true,
            'message' => 'User updated successfully.'
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Delete User
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {

        $user = User::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.'
            ], 404);
        }


        if ((int) $user->id === (int) auth()->id()) {
            return response()->json(['status' => false, 'message' => 'You cannot delete your own account while signed in to it.'], 422);
        }
        if ($this->isLastActiveUser($user)) {
            return response()->json(['status' => false, 'message' => 'This is the last active user. Add another active user before deleting it.'], 422);
        }

        $user->deleted = 'Yes';
        $user->deleted_by =  Auth::id();
        $user->deleted_date = now();

        $user->save();


        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully.'
        ]);
    }

    /** True when no other active, undeleted user would be left to sign in. */
    private function isLastActiveUser(User $user): bool
    {
        return !User::where('id', '!=', $user->id)->where('deleted', 'No')->where('status', 'Active')->exists();
    }
}
