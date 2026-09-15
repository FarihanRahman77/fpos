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
                               onclick="editUnit(' . $unit->id . ')">

                                <i class="fas fa-edit me-2"></i>
                                Edit

                            </a>
                        </li>

                        <li>
                            <a href="javascript:void(0)"
                               class="dropdown-item text-danger"
                               onclick="deleteUnit(' . $unit->id . ')">

                                <i class="fas fa-trash me-2"></i>
                                Delete

                            </a>
                        </li>

                    </ul>

                </div>
            ';


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
