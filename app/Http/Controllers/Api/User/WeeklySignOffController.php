<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Carbon\Carbon;
use App\Models\WeekLog;
use App\Models\AssignGig;
use App\Models\TimeSheet;
use App\Models\Schedule;
use App\Models\WeeklySignOff;

class WeeklySignOffController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:DSW|CSP']);
    }

    public function sign_off(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sign_off_time' => ['required'],
            'week_number' => ['required'],
            'gig_id' => ['required', 'exists:gigs,id'],
            'support_worker_signature' => ['required'],
            'client_signature' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $assigned_gig = AssignGig::where(['gig_id' => $request->gig_id, 'user_id' => auth('api')->user()->id])->first();
        if(!$assigned_gig)
        {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'The gig is not assigned to the user'
            ], 404);
        }
        $timesheet = Timesheet::where(['gig_id' => $request->gig_id, 'user_id' => auth('api')->user()->id])->first();
        if(!$timesheet)
        {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'The gig has not timesheet'
            ], 404);
        }
        
        $schedule = Schedule::where(['gig_id' => $request->gig_id])->first();
        if(!$schedule)
        {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'The schedule not found'
            ], 404);
        }
        // Decode the schedule JSON data
        $scheduleArray = json_decode($schedule->schedule, true);
        $lastDay = ucfirst(end($scheduleArray)['day']);
        $signOffDay = Carbon::parse($request->sign_off_time)->format('l');
        if($lastDay != $signOffDay){
            return response()->json([
                'status' => 400,
                'response' => 'Conflict In Sign Off Day',
                'message' => 'Can`t Sign Off today because it is not the last schedule of the week'
            ], 404);
        }
        // Current date and time
        $now = Carbon::now();
        
        // Decode the activities JSON data
        $activities = json_decode($timesheet->activities, true);
    
        // Initialize variable for the last activity ID with null clock_out
        $lastActivityId = null;
    
        // Iterate over activities to find the last one with null clock_out
        foreach ($activities as $activity) {
            if (is_null($activity['clock_out'])) {
                $lastActivityId = $activity['activity_id'];
            }
        }
        
        // Upload support_worker_signature to Cloudinary
        /*$supportWorkerSignaturePath = $request->file('support_worker_signature')->getRealPath();
        $supportWorkerSignatureUpload = Cloudinary::upload($supportWorkerSignaturePath, [
            'folder' => 'signatures/support_worker'
        ]);
        $supportWorkerSignatureUrl = $supportWorkerSignatureUpload->getSecurePath();
    
        // Upload client_signature to Cloudinary
        $clientSignaturePath = $request->file('client_signature')->getRealPath();
        $clientSignatureUpload = Cloudinary::upload($clientSignaturePath, [
            'folder' => 'signatures/client'
        ]);
        $clientSignatureUrl = $clientSignatureUpload->getSecurePath();*/

        // Create a new sign_off report with the validated data
        $sign_offReport = WeeklySignOff::create([
            'sign_off_time' => $request->sign_off_time,
            'sign_off_date' => Carbon::parse($request->sign_off_time)->format('m-d-Y'),
            'sign_off_week_number' => $request->week_number,
            'sign_off_year' => Carbon::parse($request->sign_off_time)->format('Y'),
            'sign_off_day' => Carbon::parse($request->sign_off_time)->format('l'),
            'timesheet_id' => $timesheet->unique_id,
            'client_signature' => $request->client_signature,
            'support_worker_signature' => $request->support_worker_signature,
            'gig_id' => $request->gig_id,
            'support_worker_id' => auth('api')->user()->id
        ]);
        WeekLog::create([
            'title'=> auth('api')->user()->last_name." ".auth('api')->user()->first_name." Signed off for the week",
            'week_number' => $now->weekOfYear,
            'year' => $now->year,
            'day' => $now->format('l'),
            'time' => $now->format('h:i A'),
            'user_id' => auth('api')->user()->id,
            'type' => "Weekly Sign Off",
            'timesheet_id' => $timesheet->unique_id,
            'activity_id' => $lastActivityId
        ]);
        return response()->json(['status' => 200,'response' => 'Weekly Sign Off successfully','data' => $sign_offReport], 200);
    }
    
    public function all_sign_off(Request $request)
    {
        $signOff = WeeklySignOff::where([
            'support_worker_id' => auth('api')->user()->id
        ])->with(['gig.client','user'])->get();
        if ($signOff->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Assigned Gig(s) does not exist'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Sign Off(s) Fetched successfully','data' => $signOff], 200);
    }

    public function single_sign_off(Request $request)
    {
        $signOff = WeeklySignOff::where(['support_worker_id' => auth('api')->user()->id,'id'=>$request->sign_off_id])->with(['gig.client','user'])->first();
        // Check if the progress report was found
        if (!$signOff) {
            return response()->json(['status' => 404,'response' => 'Not Found','message' => 'No matching progress report found'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Sign Off Fetched successfully','data' => $signOff], 200);
    }
}
