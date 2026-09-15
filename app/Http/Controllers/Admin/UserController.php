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

        return view('admin.users.index', compact('roles'));
    }


    /*
    |--------------------------------------------------------------------------
    | List Users
    |--------------------------------------------------------------------------
    */
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

            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);


        $user = new User();

        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->role_id = $request->role_id;
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
            'data' => $user
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

            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);


        $user->name = $request->name;
        $user->email = $request->email;
        $user->role_id = $request->role_id;
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


        $user->deleted = 'Yes';
        $user->deleted_by =  Auth::id();
        $user->deleted_date = now();

        $user->save();


        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully.'
        ]);
    }
}
