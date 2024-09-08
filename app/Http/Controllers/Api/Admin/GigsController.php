<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\Gig;
use App\Models\User;
use App\Models\GigType;
use App\Models\Schedule;
use App\Models\AssignGig;
use App\Models\TimeSheet;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class GigsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $gigs = Gig::with('schedule')->get();
        if ($gigs->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Gig(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View All Gigs',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all users at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($gigs),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Gigs fetched successfully","data"=>$gigs],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required','string'],
            'description' => ['required'],
            'client_id' => ['required', 'exists:clients,id'],
            'created_by' => ['required', 'exists:users,id'],
            'grace_period' => ['required','numeric','min:0', 'max:15'],
            'gig_type_id' => ['required','exists:gig_types,id'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
            'dsw_id' => ['nullable', 'exists:users,id'],
            'start_date' => ['required','date_format:m-d-Y'],
            'days' => ['required'],
            'schedule' => ['required','array','max:7'], // Ensure it's an array and does not exceed 7 items
            'schedule.*.day' => ['required','string'],
            'schedule.*.start_time' => ['required','date_format:h:i A'],
            'schedule.*.end_time' => ['required','date_format:h:i A'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
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

        // Validate supervisor_id if provided
        if ($request->filled('supervisor_id')) {
            $supervisor = User::find($request->supervisor_id);
            if ($supervisor) {
                // Check for 'supervisor' role
                if (!$supervisor->hasRole('Supervisor')) {
                    return response()->json(['message' => 'User is not a Supervisor.'], 403);
                }
            } else {
                return response()->json(['error' => 'Supervisor not found'], 404);
            }
        }

        $gig_type = GigType::find($request->gig_type_id);
        if (!$gig_type) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Gig Type does not exist'], 404);
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
            'supervisor_id' => $request->supervisor_id,
            'start_date' => $request->start_date
        ]);

        $days = json_encode($request->days);
        $schedule = json_encode($request->schedule);
        $schedule = Schedule::create([
            'gig_id' => $gig->id,
            'gig_unique_id' => $gig->gig_unique_id,
            'days' => $days,
            'schedule' => $schedule
        ]);

        // Validate dsw_id if provided
        if ($request->filled('dsw_id')) {
            $dsw = User::find($request->dsw_id);
            if ($dsw) {
                // Additional code if dsw_id is provided
                // Run the specific code that you want to execute when dsw_id is not null
                $this->assignDSWToGig($gig,$dsw);
            } else {
                return response()->json(['error' => 'DSW not found'], 404);
            }
        }
        
        ActivityLog::create([
            'action' => 'Created New gig',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new gig at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $gig->id,
            'subject_type' => get_class($gig),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>201,'response'=>'Created Gig','message'=>'Gig created successfully','data'=>["gig"=>$gig, "schedule"=>$schedule]], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $gig = Gig::where('id', $request->id)->with('schedule')->first();
        if (!$gig) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Gig does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View A Gig Details',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a gig details at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $gig->id,
            'subject_type' => get_class($gig),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful','message'=>'Gig successfully fetched', 'data'=>$gig], 200);
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
            'created_by' => ['required', 'exists:users,id'],
            'grace_period' => ['required','numeric','min:0', 'max:15'],
            'gig_type_id' => ['required','exists:gig_types,id'],
            'start_date' => ['required','date_format:m-d-Y'],
            'supervisor_id' => ['required', 'exists:users,id'],
            'days' => ['required'],
            'schedule' => ['required','array','max:7'], // Ensure it's an array and does not exceed 7 items
            'schedule.*.day' => ['required','string'],
            'schedule.*.start_time' => ['required','date_format:H:i A'],
            'schedule.*.end_time' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Entity', 'errors' => $validator->errors()->all()], 422);
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
        $user = User::find($request->created_by);
        $supervisor = User::find($request->supervisor_id);

        // Check if the user exists to avoid null object errors
        if ($user) {
            // Check for 'manager' or 'supervisor' roles
            if (!$user->hasRole('Admin') && !$user->hasRole('Manager') && !$user->hasRole('Supervisor')) {
                return response()->json(['message' => 'User is not a Manager or supervisor.']);
            }
        } else {
            return response()->json(['error' => 'User not found'], 404);
        }

        if ($supervisor) {
            // Check for 'manager' or 'supervisor' roles
            if (!$supervisor->hasRole('Supervisor')) {
                return response()->json(['message' => 'User is not a Supervisor.']);
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

        $gig->update([
            'title' => $request->title, 
            'description' => $request->description, 
            'client_id' => $request->client_id, 
            'created_by' => $request->created_by,
            'gig_type_id' => $gig_type->id,
            'gig_type' => $gig_type->title,
            'gig_type_shortcode' => $gig_type->shortcode,
            'supervisor_id' => $request->supervisor_id,
            'start_date' => $request->start_date
        ]);

        $schedule = Schedule::where('gig_id', $request->id)->first();
        if (!$schedule) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Schedule not found'], 404);
        }

        $days = json_encode($request->days);
        $schedule_update = json_encode($request->schedule);

        $schedule->update([
            'gig_id' => $request->id,
            'gig_unique_id' => $gig->gig_unique_id,
            'days' => $days,
            'schedule' => $schedule_update,
            'grace_period' => $request->grace_period
        ]);

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
            $gig = Gig::find($request->id);
            if (!$gig) {
                return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Gig not found!'], 404);
            }

            // Find the schedule using the correct method
            $schedule = Schedule::where('gig_id', $request->id)->first();
            if (!$schedule) {
                return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Schedule not found!'], 404);
            }

            // Delete schedule first to handle foreign key constraints if any
            $schedule->delete();
            // Then delete the gig
            $gig->delete();

            // Log the activity
            ActivityLog::create([
                'action' => 'Deleted A Gig',
                'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' deleted a gig at ' . Carbon::now()->format('h:i:s A'),
                'subject_type' => get_class($gig),
                'subject_id' => $request->id,
                'user_id' => auth()->id(),
            ]);

            // Commit the transaction
            DB::commit();

            return response()->json(['status' => 204, 'response' => 'No Content', 'message' => 'Gig and schedule deleted successfully']);
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
    
    // Function to handle additional code when dsw_id is not null
    protected function assignDSWToGig($gig,$dsw)
    {
        $gig_id = $gig->id;
        $user_id =$dsw->id;
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

        $existingAssignments = AssignGig::where('user_id', $user_id)->with('schedule')->get();
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
            'gig_id' => $gig_id,
            'user_id' => $user_id,
            'schedule_id' => $schedule_id,
        ]);

        $assigned_gig = AssignGig::where('id', $assign_gig->id)->with(['assignee' => function ($query) {
            $query->select('id', 'first_name', 'last_name', 'other_name', 'email', 'phone_number', 'location_id', 'gender', 'id_card', 'address1', 'address2', 'city', 'zip_code', 'dob', 'employee_id', 'points', 'email_verified_at', 'is_temporary_password', 'status', 'created_at', 'updated_at', 'deleted_at');
        }, 'gig.client', 'schedule'])->first();

        if (!$assigned_gig) {
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

        // return response()->json(['status' => 201, 'response' => 'Assigned Gig', 'message' => 'Gig assigned successfully', 'data' => [$assigned_gig]], 201);
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
