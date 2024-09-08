<?php

namespace App\Http\Controllers\Api\Manager;

use Carbon\Carbon;
use App\Models\Gig;
use App\Models\User;
use App\Models\GigType;
use App\Models\Schedule;
use App\Models\SupervisorInCharge;
use App\Models\AssignGig;
use App\Models\TimeSheet;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ManagerGigController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware(['role:Manager']);
    }
    /**
     * Display a listing of the resource.
     */
    /*public function index()
    {
        // Get the authenticated user's location_id
        $userLocationId = auth()->user()->location_id;

        // Fetch all gigs created by users with the same location_id
        $gigs = Gig::with(['client','schedule','supervisor','assignments.assignee'])
            ->whereHas('creator', function ($query) use ($userLocationId) {
                $query->where(['location_id'=> $userLocationId, 'status' => 'pending']);
            })->latest()->get();

        if ($gigs->isEmpty()) {
            return response()->json(['status' => 200, 'response' => 'Not Found', 'message' => 'Gig(s) does not exist', 'data' => $gigs], 200);
        }

        ActivityLog::create([
            'action' => 'View All Gigs',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all gigs at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => Gig::class,
            'user_id' => auth()->id(),
        ]);

        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Gigs fetched successfully', 'data' => $gigs], 200);
    }*/
    
    public function index()
{
    // Get the authenticated user's location_id
    $userLocationId = auth()->user()->location_id;

    // Fetch all gigs created by users with the same location_id and status not 'ended' or 'completed'
    $gigs = Gig::with(['client','schedule','supervisor','assignments.assignee'])
        ->whereHas('creator', function ($query) use ($userLocationId) {
            $query->where('location_id', $userLocationId)
                  ->where('status', 'pending');
        })
        ->whereNotIn('status', ['ended', 'completed'])
        ->latest()
        ->get();

    if ($gigs->isEmpty()) {
        return response()->json(['status' => 200, 'response' => 'Not Found', 'message' => 'Gig(s) does not exist', 'data' => $gigs], 200);
    }

    ActivityLog::create([
        'action' => 'View All Gigs',
        'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all gigs at ' . Carbon::now()->format('h:i:s A'),
        'subject_id' => auth()->id(),
        'subject_type' => Gig::class,
        'user_id' => auth()->id(),
    ]);

    return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Gigs fetched successfully', 'data' => $gigs], 200);
}


    /**
     * Store a newly created resource in storage.
     */

