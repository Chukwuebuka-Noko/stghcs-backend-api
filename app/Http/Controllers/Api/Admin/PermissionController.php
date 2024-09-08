<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $permissions = Permission::get();
        if ($permissions->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Permission(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View Permissions',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all permissions at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($permissions),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Permissions fetched successfully","data"=>$permissions],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'unique:permissions,name'
            ]
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $permission = Permission::create([
            'name' => $request->name
        ]);
        ActivityLog::create([
            'action' => 'Created New Permission',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new permission at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $permission->id,
            'subject_type' => get_class($permission),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>201,'response'=>'Premission Created','message'=>'Permission created successfully','data'=>$permission], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Permission $permission)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'unique:permissions,name,'.$request->id
            ]
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $permission = Permission::find($request->id);
        if (!$permission) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }

        $permission->update([
            'name'=> $request->name,
        ]);
        
        ActivityLog::create([
            'action' => 'Updated Permission',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated a permission at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $permission->id,
            'subject_type' => get_class($permission),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Permission updated','message'=>'Permission updated successfully','data'=>$permission], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $permission = Permission::find($request->id);
        if (!$permission) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $permission->delete();
        ActivityLog::create([
            'action' => 'Deleted A Permission',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a permission at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($permission),
            'subject_id'=> $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>204,'response'=>'No Content','message' => 'Permission Deleted successfully']);
    }
}
