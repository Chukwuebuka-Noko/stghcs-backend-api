<?php

namespace App\Http\Controllers\Api\Supervisor;

use Carbon\Carbon;
use App\Models\Gig;
use App\Models\User;
use App\Models\Schedule;
use App\Models\AssignGig;
use App\Models\TimeSheet;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class SupervisorAssignGigController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Supervisor']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get the authenticated user's location_id
        $userLocationId = auth()->user()->location_id;

        // Fetch all assigned gigs where the assignee has the same location_id
        $assign_gig = AssignGig::with(['assignee' => function($query) {
            $query->select('id', 'first_name', 'last_name', 'other_name', 'email', 'phone_number', 'location_id', 'gender', 'id_card', 'address1', 'address2', 'city', 'zip_code', 'dob', 'employee_id', 'points', 'email_verified_at', 'is_temporary_password', 'status', 'created_at', 'updated_at', 'deleted_at');
        }, 'gig.client', 'schedule'])
        ->whereHas('assignee', function ($query) use ($userLocationId) {
            $query->where('location_id', $userLocationId);
        })
        ->get();
        if ($assign_gig->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Assigned Gig(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View All gigs assign',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all gigs assign at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($assign_gig ),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"All Assigned gig(s) fetched successfully","data"=>$assign_gig ],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_id' => ['required', 'exists:gigs,id'],
            'user_id' => ['required', 'exists:users,id'],
            // 'schedule_id' => ['required', 'exists:schedules,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }
        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'User not found'], 404);
        }
        $schedule = Schedule::where('gig_id', $request->gig_id)->first();
        // Check if the Schedule was actually retrieved
        if ($schedule === null) {
            // Handling the case where the Schedule is not found
            return response()->json([
                'status' => 404,
                'message' => 'The requested Gig has no schedule.'
            ], 404);
        }
        $schedule_id = $schedule->id;
        
        // Decode the schedule JSON to check for conflicts
        $currentSchedule = json_decode($schedule->schedule, true);

        // Check if the user has already been assigned to a gig with the same time shift
        $existingAssignments = AssignGig::where('user_id', $request->user_id)
            ->with('schedule')
            ->get();

        foreach ($existingAssignments as $assignment) {
            $existingSchedule = json_decode($assignment->schedule->schedule, true);
            foreach ($currentSchedule as $currentShift) {
                foreach ($existingSchedule as $existingShift) {
                    if ($currentShift['day'] == $existingShift['day'] &&
                        $currentShift['start_time'] == $existingShift['start_time'] &&
                        $currentShift['end_time'] == $existingShift['end_time']) {
                        return response()->json([
                            'status' => 409,
                            'response' => 'Conflict',
                            'message' => 'User is already assigned to a gig with the same time shift.'
                        ], 409);
                    }
                }
            }
        }

        $assign_gig = AssignGig::create([
            'gig_id' => $request->gig_id,
            'user_id' => $request->user_id,
            'schedule_id' => $schedule_id,
        ]);

        $assigned_gig = AssignGig::where('id', $assign_gig->id)->with(['assignee' => function ($query) {
            $query->select('id', 'first_name', 'last_name', 'other_name', 'email', 'phone_number', 'location_id', 'gender', 'id_card', 'address1', 'address2', 'city', 'zip_code', 'dob', 'employee_id', 'points', 'email_verified_at', 'is_temporary_password', 'status', 'created_at', 'updated_at', 'deleted_at');
        }, 'gig.client','gig.supervisor', 'schedule'])->first();
        if (!$assigned_gig) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Assigned gig not found'], 404);
        }
        $unid = $this->generateUniqueAlphanumeric();
        TimeSheet::create([
            'gig_id' => $request->gig_id,
            'user_id' => $request->user_id,
            'unique_id' => $unid,
            'status' => 'started'
        ]);
        Gig::find($request->gig_id)->update(['status' => 'assigned']);
        ActivityLog::create([
            'action' => 'Gig has been assigned to ' . $user->last_name . ' ' . $user->first_name,
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' assigned gig to ' . $user->last_name . ' ' . $user->first_name . ' at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $assign_gig->id,
            'subject_type' => get_class($assign_gig),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status' => 201, 'response' => 'Assigned Gig', 'message' => 'Gig assigned successfully', 'data' => [$assigned_gig]], 201);
    }
    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        // Get the authenticated user's location_id
        $userLocationId = auth()->user()->location_id;
    
        // Fetch the first assigned gig where the assignee has the same location_id
        $assign_gig = AssignGig::with(['assignee' => function($query) {
            $query->select('id', 'first_name', 'last_name', 'other_name', 'email', 'phone_number', 'location_id', 'gender', 'id_card', 'address1', 'address2', 'city', 'zip_code', 'dob', 'employee_id', 'points', 'email_verified_at', 'is_temporary_password', 'status', 'created_at', 'updated_at', 'deleted_at');
        }, 'gig.client', 'schedule'])
        ->whereHas('assignee', function ($query) use ($userLocationId) {
            $query->where('location_id', $userLocationId);
        })
        ->first();
    
        if (is_null($assign_gig)) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Assigned Gig does not exist'], 404);
        }
    
        ActivityLog::create([
            'action' => 'View Assigned gig',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed an assigned gig at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => AssignGig::class,
            'user_id' => auth()->id(),
        ]);
    
        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Assigned gig fetched successfully', 'data' => $assign_gig], 200);
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_id' => ['required', 'exists:gigs,id'],
            'user_id' => ['required', 'exists:users,id'],
            // 'schedule_id' => ['required', 'exists:schedules,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }
        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'User not found'], 404);
        }
        $schedule = Schedule::where('gig_id', $request->gig_id)->first();
        if ($schedule === null) {
            return response()->json([
                'status' => 404,
                'message' => 'The requested Gig has no schedule.'
            ], 404);
        }
        $schedule_id = $schedule->id;

        // Decode the schedule JSON to check for conflicts
        $currentSchedule = json_decode($schedule->schedule, true);

        // Check if the user has already been assigned to a gig with the same time shift, excluding the current assignment
        $existingAssignments = AssignGig::where('user_id', $request->user_id)
            ->where('id', '!=', $request->id)
            ->with('schedule')
            ->get();

        foreach ($existingAssignments as $assignment) {
            $existingSchedule = json_decode($assignment->schedule->schedule, true);
            foreach ($currentSchedule as $currentShift) {
                foreach ($existingSchedule as $existingShift) {
                    if ($currentShift['day'] == $existingShift['day'] &&
                        $currentShift['start_time'] == $existingShift['start_time'] &&
                        $currentShift['end_time'] == $existingShift['end_time']) {
                        return response()->json([
                            'status' => 409,
                            'response' => 'Conflict',
                            'message' => 'User is already assigned to a gig with the same time shift.'
                        ], 409);
                    }
                }
            }
        }

        $assign_gig = AssignGig::find($request->id);
        if (!$assign_gig) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Assigned gig not found'], 404);
        }

        $assign_gig->update([
            'gig_id' => $request->gig_id,
            'user_id' => $request->user_id,
            'schedule_id' => $schedule_id,
        ]);

        $assigned_gig = AssignGig::where('id', $assign_gig->id)->with(['assignee' => function ($query) {
            $query->select('id', 'first_name', 'last_name', 'other_name', 'email', 'phone_number', 'location_id', 'gender', 'id_card', 'address1', 'address2', 'city', 'zip_code', 'dob', 'employee_id', 'points', 'email_verified_at', 'is_temporary_password', 'status', 'created_at', 'updated_at', 'deleted_at');
        }, 'gig.client', 'schedule'])->first();

        $unid = $this->generateUniqueAlphanumeric();
        TimeSheet::updateOrCreate(
            [
                'gig_id' => $request->gig_id,
                'user_id' => $request->user_id
            ],
            [
                'unique_id' => $unid,
                'status' => 'started'
            ]
        );

        $gig = Gig::find($request->gig_id);
        $gig->status = 'assigned';
        $gig->save();

        ActivityLog::create([
            'action' => 'Gig has been assigned to ' . $user->last_name . ' ' . $user->first_name . ' was updated',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' updated gig assigned to ' . $user->last_name . ' ' . $user->first_name . ' at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $assign_gig->id,
            'subject_type' => get_class($assign_gig),
            'user_id' => auth()->id(),
        ]);

        return response()->json(['status' => 200, 'response' => 'Updated Assigned Gig', 'message' => 'Assigned Gig updated successfully', 'data' => [$assigned_gig]], 200);
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $gig = AssignGig::find($request->id);
        if (!$gig) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $gig->delete();
        ActivityLog::create([
            'action' => 'Deleted Assigned Gig',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted assigned gig at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($gig),
            'subject_id' => $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>204,'response'=>'Not Content','message' => 'Assigned Gig Deleted successfully'],200);
    }
    public function generateUniqueAlphanumeric($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString . time(); // Append a timestamp to ensure uniqueness
    }
}