public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'title' => ['required', 'string'],
        'description' => ['required'],
        'client_id' => ['required', 'exists:clients,id'],
        'grace_period' => ['required', 'numeric', 'min:0', 'max:15'],
        'gig_type_id' => ['required', 'exists:gig_types,id'],
        'support_worker_id' => ['nullable', 'exists:users,id'],
        'start_date' => ['required', 'date_format:m-d-Y'],
        'end_date' => ['nullable', 'date_format:m-d-Y'],
        'days' => ['required'],
        'schedule' => ['required', 'array', 'max:7'], // Ensure it's an array and does not exceed 7 items
        'schedule.*.day' => ['required', 'string'],
        'schedule.*.start_time' => ['required', 'date_format:h:i A'],
        'schedule.*.end_time' => ['required', 'date_format:h:i A'],
    ]);

    if ($validator->fails()) {
        $errors = $validator->errors()->all();
        return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
    }

    // Additional validation for checking if schedule.*.day exists in days
    $days = $request->days;
    foreach ($request->schedule as $schedule) {
        if (!in_array($schedule['day'], $days)) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => ['invalid_day' => ["The day {$schedule['day']} is not valid or does not exist in provided days."]]], 422);
        }
    }

    // Extracting days from the schedule
    $scheduledDays = array_map(function ($item) {
        return $item['day'];
    }, $request->schedule);
    $scheduledDays = array_unique($scheduledDays); // Remove duplicates to simplify comparison

    // Checking if all days are covered
    $missingDays = array_diff($request->days, $scheduledDays);
    if (!empty($missingDays)) {
        return response()->json([
            'status' => 422,
            'response' => 'Unprocessable Content',
            'errors' => ['days' => ['Not all day(s) are covered in the schedule. Missing: ' . implode(', ', $missingDays)]]
        ], 422);
    }

    // Retrieve user by ID
    $user = User::find(auth()->user()->id);

    // Check if the user exists to avoid null object errors
    if ($user) {
        // Check for 'Admin', 'Manager', or 'Supervisor' roles
        if (!$user->hasRole('Admin') && !$user->hasRole('Manager') && !$user->hasRole('Supervisor')) {
            return response()->json(['message' => 'User is not a Manager or Supervisor.'], 403);
        }
    } else {
        return response()->json(['error' => 'User not found'], 404);
    }

    $gig_type = GigType::find($request->gig_type_id);
    if (!$gig_type) {
        return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Gig Type does not exist'], 404);
    }
    
    // Ensure the variable is defined
    $support_worker = null;

    // Validate support_worker_id if provided
    if ($request->filled('support_worker_id')) {
        $support_worker = User::find($request->support_worker_id);
        if ($support_worker) {
            // Check for schedule conflicts
            if ($this->hasScheduleConflict($support_worker->id, $request->schedule)) {
                return response()->json(['status' => 409, 'response' => 'Conflict', 'message' => 'This schedule conflicts with an active schedule.'], 409);
            }
        } else {
            return response()->json(['error' => 'Support Worker not found'], 404);
        }
    }
    
    if($support_worker != null){
        if (!$support_worker->hasRole('Supervisor')) {
            $supervisor = SupervisorInCharge::where('user_id', $support_worker->id)->first();
            if (is_null($supervisor)) {
                return response()->json(['status' => 409, 'response' => 'Conflict', 'message' => 'Support Worker('.$support_worker->first_name.') has no supervisor who is in charge.'], 409);
            }
        }
    }
    

    $gig = Gig::create([
        'gig_unique_id' => $this->generateUniqueId(),
        'title' => $request->title,
        'description' => $request->description,
        'client_id' => $request->client_id,
        'created_by' => auth()->user()->id,
        'grace_period' => $request->grace_period,
        'gig_type_id' => $gig_type->id,
        'gig_type' => $gig_type->title,
        'gig_type_shortcode' => $gig_type->shortcode,
        'start_date' => $request->start_date,
        'end_date' => $request->end_date
    ]);

    $days = json_encode($request->days);
    $schedule = json_encode($request->schedule);
    $schedule = Schedule::create([
        'gig_id' => $gig->id,
        'gig_unique_id' => $gig->gig_unique_id,
        'days' => $days,
        'schedule' => $schedule
    ]);

    // If no conflicts, assign the support worker to the gig
    if ($request->filled('support_worker_id')) {
        $assignResult = $this->assignSupportWorkerToGig($gig, $support_worker);
        if ($assignResult !== true) {
            return $assignResult;
        }
    }

    ActivityLog::create([
        'action' => 'Created New gig',
        'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' created new gig at ' . Carbon::now()->format('h:i:s A'),
        'subject_id' => $gig->id,
        'subject_type' => get_class($gig),
        'user_id' => auth()->id(),
    ]);

    return response()->json(['status' => 201, 'response' => 'Created Gig', 'message' => 'Gig created successfully', 'data' => ["gig" => $gig, "schedule" => $schedule]], 201);
}



protected function hasScheduleConflict($supportWorkerId, $newSchedule)
{
    $existingAssignments = AssignGig::where('user_id', $supportWorkerId)->with('schedule')->get();

    foreach ($existingAssignments as $assignment) {
        $existingSchedule = json_decode($assignment->schedule->schedule, true);

        foreach ($newSchedule as $newShift) {
            foreach ($existingSchedule as $existingShift) {
                if ($newShift['day'] == $existingShift['day']) {
                    $newStartTime = Carbon::parse($newShift['start_time']);
                    $newEndTime = Carbon::parse($newShift['end_time']);
                    $existingStartTime = Carbon::parse($existingShift['start_time']);
                    $existingEndTime = Carbon::parse($existingShift['end_time']);

                    if ($newStartTime->between($existingStartTime, $existingEndTime) || $newEndTime->between($existingStartTime, $existingEndTime)) {
                        return true;
                    }
                }
            }
        }
    }

    return false;
}


    // Function to handle additional code when dsw_id is not null
