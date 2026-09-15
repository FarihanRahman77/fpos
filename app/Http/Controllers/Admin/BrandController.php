<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index()
    {
        return view('admin.inventory.brand.index');
    }


    public function getBrands()
    {
        $brands = Brand::where('deleted', 'No')
            ->orderBy('id', 'DESC')
            ->get();

        $output = ['data' => []];

        $i = 1;

        foreach ($brands as $brand) {

            $status = $brand->status == 'Active'

                ? '<span class="badge bg-success"
                           onclick="changeBrandStatus(' . $brand->id . ')"
                           style="cursor:pointer;">Active</span>'

                : '<span class="badge bg-secondary"
                           onclick="changeBrandStatus(' . $brand->id . ')"
                           style="cursor:pointer;">Inactive</span>';


            $action = '
                <div class="dropdown">

                    <button type="button"
                            class="btn btn-sm btn-primary dropdown-toggle"
                            data-bs-toggle="dropdown">

                        <i class="fas fa-cog"></i>

                    </button>

                    <ul class="dropdown-menu dropdown-menu-end">

                        <li>
                            <a href="javascript:void(0)"
                               class="dropdown-item"
                               onclick="editBrand(' . $brand->id . ')">

                                <i class="fas fa-edit me-2"></i>
                                Edit

                            </a>
                        </li>

                        <li>
                            <a href="javascript:void(0)"
                               class="dropdown-item text-danger"
                               onclick="deleteBrand(' . $brand->id . ')">

                                <i class="fas fa-trash me-2"></i>
                                Delete

                            </a>
                        </li>

                    </ul>

                </div>
            ';


            $output['data'][] = [

                $i++,

                $brand->name,

                $brand->slug,

                $status,

                $action

            ];
        }


        return response()->json($output);
    }


    public function save(Request $request)
    {
        //return $request;
        $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:255'
        ]);


        if ($request->id) {

            $brand = Brand::where('id', $request->id)
                ->where('deleted', 'No')
                ->first();

            if (!$brand) {

                return response()->json([
                    'status' => false,
                    'message' => 'Brand not found.'
                ], 404);
            }
            $brand->status = $request->status;

        } else {

            $brand = new Brand();

            $brand->deleted = 'No';
            $brand->status = 'Active';
            $brand->created_by = auth()->id();
        }


        $brand->name = $request->name;
        $brand->slug = Str::slug($request->name);


        if ($request->id) {
            $brand->updated_by = auth()->id();
        }


        $brand->save();


        return response()->json([
            'status' => true,
            'message' => $request->id
                ? 'Brand updated successfully.'
                : 'Brand created successfully.'
        ]);
    }


    public function edit($id)
    {
        $brand = Brand::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$brand) {

            return response()->json([
                'status' => false,
                'message' => 'Brand not found.'
            ], 404);
        }


        return response()->json([
            'status' => true,
            'data' => $brand
        ]);
    }


    public function delete($id)
    {
        $brand = Brand::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$brand) {

            return response()->json([
                'status' => false,
                'message' => 'Brand not found.'
            ], 404);
        }


        $brand->deleted = 'Yes';
        $brand->status = 'Inactive';
        $brand->updated_by = auth()->id();

        $brand->save();


        return response()->json([
            'status' => true,
            'message' => 'Brand deleted successfully.'
        ]);
    }


    public function status(Request $request)
    {
        $brand = Brand::where('id', $request->id)
            ->where('deleted', 'No')
            ->first();

        if (!$brand) {

            return response()->json([
                'status' => false,
                'message' => 'Brand not found.'
            ], 404);
        }


        if ($brand->status == 'Active') {

            $brand->status = 'Inactive';

        } else {

            $brand->status = 'Active';
        }

        $brand->updated_by = auth()->id();

        $brand->save();


        return response()->json([
            'status' => true,
            'message' => 'Brand status updated.'
        ]);
    }
}
