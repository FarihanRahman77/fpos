<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class AttributeTypeController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('admin.inventory.attribute_types.index');
    }


    /*
    |--------------------------------------------------------------------------
    | GET ATTRIBUTE TYPES
    |--------------------------------------------------------------------------
    */

    public function getAttributeTypes()
    {
        try {

            $attributeTypes = DB::table('attribute_types')
                ->select(
                    'id',
                    'name',
                    'slug',
                    'status',
                    'created_date'
                )
                ->where('deleted', 'No')
                ->orderBy('id', 'DESC')
                ->get();


            $output = [
                'status' => true,
                'data' => []
            ];


            $i = 1;


            foreach ($attributeTypes as $attributeType) {

                $attributeCount = DB::table('attributes')
                    ->where('attribute_type_id', $attributeType->id)
                    ->where('deleted', 'No')
                    ->count();


                $status = '';

                if ($attributeType->status == 'Active') {

                    $status = '
                        <i class="fas fa-check-circle text-success attributeTypeStatusBtn"
                           data-id="' . $attributeType->id . '"
                           title="Active"
                           style="font-size:18px; cursor:pointer;">
                        </i>
                    ';

                } else {

                    $status = '
                        <i class="fas fa-times-circle text-danger attributeTypeStatusBtn"
                           data-id="' . $attributeType->id . '"
                           title="Inactive"
                           style="font-size:18px; cursor:pointer;">
                        </i>
                    ';
                }


                $actions = '
                    <div class="btn-group">

                        <button type="button"
                                class="btn btn-sm btn-primary editAttributeTypeBtn"
                                data-id="' . $attributeType->id . '"
                                title="Edit">

                            <i class="fas fa-edit"></i>

                        </button>


                        <button type="button"
                                class="btn btn-sm btn-danger deleteAttributeTypeBtn"
                                data-id="' . $attributeType->id . '"
                                title="Delete">

                            <i class="fas fa-trash"></i>

                        </button>

                    </div>
                ';


                $output['data'][] = [

                    $i++,

                    e($attributeType->name),

                    e($attributeType->slug),

                    '<span class="badge bg-info">'
                        . $attributeCount .
                    '</span>',

                    $attributeType->created_date
                        ? date('d-m-Y h:i A', strtotime($attributeType->created_date))
                        : '',

                    $status,

                    $actions
                ];
            }


            return response()->json($output);


        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150'
        ]);


        try {

            $name = trim($request->name);

            $slug = Str::slug($name);


            $exists = DB::table('attribute_types')
                ->where('slug', $slug)
                ->where('deleted', 'No')
                ->exists();


            if ($exists) {

                return response()->json([
                    'status' => false,
                    'message' => 'Attribute category already exists.'
                ]);
            }


            $attributeType = new \stdClass();

            $attributeType->name = $name;
            $attributeType->slug = $slug;


            DB::table('attribute_types')->insert([

                'name' => $name,

                'slug' => $slug,

                'deleted' => 'No',

                'deleted_by' => null,

                'created_date' => now(),

                'created_by' => Session::get('user_id'),

                'status' => 'Active',

                'updated_by' => null,

                'updated_date' => null,

                'created_at' => now(),

                'updated_at' => now()

            ]);


            return response()->json([
                'status' => true,
                'message' => 'Attribute category saved successfully.'
            ]);


        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        try {

            $attributeType = DB::table('attribute_types')
                ->where('id', $id)
                ->where('deleted', 'No')
                ->first();


            if (!$attributeType) {

                return response()->json([
                    'status' => false,
                    'message' => 'Attribute category not found.'
                ]);
            }


            return response()->json([
                'status' => true,
                'data' => $attributeType
            ]);


        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:150'
        ]);


        try {

            $attributeType = DB::table('attribute_types')
                ->where('id', $id)
                ->where('deleted', 'No')
                ->first();


            if (!$attributeType) {

                return response()->json([
                    'status' => false,
                    'message' => 'Attribute category not found.'
                ]);
            }


            $name = trim($request->name);

            $slug = Str::slug($name);


            $exists = DB::table('attribute_types')
                ->where('slug', $slug)
                ->where('id', '!=', $id)
                ->where('deleted', 'No')
                ->exists();


            if ($exists) {

                return response()->json([
                    'status' => false,
                    'message' => 'Another attribute category already has this name.'
                ]);
            }


            DB::table('attribute_types')
                ->where('id', $id)
                ->update([

                    'name' => $name,

                    'slug' => $slug,

                    'updated_by' => Session::get('user_id'),

                    'updated_date' => now(),

                    'updated_at' => now()

                ]);


            return response()->json([
                'status' => true,
                'message' => 'Attribute category updated successfully.'
            ]);


        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        try {

            $attributeType = DB::table('attribute_types')
                ->where('id', $id)
                ->where('deleted', 'No')
                ->first();


            if (!$attributeType) {

                return response()->json([
                    'status' => false,
                    'message' => 'Attribute category not found.'
                ]);
            }


            /*
             * Check whether attributes exist
             */

            $attributeCount = DB::table('attributes')
                ->where('attribute_type_id', $id)
                ->where('deleted', 'No')
                ->count();


            if ($attributeCount > 0) {

                return response()->json([
                    'status' => false,
                    'message' => 'This attribute category cannot be deleted because attributes are assigned to it.'
                ]);
            }


            DB::table('attribute_types')
                ->where('id', $id)
                ->update([

                    'deleted' => 'Yes',

                    'deleted_by' => Session::get('user_id'),

                    'updated_by' => Session::get('user_id'),

                    'updated_date' => now(),

                    'updated_at' => now()

                ]);


            return response()->json([
                'status' => true,
                'message' => 'Attribute category deleted successfully.'
            ]);


        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CHANGE STATUS
    |--------------------------------------------------------------------------
    */

    public function changeStatus($id)
    {
        try {

            $attributeType = DB::table('attribute_types')
                ->where('id', $id)
                ->where('deleted', 'No')
                ->first();


            if (!$attributeType) {

                return response()->json([
                    'status' => false,
                    'message' => 'Attribute category not found.'
                ]);
            }


            $newStatus =
                $attributeType->status == 'Active'
                ? 'Inactive'
                : 'Active';


            DB::table('attribute_types')
                ->where('id', $id)
                ->update([

                    'status' => $newStatus,

                    'updated_by' => Session::get('user_id'),

                    'updated_date' => now(),

                    'updated_at' => now()

                ]);


            return response()->json([
                'status' => true,
                'message' => 'Status changed successfully.'
            ]);


        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
