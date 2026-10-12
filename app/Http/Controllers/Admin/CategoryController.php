<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Session;

class CategoryController extends Controller
{
    public function index()
    {
        return view('admin.inventory.category.index');
    }


    public function getCategories()
    {
        $categories = Category::where('deleted', 'No')
            ->orderBy('id', 'DESC')
            ->get();

        $output = array('data' => array());

        $i = 1;

        foreach ($categories as $category) {

            if ($category->status == 'Active') {

                $status = '<span class="badge bg-success status-btn"
                                onclick="changeCategoryStatus(' . $category->id . ')"
                                style="cursor:pointer;">
                                Active
                           </span>';

            } else {

                $status = '<span class="badge bg-secondary status-btn"
                                onclick="changeCategoryStatus(' . $category->id . ')"
                                style="cursor:pointer;">
                                Inactive
                           </span>';
            }


            $button = action_dropdown([
                ['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => 'editCategory(' . $category->id . ')'],
                ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => 'deleteCategory(' . $category->id . ')'],
            ]);


            $output['data'][] = array(

                $i++,

                $category->name,

                $category->slug,

                $status,

                $button

            );
        }

        return response()->json($output);
    }


    public function save(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:255'
        ]);


        if ($request->id) {

            $category = Category::where('id', $request->id)
                ->where('deleted', 'No')
                ->first();

            if (!$category) {

                return response()->json([
                    'status' => false,
                    'message' => 'Category not found.'
                ], 404);
            }

        } else {

            $category = new Category();

            $category->deleted = 'No';
            $category->status = 'Active';
            $category->created_by = auth()->id();
        }


        $category->name = $request->name;

        $category->slug = Str::slug($request->name);


        if ($request->id) {

            $category->updated_by = auth()->id();
        }


        $category->save();


        return response()->json([
            'status' => true,
            'message' => $request->id
                ? 'Category updated successfully.'
                : 'Category created successfully.'
        ]);
    }


    public function edit($id)
    {
        $category = Category::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$category) {

            return response()->json([
                'status' => false,
                'message' => 'Category not found.'
            ], 404);
        }


        return response()->json([
            'status' => true,
            'data' => $category
        ]);
    }


    public function delete(Request $request)
    {
        $category = Category::where('id', $request->id)
            ->where('deleted', 'No')
            ->first();

        if (!$category) {

            return response()->json([
                'status' => false,
                'message' => 'Category not found.'
            ], 404);
        }


        $category->deleted = 'Yes';
        $category->status = 'Inactive';
        $category->deleted_by = auth()->id();
        $category->deleted_date = now();

        $category->save();


        return response()->json([
            'status' => true,
            'message' => 'Category deleted successfully.'
        ]);
    }


    public function status(Request $request)
    {
        $category = Category::where('id', $request->id)
            ->where('deleted', 'No')
            ->first();

        if (!$category) {

            return response()->json([
                'status' => false,
                'message' => 'Category not found.'
            ], 404);
        }


        if ($category->status == 'Active') {

            $category->status = 'Inactive';

        } else {

            $category->status = 'Active';
        }


        $category->updated_by = auth()->id();

        $category->save();


        return response()->json([
            'status' => true,
            'message' => 'Category status updated.'
        ]);
    }
}
