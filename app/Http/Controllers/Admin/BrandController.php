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


            $action = action_dropdown([
                ['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => 'editBrand(' . $brand->id . ')'],
                ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => 'deleteBrand(' . $brand->id . ')'],
            ]);


            $output['data'][] = [

                $i++,

                e($brand->name),

                e($brand->slug),

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
            'name' => 'required|string|max:255',
            'status' => 'nullable|in:Active,Inactive',
        ]);

        $duplicate = Brand::where('deleted', 'No')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($request->name))])
            ->when($request->id, fn ($q) => $q->where('id', '<>', $request->id))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status' => false,
                'message' => 'A brand with this name already exists.',
                'errors' => ['name' => ['A brand with this name already exists.']],
            ], 422);
        }


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
            $brand->status = $request->status ?: $brand->status;

        } else {

            $brand = new Brand();

            $brand->deleted = 'No';
            $brand->status = 'Active';
            $brand->created_by = auth()->id();
        }


        $brand->name = trim($request->name);
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


        if (\Illuminate\Support\Facades\DB::table('products')->where('brand_id', $brand->id)->where('deleted', 'No')->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Products use this brand, so it cannot be deleted. Change their brand or make it Inactive.'
            ], 422);
        }

        $brand->deleted = 'Yes';
        $brand->status = 'Inactive';
        $brand->deleted_by = auth()->id();
        $brand->deleted_date = now();
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
