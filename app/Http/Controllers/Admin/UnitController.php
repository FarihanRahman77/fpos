<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UnitController extends Controller
{
    public function index()
    {
        return view('admin.inventory.unit.index');
    }


    public function getUnits()
    {
        $units = Unit::where('deleted', 'No')
            ->orderBy('id', 'DESC')
            ->get();

        $output = ['data' => []];

        $i = 1;

        foreach ($units as $unit) {

            $status = $unit->status == 'Active'

                ? '<span class="badge bg-success"
                           onclick="changeUnitStatus(' . $unit->id . ')"
                           style="cursor:pointer;">Active</span>'

                : '<span class="badge bg-secondary"
                           onclick="changeUnitStatus(' . $unit->id . ')"
                           style="cursor:pointer;">Inactive</span>';


            $action = action_dropdown([
                ['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => 'editUnit(' . $unit->id . ')'],
                ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => 'deleteUnit(' . $unit->id . ')'],
            ]);


            $output['data'][] = [

                $i++,

                $unit->name,

                $unit->slug,

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

            $unit = Unit::where('id', $request->id)
                ->where('deleted', 'No')
                ->first();

            if (!$unit) {

                return response()->json([
                    'status' => false,
                    'message' => 'Unit not found.'
                ], 404);
            }
            $unit->status=$request->status;
        } else {

            $unit = new Unit();

            $unit->deleted = 'No';
            $unit->status = 'Active';
            $unit->created_by = auth()->id();
        }


        $unit->name = $request->name;
        $unit->slug = Str::slug($request->name);


        if ($request->id) {
            $unit->updated_by = auth()->id();
        }


        $unit->save();


        return response()->json([
            'status' => true,
            'message' => $request->id
                ? 'Unit updated successfully.'
                : 'Unit created successfully.'
        ]);
    }


    public function edit($id)
    {
        $unit = Unit::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$unit) {

            return response()->json([
                'status' => false,
                'message' => 'Unit not found.'
            ], 404);
        }


        return response()->json([
            'status' => true,
            'data' => $unit
        ]);
    }


    public function delete(Request $request)
    {
        $unit = Unit::where('id', $request->id)
            ->where('deleted', 'No')
            ->first();

        if (!$unit) {

            return response()->json([
                'status' => false,
                'message' => 'Unit not found.'
            ], 404);
        }


        $unit->deleted = 'Yes';
        $unit->status = 'Inactive';
        $unit->updated_by = auth()->id();

        $unit->save();


        return response()->json([
            'status' => true,
            'message' => 'Unit deleted successfully.'
        ]);
    }


    public function status(Request $request)
    {
        $unit = Unit::where('id', $request->id)
            ->where('deleted', 'No')
            ->first();

        if (!$unit) {

            return response()->json([
                'status' => false,
                'message' => 'Unit not found.'
            ], 404);
        }


        $unit->status =
            $unit->status == 'Active'
                ? 'Inactive'
                : 'Active';

        $unit->updated_by = auth()->id();

        $unit->save();


        return response()->json([
            'status' => true,
            'message' => 'Unit status updated.'
        ]);
    }
}
