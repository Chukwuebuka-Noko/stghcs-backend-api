<?php

namespace App\Http\Controllers\Api\Billing;

use Carbon\Carbon;
use App\Models\Task;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class BillingTaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware(['role:Billing']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tasks = Task::all();
        
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
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $task = Task::where(['id' => $request->id])->first();
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
}
