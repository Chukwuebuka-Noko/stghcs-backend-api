<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        if ($roles->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Role(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View Roles',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all roles at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($roles),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Roles fetched successfully","data"=>$roles],200);
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'api'
        ]);
        ActivityLog::create([
            'action' => 'Created New Role',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new role at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $role->id,
            'subject_type' => get_class($role),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>201,'response'=>'Role Created','message'=>'Role created successfully','data'=>$role], 201);
    }
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }
        
        $role = Role::find($request->id);
        if (!$role) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $role->update([
            'name' => $request->name
        ]);
        ActivityLog::create([
            'action' => 'Updated Roles',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated a role at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $role->id,
            'subject_type' => get_class($role),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Role Updated','message'=>'Role updated successfully','data'=>$role], 201);
    }

    public function destroy(Request $request)
    {
        $role = Role::find($request->id);
        if (!$role) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $role->delete();
        ActivityLog::create([
            'action' => 'Deleted A Roles',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a role at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($role),
            'subject_id'=> $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>204,'response'=>'No Content','message' => 'Role Deleted successfully']);
    }

    public function givePermissionToRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'permission' => 'required'
        ]);

        $role = Role::findOrFail($request->role_id);
        $role->syncPermissions($request->permission);

        return response()->json(['status'=>200,'response'=>'Successful','message' => 'Permission(s) assigned to the role successfully']);
    }
}
