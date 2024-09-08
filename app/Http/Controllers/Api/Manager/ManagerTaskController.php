<?php

namespace App\Http\Controllers\Api\Manager;

use Carbon\Carbon;
use App\Models\Task;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ManagerTaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware(['role:Manager']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tasks = Task::where(['created_by' => auth('api')->user()->id])->get();
        if ($tasks->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Task(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View All Tasks',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all tasks at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($tasks),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Tasks fetched successfully","data"=>$tasks],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required','string'],
            'description' => ['nullable','string'],
            'location' => ['nullable','string'],
            'start_date' => ['required'],
            'end_date' => ['nullable'],
            'assign_to' => ['required', 'exists:users,id'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $task = Task::create([
            'title' => $request->title,
            'description' => $request->description,
            'location' => $request->location,
            'start_date' => $request->start_date,
            'end_date' => $request->end_start,
            'assigned_to' => $request->assign_to,
            'created_by' => auth('api')->user()->id
        ]);

        ActivityLog::create([
            'action' => 'Created New User',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created a new task at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $task->id,
            'subject_type' => get_class($task),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>201,'response'=>'Task Created','message'=>'Task created successfully','data'=>$task], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $task = Task::where(['id' => $request->id, 'created_by' => auth('api')->user()->id])->first();
        if (!$task) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Task does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View A User Profile',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a task at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $task->id,
            'subject_type' => get_class($task),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful','message'=>'Task successfully fetched', 'data'=>$task], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required','string'],
            'description' => ['nullable','string'],
            'location' => ['nullable','string'],
            'start_date' => ['required'],
            'end_date' => ['nullable'],
            'assign_to' => ['required', 'exists:users,id'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $task = Task::find($request->id);

        $task->update([
            'title' => $request->title,
            'description' => $request->description,
            'location' => $request->location,
            'start_date' => $request->start_date,
            'end_date' => $request->end_start,
            'assigned_to' => $request->assign_to,
            'created_by' => auth('api')->user()->id
        ]);
        ActivityLog::create([
            'action' => 'Updated User',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated a task at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $task->id,
            'subject_type' => get_class($task),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'User Updated','message'=>'User updated successfully','data'=>$task], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $task = Task::find($request->id);
        if (!$task) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $task->delete();
        ActivityLog::create([
            'action' => 'Deleted A Task',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a task at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($task),
            'subject_id' => $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>204,'response'=>'No Content','message' => 'Task Deleted successfully']);
    }
}