protected function assignSupportWorkerToGig($gig, $support_worker)
{
    $gig_id = $gig->id;
    $user_id = $support_worker->id;
    $user = User::find($user_id);

    Log::info("Assigning support worker", ['gig_id' => $gig_id, 'user_id' => $user_id]);

    if (!$user) {
        Log::error("User not found", ['user_id' => $user_id]);
        return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'User not found'], 404);
    }
    
    // Check if the support worker has the role of "Supervisor"
    if (!$user->hasRole('Supervisor')) {
        Log::info("User does not have the role of Supervisor", ['user_id' => $user_id]);
        $supervisor = SupervisorInCharge::where('user_id', $user_id)->first();
    }else{
        $supervisor = $user;
    }

    $schedule = Schedule::where('gig_id', $gig_id)->first();
    if (!$schedule) {
        Log::error("Schedule not found", ['gig_id' => $gig_id]);
        return response()->json(['status' => 404, 'message' => 'The requested Gig has no schedule.'], 404);
    }

    $schedule_id = $schedule->id;
    $currentSchedule = json_decode($schedule->schedule, true);
    
    // Check for weekly hours limit before assigning the gig
    $hourLimitCheck = $this->checkWeeklyHoursLimit($user_id, $currentSchedule);
    if ($hourLimitCheck !== true) {
        return $hourLimitCheck;
    }

    // Check for schedule conflicts before assigning the gig
    if ($this->hasScheduleConflict($user_id, $currentSchedule)) {
        Log::info("Schedule conflict detected", ['user_id' => $user_id, 'schedule' => $currentSchedule]);
        return response()->json(['status' => 409, 'response' => 'Conflict', 'message' => 'This schedule conflicts with an active schedule. But Shift has been created but was not assigned.']);
    }

    if (is_null($supervisor)) {
        Log::info("No supervisor found for user", ['user_id' => $user_id]);
        return true;
    }

    $supervisor_id = $supervisor->supervisor_id;
    Gig::where(['id' => $gig_id])
        ->update(['supervisor_id' => $supervisor_id]);

    $assign_gig = AssignGig::create([
        'gig_id' => $gig_id,
        'user_id' => $user_id,
        'schedule_id' => $schedule_id,
    ]);

    Log::info("Assign gig created", ['assign_gig_id' => $assign_gig->id]);

    $assigned_gig = AssignGig::where('id', $assign_gig->id)->with(['assignee' => function ($query) {
        $query->select('id', 'first_name', 'last_name', 'other_name', 'email', 'phone_number', 'location_id', 'gender', 'id_card', 'address1', 'address2', 'city', 'zip_code', 'dob', 'employee_id', 'points', 'email_verified_at', 'is_temporary_password', 'status', 'created_at', 'updated_at', 'deleted_at');
    }, 'gig.client', 'schedule'])->first();

    if (!$assigned_gig) {
        Log::error("Assigned gig not found", ['assign_gig_id' => $assign_gig->id]);
        return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Assigned gig not found'], 404);
    }

    $unid = $this->generateUniqueAlphanumeric();
    TimeSheet::create([
        'gig_id' => $gig_id,
        'user_id' => $user_id,
        'unique_id' => $unid,
        'status' => 'started'
    ]);

    Gig::find($gig_id)->update(['status' => 'assigned']);

    ActivityLog::create([
        'action' => 'Gig has been assigned to ' . $user->last_name . ' ' . $user->first_name,
        'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' assigned gig to ' . $user->last_name . ' ' . $user->first_name . ' at ' . Carbon::now()->format('h:i:s A'),
        'subject_id' => $assign_gig->id,
        'subject_type' => get_class($assign_gig),
        'user_id' => auth()->id(),
    ]);
    return true;
}

