<?php

namespace App\Http\Controllers\Api\Supervisor;

use Carbon\Carbon;
use App\Models\WeekLog;
use App\Models\AssignGig;
use Illuminate\Http\Request;
use App\Models\IncidentReport;
use App\Models\TimeSheet;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class SupervisorDswIncidentReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Supervisor']);
    }

    public function report_incident(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required'],
            'description' => ['required'],
            'incident_time' => ['required'],
            'gig_id' => ['required', 'exists:gigs,id']
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

        // Create a new incident report with the validated data
        $incidentReport = IncidentReport::create([
            'title' => $request->title,
            'description' => $request->description,
            'incident_time' => $request->incident_time,
            'incident_date' => Carbon::parse($request->incident_time)->format('m-d-Y'),
            'incident_week_number' => $now->weekOfYear,
            'incident_year' => Carbon::parse($request->incident_time)->format('Y'),
            'timesheet_id' => $timesheet->unique_id,
            'activity_id' => $lastActivityId,
            'gig_id' => $request->gig_id,
            'user_id' => auth('api')->user()->id
        ]);
        WeekLog::create([
            'title'=> auth('api')->user()->last_name." ".auth('api')->user()->first_name." reported an incident",
            'week_number' => $now->weekOfYear,
            'year' => $now->year,
            'day' => $now->format('l'),
            'time' => $now->format('h:i A'),
            'user_id' => auth('api')->user()->id,
            'type' => "Report Incident",
            'timesheet_id' => $timesheet->unique_id,
            'report_id' => $incidentReport->id
        ]);
        return response()->json(['status' => 200,'response' => 'Incident reported successfully','data' => $incidentReport], 200);
    }

    public function all_reported_incident(Request $request)
    {
        $incidentReport = IncidentReport::where([
            'user_id' => auth('api')->user()->id
        ])->with(['gig.client','user'])->get();
        if ($incidentReport->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Assigned Gig(s) does not exist'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Incident reported successfully','data' => $incidentReport], 200);
    }

    public function single_reported_incident(Request $request)
    {
        $incidentReport = IncidentReport::where(['user_id' => auth('api')->user()->id,'id'=>$request->incident_report_id])->with(['gig.client','user'])->first();
        // Check if the incident report was found
        if (!$incidentReport) {
            return response()->json(['status' => 404,'response' => 'Not Found','message' => 'No matching incident report found'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Incident reported successfully','data' => $incidentReport], 200);
    }  
}
