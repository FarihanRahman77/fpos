<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Attribute;
use App\Models\Admin\AttributeType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttributeController extends Controller
{
    public function index()
    {
        $attributeTypes = AttributeType::where('deleted', 'No')->get();
        return view('admin.inventory.attribute.index', ['attributeTypes' => $attributeTypes]);
    }


    public function getAttributes()
    {
        $attributes = Attribute::where('deleted', 'No')
            ->orderBy('id', 'DESC')
            ->get();

        $output = ['data' => []];

        $i = 1;

        foreach ($attributes as $attribute) {
            $attributeType = AttributeType::where('deleted', 'No')->where('id', $attribute->attribute_type_id)->first();

            $status = $attribute->status == 'Active'

                ? '<span class="badge bg-success"
                           onclick="changeAttributeStatus(' . $attribute->id . ')"
                           style="cursor:pointer;">Active</span>'

                : '<span class="badge bg-secondary"
                           onclick="changeAttributeStatus(' . $attribute->id . ')"
                           style="cursor:pointer;">Inactive</span>';


            $action = action_dropdown([
                ['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => 'editAttribute(' . $attribute->id . ')'],
                ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => 'deleteAttribute(' . $attribute->id . ')'],
            ]);


            $output['data'][] = [

                $i++,

                $attribute->name,

                $attribute->slug,

                $attributeType->name,

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
        $attribute->attribute_type_id = $request->attribute_type_id;
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