protected function checkWeeklyHoursLimit($supportWorkerId, $newSchedule)
{
    // Get the start and end time of the new gig
    $newGigHours = 0;
    foreach ($newSchedule as $shift) {
        $startTime = Carbon::parse($shift['start_time']);
        $endTime = Carbon::parse($shift['end_time']);
        $newGigHours += $endTime->diffInHours($startTime);
    }

    // Get all existing gig assignments for the support worker in the current week
    $weekStart = Carbon::now()->startOfWeek();
    $weekEnd = Carbon::now()->endOfWeek();

    $assignedGigs = AssignGig::where('user_id', $supportWorkerId)
        ->whereHas('gig', function ($query) use ($weekStart, $weekEnd) {
            $query->whereBetween('start_date', [$weekStart, $weekEnd]);
                  /*->orWhereBetween('end_date', [$weekStart, $weekEnd]);*/
        })
        ->with('schedule')
        ->get();

    $totalAssignedHours = 0;
    foreach ($assignedGigs as $assignedGig) {
        $assignedSchedule = json_decode($assignedGig->schedule->schedule, true);
        foreach ($assignedSchedule as $shift) {
            $startTime = Carbon::parse($shift['start_time']);
            $endTime = Carbon::parse($shift['end_time']);
            $totalAssignedHours += $endTime->diffInHours($startTime);
        }
    }

    $totalHours = $totalAssignedHours + $newGigHours;

    if ($totalAssignedHours >= 40) {
        return response()->json([
            'status' => 409,
            'response' => 'Conflict',
            'message' => 'The support worker has already reached the maximum allowed hours (40) in this week.'
        ], 409);
    }

    if ($totalHours > 40) {
        $remainingHours = 40 - $totalAssignedHours;
        return response()->json([
            'status' => 409,
            'response' => 'Conflict',
            'message' => 'The support worker can only take a gig that requires ' . $remainingHours . ' hour(s) or less this week.'
        ], 409);
    }

    return true;
}

    
    protected function updateAssignedSupportWorkerToGig($gig, $support_worker)
{
    $gig_id = $gig->id;
    $user_id = $support_worker->id;
    $user = User::find($user_id);
    if (!$user) {
        return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'User not found'], 404);
    }

    $schedule = Schedule::where('gig_id', $gig_id)->first();
    if (!$schedule) {
        return response()->json(['status' => 404, 'message' => 'The requested Gig has no schedule.'], 404);
    }

    $schedule_id = $schedule->id;
    $currentSchedule = json_decode($schedule->schedule, true);

    // Check for schedule conflicts before updating the assignment
    if ($this->hasScheduleConflict($user_id, $currentSchedule)) {
        return response()->json([
            'status' => 409,
            'response' => 'Conflict',
            'message' => 'This schedule conflicts with an active schedule.'
        ], 409);
    }

    $assign_gig = AssignGig::where(['gig_id' => $gig_id])->first();
    if (!$assign_gig) {
        return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Assigned gig not found'], 404);
    }

    if (!$user->hasRole('Supervisor')) {
        Log::info("User does not have the role of Supervisor", ['user_id' => $user_id]);
        $supervisor = SupervisorInCharge::where('user_id', $user_id)->first();
    }else{
        $supervisor = $user;
    }
    
    if (is_null($supervisor)) {
        return true;
    }

    $supervisor_id = $supervisor->supervisor_id;
    Gig::where(['id' => $gig_id])
        ->update(['supervisor_id' => $supervisor_id]);

    $assign_gig->update([
        'gig_id' => $gig_id,
        'user_id' => $user_id,
        'schedule_id' => $schedule_id,
    ]);

    $assigned_gig = AssignGig::where('id', $assign_gig->id)->with(['assignee' => function ($query) {
        $query->select('id', 'first_name', 'last_name', 'other_name', 'email', 'phone_number', 'location_id', 'gender', 'id_card', 'address1', 'address2', 'city', 'zip_code', 'dob', 'employee_id', 'points', 'email_verified_at', 'is_temporary_password', 'status', 'created_at', 'updated_at', 'deleted_at');
    }, 'gig.client', 'schedule'])->first();

    // Check if TimeSheet exists for the gig and user
    $timeSheet = TimeSheet::where('gig_id', $gig_id)->first();

    if (!$timeSheet) {
        $unid = $this->generateUniqueAlphanumeric();
        TimeSheet::create([
            'gig_id' => $gig_id,
            'user_id' => $user_id,
            'unique_id' => $unid,
            'status' => 'started'
        ]);
    } else {
        // Update the user_id only if TimeSheet already exists
        $timeSheet->update([
            'user_id' => $user_id,
            'status' => 'started'
        ]);
    }

    $gig = Gig::find($gig_id);
    $gig->status = 'assigned';
    $gig->save();

    Gig::find($gig_id)->update(['status' => 'assigned']);

    return true;
}


    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        // Get the authenticated user's location_id
        $userLocationId = auth()->user()->location_id;

        $gig = Gig::where('id', $request->id)->with(['schedule','supervisor','client','assignments.assignee'])->whereHas('creator', function ($query) use ($userLocationId) {
                $query->where('location_id', $userLocationId);
            })->first();
        if (!$gig) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Gig does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View A Gig Details',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed a gig details at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $gig->id,
            'subject_type' => get_class($gig),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Gig successfully fetched', 'data' => $gig], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string'],
            'description' => ['required'],
            'client_id' => ['required', 'exists:clients,id'],
            'grace_period' => ['required','numeric','min:0', 'max:15'],
            'gig_type_id' => ['required','exists:gig_types,id'],
            'start_date' => ['required','date_format:m-d-Y'],
            'end_date' => ['nullable','date_format:m-d-Y'],
            'support_worker_id' => ['nullable', 'exists:users,id'],
            'days' => ['required'],
            'schedule' => ['required','array','max:7'], // Ensure it's an array and does not exceed 7 items
            'schedule.*.day' => ['required','string'],
            'schedule.*.start_time' => ['required','date_format:H:i A'],
            'schedule.*.end_time' => ['required'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
        }

        // Additional validation for checking if schedule.*.day exists in days
        $days = $request->days;
        foreach ($request->schedule as $schedule) {
            if (!in_array($schedule['day'], $days)) {
                return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => ['invalid_day' => ["The day {$schedule['day']} is not valid or does not exist in provided days."]]], 422);
            }
        }

        // Extracting days from the schedule
        $scheduledDays = array_map(function ($item) {
            return $item['day'];
        }, $request->schedule);
        $scheduledDays = array_unique($scheduledDays); // Remove duplicates to simplify comparison

        // Checking if all days are covered
        $missingDays = array_diff($request->days, $scheduledDays);
        if (!empty($missingDays)) {
            return response()->json([
                'status' => 422,
                'response' => 'Unprocessable Content',
                'errors' => ['days' => ['Not all day(s) are covered in the schedule. Missing: ' . implode(', ', $missingDays)]]
            ], 422);
        }

        // Retrieve user by ID
        $user = User::find(auth()->user()->id);

        // Check if the user exists to avoid null object errors
        if ($user) {
            // Check for 'manager' or 'supervisor' roles
            if (!$user->hasRole('Admin') && !$user->hasRole('Manager') && !$user->hasRole('Supervisor')) {
                return response()->json(['message' => 'User is not a Manager or supervisor.']);
            }
        } else {
            return response()->json(['error' => 'User not found'], 404);
        }

        $gig = Gig::find($request->id);
        if (!$gig) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Gig not found'], 404);
        }

        $gig_type = GigType::find($request->gig_type_id);
        if (!$gig_type) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Gig Type does not exist'], 404);
        }
        
        // Validate support_worker_id if provided
        if ($request->has('support_worker_id')) {
            $support_worker = User::find($request->support_worker_id);
            if ($support_worker) {
                // Check for schedule conflicts before updating the gig
                if ($this->hasScheduleConflict($support_worker->id, $request->schedule)) {
                    return response()->json(['status' => 409, 'response' => 'Conflict', 'message' => 'This schedule conflicts with an active schedule.'], 409);
                }
            } else {
                return response()->json(['error' => 'Support Worker not found'], 404);
            }
        }
        
        if (!$support_worker->hasRole('Supervisor')) {
            $supervisor = SupervisorInCharge::where('user_id', $support_worker->id)->first();
            if (is_null($supervisor)) {
                return response()->json(['status' => 409, 'response' => 'Conflict', 'message' => 'Support Worker('.$support_worker->first_name.') has no supervisor who is in charge.'], 409);
            }
        }

        $gig->update([
            'title' => $request->title, 
            'description' => $request->description, 
            'client_id' => $request->client_id, 
            'created_by' => auth()->user()->id,
            'gig_type_id' => $gig_type->id,
            'gig_type' => $gig_type->title,
            'gig_type_shortcode' => $gig_type->shortcode,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date
        ]);

         $schedule = Schedule::where('gig_id', $request->id)->first();
        if (!$schedule) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Schedule not found'], 404);
        }
    
        $days = json_encode($request->days);
        $schedule_update = json_encode($request->schedule);
    
        // Only update gig_unique_id if it's passed
        if ($request->has('gig_unique_id')) {
            $existingSchedule = Schedule::where('gig_unique_id', $request->gig_unique_id)->first();
            $schedule->update([
                'gig_id' => $request->id,
                'gig_unique_id' => $request->gig_unique_id,
                'days' => $days,
                'schedule' => $schedule_update,
                'grace_period' => $request->grace_period
            ]);
        } else {
            $schedule->update([
                'gig_id' => $request->id,
                'days' => $days,
                'schedule' => $schedule_update,
                'grace_period' => $request->grace_period
            ]);
        }
        // If no conflicts, update the assigned support worker to the gig
        if ($request->has('support_worker_id')) {
            $this->updateAssignedSupportWorkerToGig($gig, $support_worker);
        }

        ActivityLog::create([
            'action' => 'Updated Gig',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' updated a gig at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $gig->id,
            'subject_type' => get_class($gig),
            'user_id' => auth()->id(),
        ]);

        return response()->json(['status' => 201, 'response' => 'Gig Updated', 'message' => 'Gig updated successfully', 'data' => ["gig"=>$gig, "schedule"=>$schedule]], 201);
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        // Start a database transaction
        DB::beginTransaction();

        try {
            $gig = Gig::find($request->gig_id);
            if (!$gig) {
                return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Gig not found!'], 404);
            }

            // Find the schedule using the correct method
            $schedule = Schedule::where('gig_id', $request->gig_id)->first();
            if (!$schedule) {
                return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Schedule not found!'], 404);
            }
            
            $time_sheet = TimeSheet::where('gig_id', $request->gig_id)->first();

            // Delete schedule first to handle foreign key constraints if any
            //$schedule->delete();
            // Then delete the gig
            $gig->update(['status' => 'ended']);
            if ($time_sheet) {
                $time_sheet->update(['status' => 'ended']);
            }

            // Log the activity
            ActivityLog::create([
                'action' => 'Ended A Gig',
                'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' ended a gig at ' . Carbon::now()->format('h:i:s A'),
                'subject_type' => get_class($gig),
                'subject_id' => $request->gig_id,
                'user_id' => auth()->id(),
            ]);

            // Commit the transaction
            DB::commit();

            return response()->json(['status' => 204, 'response' => 'Gig Ended', 'message' => 'Gig and schedule has been ended successfully']);
        } catch (\Exception $e) {
            // Rollback the transaction in case of an error
            DB::rollback();

            // Return an error response
            return response()->json(['status' => 500, 'response' => 'Internal Server Error', 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    
    public function complete(Request $request)
{
    // Start a database transaction
    DB::beginTransaction();

    try {
        $gig = Gig::find($request->gig_id);
        if (!$gig) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Gig not found!'], 404);
        }

        // Find the schedule using the correct method
        $schedule = Schedule::where('gig_id', $request->gig_id)->first();
        if (!$schedule) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Schedule not found!'], 404);
        }

        $time_sheet = TimeSheet::where('gig_id', $request->gig_id)->first();

        // Update the gig and timesheet status if they exist
        $gig->update(['status' => 'completed']);
        
        if ($time_sheet) {
            $time_sheet->update(['status' => 'completed']);
        }

        // Log the activity
        ActivityLog::create([
            'action' => 'Completed A Gig',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' has confirmed a gig completion at ' . Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($gig),
            'subject_id' => $request->gig_id,
            'user_id' => auth()->id(),
        ]);

        // Commit the transaction
        DB::commit();

        return response()->json(['status' => 200, 'response' => 'Gig Completed', 'message' => 'Gig and schedule have been completed successfully']);
    } catch (\Exception $e) {
        // Rollback the transaction in case of an error
        DB::rollback();

        // Return an error response
        return response()->json(['status' => 500, 'response' => 'Internal Server Error', 'message' => 'An error occurred: ' . $e->getMessage()], 500);
    }
}


    private function generateUniqueId() {
        $randomString = bin2hex(random_bytes(4));
        $uniqueId = sprintf(
            '%s%s-%s-%s-%s%s%s',
            substr($randomString, 0, 8),
            substr($randomString, 8, 4),
            substr(bin2hex(random_bytes(2)), 0, 4),
            substr(bin2hex(random_bytes(2)), 0, 4),
            substr(bin2hex(random_bytes(2)), 0, 4),
            substr(bin2hex(random_bytes(2)), 0, 4),
            substr(bin2hex(random_bytes(6)), 0, 12)
        );
        return $uniqueId;
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
    
    public function paginate(Request $request)
    {
        $perPage = $request->input('per_page', 20);
            // Get the authenticated user's location_id
        $userLocationId = auth()->user()->location_id;

        // Fetch all gigs created by users with the same location_id
        $gigs = Gig::with('schedule')
            ->whereHas('creator', function ($query) use ($userLocationId) {
                $query->where('location_id', $userLocationId);
            })->orderBy('created_at', 'desc')->paginate($perPage);
        ActivityLog::create([
            'action' => 'View All Gigs',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all gigs at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($gigs),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Gigs fetched successfully","data"=>$gigs],200);
    }
}
