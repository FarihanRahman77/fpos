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

                e($unit->name),

                e($unit->slug),

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
            'status' => 'nullable|in:Active,Inactive',
        ]);

        $duplicate = Unit::where('deleted', 'No')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($request->name))])
            ->when($request->id, fn ($q) => $q->where('id', '<>', $request->id))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status' => false,
                'message' => 'A unit with this name already exists.',
                'errors' => ['name' => ['A unit with this name already exists.']],
            ], 422);
        }


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
            $unit->status = $request->status ?: $unit->status;
        } else {

            $unit = new Unit();

            $unit->deleted = 'No';
            $unit->status = 'Active';
            $unit->created_by = auth()->id();
        }


        $unit->name = trim($request->name);
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


        if (\Illuminate\Support\Facades\DB::table('products')->where('unit_id', $unit->id)->where('deleted', 'No')->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Products use this unit, so it cannot be deleted. Change their unit or make it Inactive.'
            ], 422);
        }

        $unit->deleted = 'Yes';
        $unit->status = 'Inactive';
        $unit->deleted_by = auth()->id();
        $unit->deleted_date = now();
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
