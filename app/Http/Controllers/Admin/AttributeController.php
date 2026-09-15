<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttributeController extends Controller
{
    public function index()
    {
        return view('admin.inventory.attribute.index');
    }


    public function getAttributes()
    {
        $attributes = Attribute::where('deleted', 'No')
            ->orderBy('id', 'DESC')
            ->get();

        $output = ['data' => []];

        $i = 1;

        foreach ($attributes as $attribute) {

            $status = $attribute->status == 'Active'

                ? '<span class="badge bg-success"
                           onclick="changeAttributeStatus(' . $attribute->id . ')"
                           style="cursor:pointer;">Active</span>'

                : '<span class="badge bg-secondary"
                           onclick="changeAttributeStatus(' . $attribute->id . ')"
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
                               onclick="editAttribute(' . $attribute->id . ')">

                                <i class="fas fa-edit me-2"></i>
                                Edit

                            </a>
                        </li>

                        <li>
                            <a href="javascript:void(0)"
                               class="dropdown-item text-danger"
                               onclick="deleteAttribute(' . $attribute->id . ')">

                                <i class="fas fa-trash me-2"></i>
                                Delete

                            </a>
                        </li>

                    </ul>

                </div>
            ';


            $output['data'][] = [

                $i++,

                $attribute->name,

                $attribute->slug,

                $status,

                $action

            ];
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

            $attribute = Attribute::where('id', $request->id)
                ->where('deleted', 'No')
                ->first();

            if (!$attribute) {

                return response()->json([
                    'status' => false,
                    'message' => 'Attribute not found.'
                ], 404);
            }
            $attribute->status = $request->status;
        } else {

            $attribute = new Attribute();

            $attribute->deleted = 'No';
            $attribute->status = 'Active';
            $attribute->created_by = auth()->id();
        }


        $attribute->name = $request->name;

        $attribute->slug = Str::slug($request->name);


        if ($request->id) {
            $attribute->updated_by = auth()->id();
        }


        $attribute->save();


        return response()->json([
            'status' => true,
            'message' => $request->id
                ? 'Attribute updated successfully.'
                : 'Attribute created successfully.'
        ]);
    }


    public function edit($id)
    {
        $attribute = Attribute::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$attribute) {

            return response()->json([
                'status' => false,
                'message' => 'Attribute not found.'
            ], 404);
        }


        return response()->json([
            'status' => true,
            'data' => $attribute
        ]);
    }


    public function delete(Request $request)
    {
        $attribute = Attribute::where('id', $request->id)
            ->where('deleted', 'No')
            ->first();

        if (!$attribute) {

            return response()->json([
                'status' => false,
                'message' => 'Attribute not found.'
            ], 404);
        }


        $attribute->deleted = 'Yes';
        $attribute->updated_by = auth()->id();

        $attribute->save();


        return response()->json([
            'status' => true,
            'message' => 'Attribute deleted successfully.'
        ]);
    }


    public function status(Request $request)
    {
        $attribute = Attribute::where('id', $request->id)
            ->where('deleted', 'No')
            ->first();

        if (!$attribute) {

            return response()->json([
                'status' => false,
                'message' => 'Attribute not found.'
            ], 404);
        }


        $attribute->status =
            $attribute->status == 'Active'
                ? 'Inactive'
                : 'Active';

        $attribute->updated_by = auth()->id();

        $attribute->save();


        return response()->json([
            'status' => true,
            'message' => 'Attribute status updated.'
        ]);
    }
}
