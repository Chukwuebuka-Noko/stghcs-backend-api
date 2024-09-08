<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\Gig;
use App\Models\Schedule;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class SchedulesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $schedules = Schedule::get();
        if ($schedules->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Schedule(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View All Schedule',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all schedules at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($schedules),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Schedule fetched successfully","data"=>$schedules],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'days' => ['required'],
            'schedule' => ['required'],
            'gig_id' => ['required', 'exists:gigs,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }
        $days = json_encode($request->days);
        $schedule = json_encode($request->schedule);
        $schedule = Schedule::create([
            'gig_id' => $request->gig_id,
            'days' => $days,
            'schedule' => $schedule
        ]);
        ActivityLog::create([
            'action' => 'Created New schedule',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new schedule at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $schedule->id,
            'subject_type' => get_class($schedule),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>201,'response'=>'Schedule Created','message'=>'Schedule created successfully','data'=>$schedule], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $schedule = Schedule::find($request->id);
        if (!$schedule) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Schedule does not exist'], 404);
        }
        $gig = Gig::where('id', $schedule->id)->first();
        ActivityLog::create([
            'action' => 'View A Schedule Details',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a schedule details at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $schedule->id,
            'subject_type' => get_class($schedule),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful','message'=>'Schedule successfully fetched','data'=> $schedule ,'gig'=>$gig], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'days' => ['required'],
            'schedule' => ['required'],
            'gig_id' => ['required', 'exists:gigs,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }
        $days = json_encode($request->days);
        $schedule_update = json_encode($request->schedule);
        $schedule = Schedule::find($request->id);
        $schedule->update([
            'gig_id' => $request->gig_id,
            'days' => $days,
            'schedule' => $schedule_update
        ]);
        ActivityLog::create([
            'action' => 'Updated schedule',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated new schedule at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $schedule->id,
            'subject_type' => get_class($schedule),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Schedule Updated','message'=>'Schedule updated successfully','data'=>$schedule], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $schedule = Schedule::find($request->id);
        if (!$schedule) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $schedule->delete();
        ActivityLog::create([
            'action' => 'Deleted A chedule',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a schedule at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($schedule),
            'subject_id' => $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>204,'response'=>'No Content','message' => 'Schedule Deleted successfully']);
    }
}
