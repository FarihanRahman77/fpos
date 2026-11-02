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

                e($attribute->name),

                e($attribute->slug),

                e($attributeType->name ?? '-'),

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
            'name' => 'required|string|max:255',
            'attribute_type_id' => 'required|integer|exists:attribute_types,id',
            'status' => 'nullable|in:Active,Inactive',
        ], [
            'attribute_type_id.required' => 'Choose the attribute category.',
        ]);

        // One name per attribute category (e.g. "Red" only once under "Color").
        $duplicate = Attribute::where('deleted', 'No')
            ->where('attribute_type_id', $request->attribute_type_id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($request->name))])
            ->when($request->id, fn ($q) => $q->where('id', '<>', $request->id))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status' => false,
                'message' => 'This attribute already exists in that category.',
                'errors' => ['name' => ['This attribute already exists in that category.']],
            ], 422);
        }


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
            $attribute->status = $request->status ?: $attribute->status;
        } else {

            $attribute = new Attribute();


            $attribute->deleted = 'No';
            $attribute->status = 'Active';
            $attribute->created_by = auth()->id();
        }


        $attribute->name = trim($request->name);
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


        // Products and their variants point at the attribute; removing it would leave them nameless.
        $inUse = \Illuminate\Support\Facades\DB::table('product_variants')->where('attribute_id', $attribute->id)->where('deleted', 'No')->exists()
            || \Illuminate\Support\Facades\DB::table('product_attributes')->where('attribute_id', $attribute->id)->where('deleted', 'No')->exists();

        if ($inUse) {
            return response()->json([
                'status' => false,
                'message' => 'This attribute is used by products, so it cannot be deleted. Make it Inactive instead.'
            ], 422);
        }

        $attribute->deleted = 'Yes';
        $attribute->status = 'Inactive';
        $attribute->deleted_by = auth()->id();
        $attribute->deleted_date = now();
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
