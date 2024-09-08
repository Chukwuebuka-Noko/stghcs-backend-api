<?php

namespace App\Http\Controllers\Api\User;

use Carbon\Carbon;
use App\Mail\ClockIn;
use App\Models\WeekLog;
use App\Models\Schedule;
use App\Models\ProgressReport;
use App\Models\TimeSheet;
use App\Models\ActivityLog;
use App\Models\RewardPoint;
use Illuminate\Http\Request;
use App\Models\RewardPointLog;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\{User, AssignGig, Gig, Client};

class TimeSheetController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:DSW|CSP']);
    }
    
    public function clock_in(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_id' => ['required', 'exists:gigs,id'],
            'latitude' => ['required'],
            'longitude' => ['required']
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json([
                'status' => 422,
                'response' => 'Unprocessable Content',
                'message' => $errors
            ], 422);
            // $errors = $validator->errors()->all();
            // return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
        }

        $user = Auth::user();
        $userCoordinates = $request->only(['latitude', 'longitude']);
        $now = Carbon::now();

        $assignGig = AssignGig::with(['gig.client', 'schedule', 'assignee'])
            ->where(['user_id' => $user->id, 'gig_id' => $request->gig_id])
            ->first();

        if (!$assignGig) {
            return response()->json([
                'status' => 404,
                'response' => 'Gig Not Assigned',
                'message' => 'Gig not assigned to this user'
            ], 404);
        }

        // Check for active clock-in across all timesheets
        $userId = auth('api')->user()->id;

        // Fetch all timesheets for the user
        $timesheets = TimeSheet::where('user_id', $userId)->get();

        // Variable to track if there's an active clock-in
        $activeClockIn = false;

        foreach ($timesheets as $timesheet) {
            // Check if activities exist
            if ($timesheet->activities) {
                $activities = json_decode($timesheet->activities, true);

                // Check for clock_in without clock_out within activities
                if (isset($activities['clock_in']) && !isset($activities['clock_out'])) {
                    $activeClockIn = true;
                    break;
                }
            }
        }

        if ($activeClockIn) {
            return response()->json([
                'status' => 409,
                'response' => 'Conflict Request',
                'message' => 'Existing clock-in found without clock-out. Please clock out before clocking in again.',
                'state' => 'Clock'
            ], 409);
        }

        $data = json_decode($assignGig->gig->client->coordinate, true);
        $clientCoordinates = [
            'latitude' => (float) $data['lat'],
            'longitude' => (float) $data['long']
        ];

        $timeSheet = TimeSheet::firstOrCreate([
            'user_id' => $user->id,
            'gig_id' => $assignGig->gig->id
        ]);

        $startDate = Carbon::createFromFormat('m-d-Y', $assignGig->gig->start_date);
        $currentDate = Carbon::now()->format('m-d-Y');

        if ($startDate->gt(Carbon::createFromFormat('m-d-Y', $currentDate))) {
            return response()->json([
                'status' => 409,
                'response' => 'Conflict Request',
                'message' => 'This gig is meant to start on ' . $assignGig->gig->start_date
            ], 409);
        }

        $details = json_decode($timeSheet->activities, true) ?? [];

        foreach ($details as $entry) {
            if ($entry['clock_out'] === null) {
                return response()->json([
                    'status' => 409,
                    'response' => 'Conflict Request',
                    'message' => 'Existing clock-in found without clock-out. Please clock out before clocking in again.'
                ], 409);
            }
        }

        $schedule = Schedule::find($assignGig->schedule->id);
        $scheduled_date = $schedule->schedule;
        $schedule_time = $this->getCurrentDaySchedule($scheduled_date);
        // Check if schedule was not found
        if ($schedule_time['status'] === 404) {
            return response()->json([
                'status' => 404,
                'response' => $schedule_time['response'],
                'message' => $schedule_time['message']
            ], 404);
        }
        $scheduleStartTime = Carbon::createFromFormat('h:i A', $schedule_time['start_time']);
        $scheduleStartTimePlus15 = (clone $scheduleStartTime)->addMinutes($assignGig->gig->grace_period);

        $isWithinCoordinates = $this->checkProximity($userCoordinates, $clientCoordinates, 164.042);
        $isWithinGracePeriod = $now->between($scheduleStartTime, $scheduleStartTimePlus15);
        $isLate = $now->gt($scheduleStartTimePlus15);
        $isEarly = $now->lt($scheduleStartTime);

        if (!$isWithinCoordinates && $isLate) {
            return response()->json([
                'status' => 403,
                'response' => 'Mismatch Flag & Lateness Flag',
                'message' => 'You are not within the required range of the client location and also you are late for your shift'
            ], 403);
        }

        if (!$isWithinCoordinates && !$isLate) {
            return response()->json([
                'status' => 403,
                'response' => 'Mismatch Flag',
                'message' => 'You are not within the required range of the client location'
            ], 403);
        }

        if ($isLate) {
            return response()->json([
                'status' => 403,
                'response' => 'Lateness Flag',
                'message' => 'You are late for your shift'
            ], 403);
        }

        $activity_id = $this->generateUniqueAlphanumeric();

        $entry = [
            'activity_id' => $activity_id,
            'clock_in' => Carbon::now()->toIso8601String(),
            'clock_in_coordinate' => json_encode($userCoordinates),
            'clock_out' => null,
            'clock_out_coordinate' => null
        ];

        if ($now->equalTo($scheduleStartTime)) {
            $status = 'On Time';
        } elseif ($now->lessThan($scheduleStartTime)) {
            $status = 'Came Before Time';
        } elseif ($now->between($scheduleStartTime, $scheduleStartTimePlus15)) {
            $status = 'Came Within Grace Period';
        } else {
            $status = 'Came Late';
            $validator = Validator::make($request->all(), [
                'report' => ['required'],
            ]);
            if ($validator->fails()) {
                
                return response()->json([
                    'status' => 422,
                    'response' => 'Clock-in Lateness',
                    'message' => 'Give Reason why you are late',
                    'errors' => $validator->errors()->all()
                ], 422);
            }
            $entry['clock_in_report'] = $request->report;
        }

        $entry['clock_in_status'] = $status;
        $details[] = $entry;
        $timeSheet->activities = json_encode($details);
        $timeSheet->save();

        WeekLog::create([
            'title' => $user->last_name . " " . $user->first_name . " clocked in",
            'week_number' => $now->weekOfYear,
            'year' => $now->year,
            'day' => $now->format('l'),
            'time' => $now->format('h:i A'),
            'type' => "Clock In",
            'timesheet_id' => $timeSheet->unique_id,
            'activity_id' => $activity_id
        ]);

        ActivityLog::create([
            'action' => 'Clock In',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' clocked in at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $timeSheet->id,
            'subject_type' => get_class($timeSheet),
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => "Clock-in recorded: $status",
            'clock_in_time' => $now->toDateTimeString(),
            'scheduled_start_time' => $scheduleStartTime->toDateTimeString(),
            'status' => $status
        ]);
    }
    //flaged Clock-in
    public function flag_clock_in(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_id' => ['required', 'exists:gigs,id'],
            'latitude' => ['required'],
            'longitude' => ['required']
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
        }
        $user = Auth::user();
        $userCoordinates = $request->only(['latitude', 'longitude']);

        // Current date and time
        $now = Carbon::now();

        // Example: Retrieve assign_gig based on user, this can vary based on your application logic
        $assignGig = AssignGig::with(['gig.client', 'schedule', 'assignee'])->where(['user_id' => $user->id, 'gig_id' => $request->gig_id])->first();

        if (!$assignGig) {
            return response()->json([
                'status' => 404,
                'response' => 'Gig Not Assigned',
                'message' => 'Gig not assigned to this user'
            ], 404);
        }

        // Check for active clock-in across all timesheets
        $userId = auth('api')->user()->id;

        // Fetch all timesheets for the user
        $timesheets = TimeSheet::where('user_id', $userId)->get();

        // Variable to track if there's an active clock-in
        $activeClockIn = false;

        foreach ($timesheets as $timesheet) {
            // Check if activities exist
            if ($timesheet->activities) {
                $activities = json_decode($timesheet->activities, true);

                // Check for clock_in without clock_out within activities
                if (isset($activities['clock_in']) && !isset($activities['clock_out'])) {
                    $activeClockIn = true;
                    break;
                }
            }
        }

        if ($activeClockIn) {
            return response()->json([
                'status' => 409,
                'response' => 'Conflict Request',
                'message' => 'Existing clock-in found without clock-out. Please clock out before clocking in again.'
            ], 409);
        }

        $data = json_decode($assignGig->gig->client->coordinate, true);

        $clientCoordinates = [
            'latitude' => (float) $data['lat'],
            'longitude' => (float) $data['long']
        ];

        // Retrieve the latest timesheet or create a new one if none exist
        $timeSheet = TimeSheet::firstOrCreate([
            'user_id' => $user->id,
            'gig_id' => $assignGig->gig->id
        ]);
        
        $startDate = Carbon::createFromFormat('m-d-Y', $assignGig->gig->start_date);
        $currentDate = Carbon::now()->format('m-d-Y');
        
        if ($startDate->gt(Carbon::createFromFormat('m-d-Y', $currentDate))) {
            return response()->json([
                'status' => 409,
                'response' => 'Conflict Request',
                'message' => 'This gig is meant to start on ' . $assignGig->gig->start_date
            ], 409);
        }

        // Decode the existing details, add the new entry, and re-encode it
        $details = json_decode($timeSheet->activities, true) ?? [];
    
        // Check for an existing clock-in without a clock-out
        foreach ($details as $entry) {
            if ($entry['clock_out'] === null) {
                // If found, return a response to restrict another clock-in
                return response()->json([
                    'message' => 'Existing clock-in found without clock-out. Please clock out before clocking in again.',
                    'status' => 409,
                    'response' => 'Conflict Request'
                ], 409);
            }
        }

        $schedule = Schedule::find($assignGig->schedule->id);
        $scheduled_date = $schedule->schedule;
        $schedule_time = $this->getCurrentDaySchedule($scheduled_date);
        // Check if schedule was not found
        if ($schedule_time['status'] === 404) {
            return response()->json([
                'status' => 404,
                'response' => $schedule_time['response'],
                'message' => $schedule_time['message']
            ], 404);
        }
        // Check scheduled time
        $scheduleStartTime = Carbon::createFromFormat('h:i A', $schedule_time['start_time']);

        // Time 15 minutes after the scheduled start time
        $scheduleStartTimePlus15 = (clone $scheduleStartTime)->addMinutes($assignGig->gig->grace_period);
        $activity_id = $this->generateUniqueAlphanumeric();

        // Create the entry details
        $entry = [
            'activity_id' => $activity_id,
            'clock_in' => Carbon::now()->toIso8601String(),
            'clock_in_coordinate' => json_encode($userCoordinates),
            'clock_out' => null,
            'clock_out_coordinate' => null,
            'flags' => []
        ];

        if (!$this->checkProximity($userCoordinates, $clientCoordinates, 164.042)) {
            $validator = Validator::make($request->all(), [
                'coordinate_remark' => ['required'],
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all(), 'message' => 'Give Reason why you are not clocking in from the required coordinate'], 422);
            }
            $entry['flags'][] = [
                'title' => 'Clock-in coordinate mismatch',
                'description' => 'Clocked In from a different coordinate from the client location',
                'remark' => $request->remark,
            ];
        }

        // Compare times and log accordingly
        if ($now->equalTo($scheduleStartTime)) {
            $status = 'On Time';
        } elseif ($now->lessThan($scheduleStartTime)) {
            $status = 'Came Before Time';
        } elseif ($now->between($scheduleStartTime, $scheduleStartTimePlus15)) {
            $status = 'Came Within Grace Period';
        } else {
            $status = 'Came Late';
            $validator = Validator::make($request->all(), [
                'lateness_remark' => ['required'],
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all(), 'message' => 'Give Reason why you are late'], 422);
            }
            $entry['clock_in_report'] = $request->report;
            $entry['flags'][] = [
                'title' => 'Came late',
                'description' => 'Came late for your gig',
                'remark' => $request->input('report', 'No specific reason provided'),
            ];
        }

        $entry['clock_in_status'] = $status;

        $details[] = $entry;
        $timeSheet->activities = json_encode($details);

        $timeSheet->save();
        // Mail::to($user->email)->send(new ClockIn($time_sheet, $user, $assignGig));
        WeekLog::create([
            'title' => $user->last_name . " " . $user->first_name . " clocked in",
            'week_number' => $now->weekOfYear,
            'year' => $now->year,
            'day' => $now->format('l'),
            'time' => $now->format('h:i A'),
            'type' => "Clock In",
            'timesheet_id' => $timeSheet->unique_id,
            'activity_id' => $activity_id
        ]);
        ActivityLog::create([
            'action' => 'Clock In',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' clocked in at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $timeSheet->id,
            'subject_type' => get_class($timeSheet),
            'user_id' => auth()->id(),
        ]);
        return response()->json([
            'message' => "Clock-in recorded: $status",
            'clock_in_time' => $now->toDateTimeString(),
            'scheduled_start_time' => $scheduleStartTime->toDateTimeString(),
            'status' => $status
        ]);
    }
    public function emergency_clock_in(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_id' => ['required', 'exists:gigs,id'],
            'latitude' => ['required'],
            'longitude' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }
        $user = Auth::user();
        $userCoordinates = $request->only(['latitude', 'longitude']);

        // Current date and time
        $now = Carbon::now();

        // Example: Retrieve assign_gig based on user, this can vary based on your application logic
        $assignGig = AssignGig::with(['gig.client', 'schedule', 'assignee'])->where(['user_id' => $user->id, 'gig_id' => $request->gig_id])->first();

        if (!$assignGig) {
            return response()->json(['message' => 'Gig not assigned to this user'], 404);
        }

        // Check for active clock-in across all timesheets
        $userId = auth('api')->user()->id;

        // Fetch all timesheets for the user
        $timesheets = TimeSheet::where('user_id', $userId)->get();

        // Variable to track if there's an active clock-in
        $activeClockIn = false;

        foreach ($timesheets as $timesheet) {
            // Check if activities exist
            if ($timesheet->activities) {
                $activities = json_decode($timesheet->activities, true);

                // Check for clock_in without clock_out within activities
                if (isset($activities['clock_in']) && !isset($activities['clock_out'])) {
                    $activeClockIn = true;
                    break;
                }
            }
        }

        if ($activeClockIn) {
            return response()->json([
                'status' => 409,
                'response' => 'Conflict Request',
                'message' => 'Existing clock-in found without clock-out. Please clock out before clocking in again.'
            ], 409);
        }

        $data = json_decode($assignGig->gig->client->coordinate, true);

        $clientCoordinates = [
            'latitude' => (float) $data['lat'],
            'longitude' => (float) $data['long']
        ];

        // if (!$this->checkProximity($userCoordinates, $clientCoordinates, 164.042)) {
        //     return response()->json(['message' => 'You are not within the required range of the client location'], 403);
        // }

        // Retrieve the latest timesheet or create a new one if none exist
        $timeSheet = TimeSheet::firstOrCreate([
            'user_id' => $user->id,
            'gig_id' => $assignGig->gig->id
        ]);
        
        $startDate = Carbon::createFromFormat('m-d-Y', $assignGig->gig->start_date);
        $currentDate = Carbon::now()->format('m-d-Y');
        
        if ($startDate->gt(Carbon::createFromFormat('m-d-Y', $currentDate))) {
            return response()->json([
                'status' => 409,
                'response' => 'Conflict Request',
                'message' => 'This gig is meant to start on ' . $assignGig->gig->start_date
            ], 409);
        }
        // Decode the existing details, add the new entry, and re-encode it
        $details = json_decode($timeSheet->activities, true) ?? [];
        // Check for an existing clock-in without a clock-out
        foreach ($details as $entry) {
            if ($entry['clock_out'] === null) {
                return response()->json([
                    'status' => 409,
                    'response' => 'Conflict Request',
                    'message' => 'Existing clock-in found without clock-out. Please clock out before clocking in again.'
                ], 409);
            }
        }
        $activity_id = $this->generateUniqueAlphanumeric();

        // Create the entry details
        $entry = [
            'activity_id' => $activity_id,
            'clock_in' => Carbon::now()->toIso8601String(),
            'clock_in_coordinate' => json_encode($userCoordinates),
            'clock_in_status' => 'On Time',
            'emergency_clock_in' => true,
            'clock_out' => null,
            'clock_out_coordinate' => null
        ];

        $details[] = $entry;
        $timeSheet->activities = json_encode($details);

        $timeSheet->save();
        // Mail::to($user->email)->send(new ClockIn($time_sheet, $user, $assignGig));
        WeekLog::create([
            'title' => $user->last_name . " " . $user->first_name . " clocked in as an emergency gig",
            'week_number' => $now->weekOfYear,
            'year' => $now->year,
            'day' => $now->format('l'),
            'time' => $now->format('h:i A'),
            'type' => "Clock In",
            'timesheet_id' => $timeSheet->unique_id,
            'activity_id' => $activity_id
        ]);
        ActivityLog::create([
            'action' => 'Clock In',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' clocked in at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $timeSheet->id,
            'subject_type' => get_class($timeSheet),
            'user_id' => auth()->id(),
        ]);
        return response()->json([
            'message' => "Clock-in recorded: On Time",
            'clock_in_time' => $now->toDateTimeString(),
            'scheduled_start_time' => $now->toDateTimeString(),
            'status' => 'On Time'
        ]);
    }
    public function clock_out(Request $request)
{
    $validator = Validator::make($request->all(), [
        'gig_id' => ['required', 'exists:gigs,id'],
        'latitude' => ['required'],
        'longitude' => ['required']
    ]);

    if ($validator->fails()) {
        return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
    }

    $user = User::find(auth('api')->user()->id);
    $userCoordinates = $request->only(['latitude', 'longitude']);

    // Retrieve assign_gig
    $assignGig = AssignGig::with(['gig.client', 'schedule', 'assignee'])
        ->where(['user_id' => $user->id, 'gig_id' => $request->gig_id])
        ->first();

    if (!$assignGig) {
        return response()->json(['message' => 'Gig not assigned to this user'], 404);
    }

    // Retrieve the latest timesheet
    $timeSheet = TimeSheet::where('user_id', $user->id)->where('gig_id', $request->gig_id)->latest()->firstOrFail();
    // Decode the details
    $details = json_decode($timeSheet->activities, true);
    // Assuming we're updating the last entry
    $lastIndex = count($details) - 1;

    $clientData = json_decode($assignGig->gig->client->coordinate, true);
    $clientCoordinates = [
        'latitude' => (float) $clientData['lat'],
        'longitude' => (float) $clientData['long']
    ];

    $schedule = $assignGig->schedule;
    $scheduled_date = $schedule->schedule; // verify this is correct. Seems it should just be $schedule->date or similar
    $schedule_time = $this->getCurrentDaySchedule($scheduled_date);
    // Check if schedule was not found
    if ($schedule_time['status'] === 404) {
        /*return response()->json([
            'status' => 404,
            'response' => $schedule_time['response'],
            'message' => $schedule_time['message']
        ], 404);*/
        $validator = Validator::make($request->all(), [
            'lateness_remark' => ['required'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Late Clock Out Flag', 'message' => 'Give Reason(s) why you are clocking out after your scheduled end time'], 422);
        }
        $remark = $request->input('lateness_remark');
        return $this->end_clock_out($request, $remark);
    }
    $scheduleEndTime = Carbon::createFromFormat('h:i A', $schedule_time['end_time']);

    // Current date and time
    $now = Carbon::now();

    $isWithinProximity = $this->checkProximity($userCoordinates, $clientCoordinates, 164.042);
    $isClockOutOnTime = $now->equalTo($scheduleEndTime);
    $isClockOutEarly = $now->lessThan($scheduleEndTime);
    $isClockOutLate = $now->greaterThan($scheduleEndTime);

    // Check if user is clocking out within the required coordinates and on time
    if (!$isWithinProximity && $isClockOutEarly) {
        return response()->json([
            'status' => 403,
            'response' => 'Mismatch Flag & Early Flag',
            'message' => 'You are not within the required range of the client location and also you are clocking out early from your shift.'
        ], 403);
    }

    // Check if user is clocking out on time but not within the required coordinates
    if (!$isWithinProximity && $isClockOutOnTime) {
        return response()->json([
            'status' => 403,
            'response' => 'Mismatch Flag',
            'message' => 'You are not within the required range of the client location'
        ], 403);
    }

    // Check if user is clocking out early
    if ($isClockOutEarly) {
        return response()->json([
            'status' => 403,
            'response' => 'Early Flag',
            'message' => 'You are clocking out early from your shift.'
        ], 403);
    }

    // If user is clocking out on time and within the required coordinates
    if ($isClockOutOnTime) {
        $status = 'On Time';
    } elseif ($isClockOutLate) {
        $status = 'Over Time';
    } else {
        $status = 'Left Before Time';
    }

    // Check if the clock out is way past 60 minutes after the scheduled end time
    if ($now->greaterThan($scheduleEndTime->addMinutes(60))) {
        // End activity and allow next clock in
        $validator = Validator::make($request->all(), [
            'lateness_remark' => ['required'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Late Clock Out Flag', 'message' => 'Give Reason(s) why you are clocking out after your scheduled end time'], 422);
        }
        $remark = $request->input('lateness_remark');
        return $this->end_clock_out($request, $remark);
        /*$validator = Validator::make($request->all(), [
            'report' => ['required'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Late Clock Out Flag', 'message' => 'Give Reason(s) why you are clocking out after your scheduled end time'], 422);
        }
        $clockInTime = new Carbon($details[$lastIndex]['clock_in']);
        $clockOutTime = Carbon::now();
        // Calculate duration
        $duration = $clockInTime->diff($clockOutTime);
        // Format duration string
        $durationString = $duration->format('%H hours, %I minutes');
        $details[$lastIndex]['clock_out'] = Carbon::now()->toIso8601String();
        $details[$lastIndex]['clock_out_coordinate'] = json_encode($userCoordinates);
        $details[$lastIndex]['duration'] = $durationString;
        $details[$lastIndex]['clock_out_report'] = $request->report;
        $details[$lastIndex]['clock_out_status'] = "Ended Activity";
        $timeSheet->activities = json_encode($details);
        $timeSheet->save();

        return response()->json([
            'message' => 'Clock-out attempt was too late. Activity ended, please clock in for your next schedule.',
            'clock_out_time' => $now->toDateTimeString(),
            'scheduled_end_time' => $scheduleEndTime->toDateTimeString(),
            'status' => 'ended',
            'details' => $details[$lastIndex]
        ]);
        
        return response()->json([
            'message' => "Clock-out recorded: $status",
            'clock_out_time' => $now->toDateTimeString(),
            'scheduled_end_time' => $scheduleEndTime->toDateTimeString(),
            'duration' => $durationString,
            'status' => $status
        ]);*/
    }else{

        $clockInTime = new Carbon($details[$lastIndex]['clock_in']);
        $clockOutTime = Carbon::now();
        // Calculate duration
        $duration = $clockInTime->diff($clockOutTime);
        // Format duration string
        $durationString = $duration->format('%H hours, %I minutes');

        if ($details[$lastIndex]['clock_out'] === null) {
            $details[$lastIndex]['clock_out'] = Carbon::now()->toIso8601String();
            $details[$lastIndex]['clock_out_coordinate'] = json_encode($userCoordinates);
            $details[$lastIndex]['clock_out_status'] = $status;
            $details[$lastIndex]['duration'] = $durationString;
            $timeSheet->activities = json_encode($details);
            $timeSheet->save();
        }
    }

    // Handle the details and rewards
    if ($status == "Over Time") {
        $reward = RewardPoint::where(['name' => 'Shift Completion'])->first();
        if ($reward) {
            $point = $reward->points / 2;
            $current_point = $user->point;
            $new_point = $point + $current_point;
            $user->update([
                'points' => $new_point
            ]);
            RewardPointLog::create([
                'title' => 'Time Sheet completion by ' . $user->last_name . ' ' . $user->first_name,
                'user_id' => $user->id,
                'points' => $point
            ]);
        }
    } else if ($status == "On Time") {
        // Update status based on report fields being null
        if (is_null($timeSheet->clock_in_report) && is_null($timeSheet->clock_out_report)) {
            $reward = RewardPoint::where(['name' => 'Shift Completion'])->first();
            if ($reward) {
                $point = $reward->points;
                $current_point = $user->point;
                $new_point = $point + $current_point;
                $user->update([
                    'points' => $new_point
                ]);
                RewardPointLog::create([
                    'title' => 'Time Sheet completion by ' . $user->last_name . ' ' . $user->first_name,
                    'user_id' => $user->id,
                    'points' => $point
                ]);
            }
        }
    } else if ($status = 'Left Before Time') {
        $validator = Validator::make($request->all(), [
            'report' => ['required'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all(), 'message' => 'Give Reason why you are leaving early'], 422);
        }
        $details[$lastIndex]['clock_out_report'] = $request->report;
    }

    
    WeekLog::create([
        'title' => $user->last_name . " " . $user->first_name . " clocked Out",
        'week_number' => $clockInTime->weekOfYear,
        'activity_id' => $details[$lastIndex]['activity_id'],
        'year' => $now->year,
        'day' => $now->format('l'),
        'time' => $now->format('h:i A'),
        'type' => "Clock Out",
        'timesheet_id' => $timeSheet->unique_id
    ]);
    ActivityLog::create([
        'action' => 'Clock Out',
        'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' clocked out at ' . $now->format('h:i:s A'),
        'subject_id' => $timeSheet->id,
        'subject_type' => get_class($timeSheet),
        'user_id' => auth()->id(),
    ]);

    return response()->json([
        'message' => "Clock-out recorded: $status",
        'clock_out_time' => $now->toDateTimeString(),
        'scheduled_end_time' => $scheduleEndTime->toDateTimeString(),
        'duration' => $durationString,
        'status' => $status
    ]);
}

    public function flag_clock_out(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_id' => ['required', 'exists:gigs,id'],
            'latitude' => ['required'],
            'longitude' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }

        $user = User::find(auth('api')->user()->id);
        $userCoordinates = $request->only(['latitude', 'longitude']);

        // Retrieve assign_gig
        $assignGig = AssignGig::with(['gig.client', 'schedule', 'assignee'])
            ->where(['user_id' => $user->id, 'gig_id' => $request->gig_id])
            ->first();

        if (!$assignGig) {
            return response()->json(['message' => 'Gig not assigned to this user'], 404);
        }
        

        // Retrieve the latest timesheet
        $timeSheet = TimeSheet::where('user_id', $user->id)->where('gig_id', $request->gig_id)->latest()->firstOrFail();
        // Decode the details
        $details = json_decode($timeSheet->activities, true);
        // Assuming we're updating the last entry
        $lastIndex = count($details) - 1;

        $clientData = json_decode($assignGig->gig->client->coordinate, true);
        $clientCoordinates = [
            'latitude' => (float) $clientData['lat'],
            'longitude' => (float) $clientData['long']
        ];

        $schedule = $assignGig->schedule;
        $scheduled_date = $schedule->schedule; // verify this is correct. Seems it should just be $schedule->date or similar
        $schedule_time = $this->getCurrentDaySchedule($scheduled_date);
        // Check if schedule was not found
        if ($schedule_time['status'] === 404) {
            /*return response()->json([
                'status' => 404,
                'response' => $schedule_time['response'],
                'message' => $schedule_time['message']
            ], 404);*/
            $remark = $request->input('lateness_remark');
            return $this->end_clock_out($request, $remark);
        }
        $scheduleEndTime = Carbon::createFromFormat('h:i A', $schedule_time['end_time']);

        // Current date and time
        $now = Carbon::now();

        // Check if the clock out is way past 15 minutes after the scheduled end time
        if ($now->greaterThan($scheduleEndTime->addMinutes(60))) {
            // End activity and allow next clock in
            $validator = Validator::make($request->all(), [
                'lateness_remark' => ['required'],
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => 422, 'response' => 'Late Clock Out Flag', 'message' => 'Give Reason(s) why you are clocking out after your scheduled end time'], 422);
            }
            
            $details[$lastIndex]['clock_out_report'] = "This Activity was ended because of late clock out";
            $details[$lastIndex]['clock_out_status'] = "Ended Activity";
            $details[$lastIndex]['flags'][] = [
                'title' => 'Ended Activity',
                'description' => 'Activity was ended because of late clock out',
                'remark' => $request->input('lateness_remark'),
            ];
            $clockInTime = new Carbon($details[$lastIndex]['clock_in']);
            $clockOutTime = Carbon::now();

            // Calculate duration
            $duration = $clockInTime->diff($clockOutTime);
            // Format duration string
            $durationString = $duration->format('%H hours, %I minutes');
            
            $details[$lastIndex]['clock_out'] = Carbon::now()->toIso8601String();
            $details[$lastIndex]['clock_out_coordinate'] = json_encode($userCoordinates);
            $details[$lastIndex]['duration'] = $durationString;
            $timeSheet->activities = json_encode($details);
            $timeSheet->save();
            
            WeekLog::create([
                'title' => $user->last_name . " " . $user->first_name . " clocked Out",
                'week_number' => $clockInTime->weekOfYear,
                'activity_id' => $details[$lastIndex]['activity_id'],
                'year' => $now->year,
                'day' => $now->format('l'),
                'time' => $now->format('h:i A'),
                'type' => "Clock Out",
                'timesheet_id' => $timeSheet->unique_id
            ]);

            return response()->json([
                'message' => 'Clock-out attempt was too late. Activity ended, please clock in for your next schedule.',
                'clock_out_time' => $now->toDateTimeString(),
                'scheduled_end_time' => $scheduleEndTime->toDateTimeString(),
                'duration' => $durationString,
                'status' => 'Ended Activity',
                'details' => $details[$lastIndex]
            ]);
        }else{
            // Determine status based on time comparison
            if ($now->equalTo($scheduleEndTime)) {
                $status = 'On Time';
            } elseif ($now->lessThan($scheduleEndTime)) {
                $status = 'Left Before Time';
                $validator = Validator::make($request->all(), [
                    'lateness_remark' => ['required'],
                ]);
                if ($validator->fails()) {
                    return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all(), 'message' => 'Give Reason why you are leaving early'], 422);
                }
                $details[$lastIndex]['clock_out_report'] = $request->report;
                $details[$lastIndex]['flags'][] = [
                    'title' => 'Left Before Time',
                    'description' => 'Left before the end of the sheet',
                    'remark' => $request->input('lateness_remark'),
                ];
            } else {
                $status = 'Over Time';
            }
            
            if (!$this->checkProximity($userCoordinates, $clientCoordinates, 164.042)) {
                $validator = Validator::make($request->all(), [
                    'coordinate_remark' => ['required'],
                ]);
                if ($validator->fails()) {
                    return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all(), 'message' => 'Give Reason why you are not clocking out from the required coordinate'], 422);
                }
                $details[$lastIndex]['flags'][] = [
                    'title' => 'Clock-out coordinate mismatch',
                    'description' => 'Clocked Out from a different coordinate from the client location',
                    'remark' => $request->coordinate_remark,
                ];
            }

            if ($status == "Over Time") {
                $reward = RewardPoint::where(['name' => 'Shift Completion'])->first();
                if ($reward) {
                    $point = $reward->points / 2;
                    $current_point = $user->point;
                    $new_point = $point + $current_point;
                    $user->update([
                        'points' => $new_point
                    ]);
                    RewardPointLog::create([
                        'title' => 'Time Sheet completion by ' . $user->last_name . ' ' . $user->first_name,
                        'user_id' => $user->id,
                        'points' => $point
                    ]);
                }
            } else if ($status == "On Time") {
                // Update status based on report fields being null
                if (is_null($timeSheet->clock_in_report) && is_null($timeSheet->clock_out_report)) {
                    $reward = RewardPoint::where(['name' => 'Shift Completion'])->first();
                    if ($reward) {
                        $point = $reward->points;
                        $current_point = $user->point;
                        $new_point = $point + $current_point;
                        $user->update([
                            'points' => $new_point
                        ]);
                        RewardPointLog::create([
                            'title' => 'Time Sheet completion by ' . $user->last_name . ' ' . $user->first_name,
                            'user_id' => $user->id,
                            'points' => $point
                        ]);
                    }
                }
            }

            $clockInTime = new Carbon($details[$lastIndex]['clock_in']);
            $clockOutTime = Carbon::now();

            // Calculate duration
            $duration = $clockInTime->diff($clockOutTime);
            // Format duration string
            $durationString = $duration->format('%H hours, %I minutes');

            if ($details[$lastIndex]['clock_out'] === null) {
                $details[$lastIndex]['clock_out'] = Carbon::now()->toIso8601String();
                $details[$lastIndex]['clock_out_coordinate'] = json_encode($userCoordinates);
                $details[$lastIndex]['clock_out_status'] = $status;
                $details[$lastIndex]['duration'] = $durationString;
                $timeSheet->activities = json_encode($details);
                $timeSheet->save();
            }
        }
        WeekLog::create([
            'title' => $user->last_name . " " . $user->first_name . " clocked Out",
            'week_number' => $clockInTime->weekOfYear,
            'activity_id' => $details[$lastIndex]['activity_id'],
            'year' => $now->year,
            'day' => $now->format('l'),
            'time' => $now->format('h:i A'),
            'type' => "Clock Out",
            'timesheet_id' => $timeSheet->unique_id
        ]);
        ActivityLog::create([
            'action' => 'Clock Out',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' clocked out at ' . $now->format('h:i:s A'),
            'subject_id' => $timeSheet->id,
            'subject_type' => get_class($timeSheet),
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => "Clock-out recorded: $status",
            'clock_out_time' => $now->toDateTimeString(),
            'scheduled_end_time' => $scheduleEndTime->toDateTimeString(),
            'duration' => $durationString,
            'status' => $status
        ]);
    }
    public function emergency_clock_out(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_id' => ['required', 'exists:gigs,id'],
            'latitude' => ['required'],
            'longitude' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }

        $user = User::find(auth('api')->user()->id);
        $userCoordinates = $request->only(['latitude', 'longitude']);

        // Retrieve assign_gig
        $assignGig = AssignGig::with(['gig.client', 'schedule', 'assignee'])
            ->where(['user_id' => $user->id, 'gig_id' => $request->gig_id])
            ->first();

        if (!$assignGig) {
            return response()->json(['message' => 'Gig not assigned to this user'], 404);
        }

         // Retrieve the latest timesheet
         $timeSheet = TimeSheet::where('user_id', $user->id)->where('gig_id', $request->gig_id)->latest()->firstOrFail();
         // Decode the details
         $details = json_decode($timeSheet->activities, true);
         // Assuming we're updating the last entry
         $lastIndex = count($details) - 1;

        $clientData = json_decode($assignGig->gig->client->coordinate, true);
        $clientCoordinates = [
            'latitude' => (float) $clientData['lat'],
            'longitude' => (float) $clientData['long']
        ];

        // if (!$this->checkProximity($userCoordinates, $clientCoordinates, 164.042)) {
        //     return response()->json(['message' => 'You are not within the required range of the client location'], 403);
        // }

        // Current date and time
        $now = Carbon::now();

        $clockInTime = new Carbon($details[$lastIndex]['clock_in']);
        $clockOutTime = Carbon::now();

        // Calculate duration
        $duration = $clockInTime->diff($clockOutTime);
        // Format duration string
        $durationString = $duration->format('%H hours, %I minutes');

        if ($details[$lastIndex]['clock_out'] === null) {
            $details[$lastIndex]['clock_out'] = Carbon::now()->toIso8601String();
            $details[$lastIndex]['clock_out_coordinate'] = json_encode($userCoordinates);
            $details[$lastIndex]['clock_out_status'] = "On Time";
            $details[$lastIndex]['emergency_clock_out'] = true;
            $details[$lastIndex]['duration'] = $durationString;
            $timeSheet->activities = json_encode($details);
            $timeSheet->save();
        }

        $reward = RewardPoint::where(['name' => 'Shift Completion'])->first();
        if ($reward) {
            $point = $reward->points;
            $current_point = $user->point;
            $new_point = $point + $current_point;
            $user->update([
                'points' => $new_point
            ]);
            RewardPointLog::create([
                'title' => 'Time Sheet completion by ' . $user->last_name . ' ' . $user->first_name,
                'user_id' => $user->id,
                'points' => $point
            ]);
        }

        WeekLog::create([
            'title' => $user->last_name . " " . $user->first_name . " clocked out as an emergency gig",
            'week_number' => $clockInTime->weekOfYear,
            'activity_id' => $details[$lastIndex]['activity_id'],
            'year' => $now->year,
            'day' => $now->format('l'),
            'time' => $now->format('h:i A'),
            'type' => "Clock Out",
            'timesheet_id' => $timeSheet->unique_id
        ]);

        ActivityLog::create([
            'action' => 'Clock Out',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' clocked out at ' . $now->format('h:i:s A'),
            'subject_id' => $timeSheet->id,
            'subject_type' => get_class($timeSheet),
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => "Clock-out recorded: On Time",
            'clock_out_time' => $now->toDateTimeString(),
            'scheduled_end_time' => $now->toDateTimeString(),
            'duration' => $durationString,
            'status' => 'On Time'
        ]);
    }
    
    protected function end_clock_out(Request $request, $remark)
{
    $validator = Validator::make($request->all(), [
        'gig_id' => ['required', 'exists:gigs,id'],
        'latitude' => ['required'],
        'longitude' => ['required'],
    ]);

    if ($validator->fails()) {
        return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
    }

    $user = User::find(auth('api')->user()->id);
    $userCoordinates = $request->only(['latitude', 'longitude']);

    $assignGig = AssignGig::with(['gig.client', 'schedule', 'assignee'])
        ->where(['user_id' => $user->id, 'gig_id' => $request->gig_id])
        ->first();

    if (!$assignGig) {
        return response()->json(['message' => 'Gig not assigned to this user'], 404);
    }

    $timeSheet = TimeSheet::where('user_id', $user->id)->where('gig_id', $request->gig_id)->latest()->firstOrFail();
    $details = json_decode($timeSheet->activities, true);
    $lastIndex = count($details) - 1;

    $clientData = json_decode($assignGig->gig->client->coordinate, true);
    $clientCoordinates = [
        'latitude' => (float) $clientData['lat'],
        'longitude' => (float) $clientData['long']
    ];

    $now = Carbon::now();

    $details[$lastIndex]['clock_out_report'] = "This Activity was ended because of late clock out";
    $details[$lastIndex]['clock_out_status'] = "Ended Activity";
    $details[$lastIndex]['flags'][] = [
        'title' => 'Ended Activity',
        'description' => 'Activity was ended because of late clock out',
        'remark' => $remark,
    ];

    $clockInTime = new Carbon($details[$lastIndex]['clock_in']);
    $clockOutTime = Carbon::now();
    $duration = $clockInTime->diff($clockOutTime);
    $durationString = $duration->format('%H hours, %I minutes');
    $details[$lastIndex]['clock_out'] = Carbon::now()->toIso8601String();
    $details[$lastIndex]['clock_out_coordinate'] = json_encode($userCoordinates);
    $details[$lastIndex]['duration'] = $durationString;
    $timeSheet->activities = json_encode($details);
    $timeSheet->save();

    WeekLog::create([
        'title' => $user->last_name . " " . $user->first_name . " clocked Out",
        'week_number' => $clockInTime->weekOfYear,
        'activity_id' => $details[$lastIndex]['activity_id'],
        'year' => $now->year,
        'day' => $now->format('l'),
        'time' => $now->format('h:i A'),
        'type' => "Clock Out",
        'timesheet_id' => $timeSheet->unique_id
    ]);

    return response()->json([
        'message' => 'Clock-out attempt was too late. Activity ended, please clock in for your next schedule.',
        'clock_out_time' => $now->toDateTimeString(),
        'duration' => $durationString,
        'status' => 'Ended Activity',
        'details' => $details[$lastIndex]
    ]);
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

    private function checkProximity($userCoords, $clientCoords, $distanceFeet)
    {
        // Convert latitude and longitude from degrees to radians
        $userLat = deg2rad($userCoords['latitude']);
        $userLong = deg2rad($userCoords['longitude']);
        $clientLat = deg2rad($clientCoords['latitude']);
        $clientLong = deg2rad($clientCoords['longitude']);

        // Compute the differences
        $theta = $userLong - $clientLong;
        $dist = sin($userLat) * sin($clientLat) + cos($userLat) * cos($clientLat) * cos($theta);
        
        // Correct for floating-point errors
        $dist = min(1.0, max(-1.0, $dist));
        
        // Convert to distance in miles
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $feet = $miles * 5280;

        // Logging for debugging
        // Log::info('User Coordinates:', $userCoords);
        // Log::info('Client Coordinates:', $clientCoords);
        // Log::info('Distance in feet:', $feet);

        return ($feet <= $distanceFeet);
    }


    private function getCurrentDaySchedule($schedule)
    {
        // Decode the JSON data into an associative array
        $schedules = json_decode($schedule, true);

        // Get current day name in lowercase
        $today = strtolower(Carbon::now()->format('l'));

        // Initialize variables to store the times
        $startTime = '';
        $endTime = '';

        // Loop through the array to find the current day's schedule
        foreach ($schedules as $schedule) {
            if ($schedule['day'] == $today) {
                $startTime = $schedule['start_time'];
                $endTime = $schedule['end_time'];
                break;
            }
        }

        // Check if we found the schedule for today
        if ($startTime == '' && $endTime == '') {
            return [
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No schedule found for this user today.'
            ];
        }

        // Return the start time and end time for the current day
        return ([
            'status' => 200,
            'start_time' => $startTime,
            'end_time' => $endTime
        ]);
    }

    private function getScheduleDay($schedule,$today)
    {
        // Decode the JSON data into an associative array
        $schedules = json_decode($schedule, true);

        // Get current day name in lowercase
        $today = strtolower($today);
        // Initialize variables to store the times
        $startTime = '';
        $endTime = '';

        // Loop through the array to find the current day's schedule
        foreach ($schedules as $schedule) {
            if ($schedule['day'] === $today) {
                $startTime = $schedule['start_time'];
                $endTime = $schedule['end_time'];
                break;
            }
        }

        // Return the start time and end time for the current day
        return ([
            'status' => 'success',
            'start_time' => $startTime,
            'end_time' => $endTime
        ]);
    }



public function weeklyLog(Request $request)
{
    // Fetch week logs for a specific timesheet including time sheet and incident reports data
    $weekLogs = WeekLog::with(['timeSheet.user.roles', 'incidentReports'])
        ->whereHas('timeSheet', function ($query) use ($request) {
            $query->where('unique_id', $request->timesheet_id);
        })
        ->orderBy('week_number', 'desc')
        ->get();

    // Check if there is at least one log and it has an associated timesheet
    $firstWeekLog = $weekLogs->first();
    if (is_null($firstWeekLog) || is_null($firstWeekLog->timeSheet)) {
        $timeSheet = $firstWeekLog ? $firstWeekLog->timeSheet : TimeSheet::where('unique_id', $request->timesheet_id)->with('user.roles')->first();
        return response()->json([
            'status' => 200,
            'response' => 'Week Log not found',
            'message' => 'Weekly activity log',
            'state' => 'Clock in',
            'employee_name' => $timeSheet->user->first_name . ' ' . $timeSheet->user->last_name,
            'employee_title' => $timeSheet->user->roles->first()->name,
            'employee_image' => $timeSheet->user->passport,
            'employee_activities' => [],
            'last_entry' => null
        ], 200);
    }

    $user = $firstWeekLog->timeSheet->user;
    if (is_null($user)) {
        return response()->json([
            'status' => 404,
            'message' => 'User not found'
        ], 404);
    }

    // Format the response according to the specified structure
    $formattedLogs = $weekLogs->groupBy('week_number')->map(function ($weekGroup) use (&$state, $request) {
        $year = $weekGroup->first()->year;
        $weekNumber = $weekGroup->first()->week_number;
        $startDate = Carbon::now()->setISODate($year, $weekNumber)->startOfWeek();
        $endDate = Carbon::now()->setISODate($year, $weekNumber)->endOfWeek();

        $days = $weekGroup->groupBy(function ($log) {
            return Carbon::parse($log->timeSheet->created_at)->format('m-d-Y');
        });

        $activitiesByDay = $days->map(function ($dayGroup) use ($startDate, $endDate, $request) {
            $dayActivities = [];

            foreach ($dayGroup as $log) {
                if ($log->timeSheet && $log->timeSheet->activities) {
                    $entries = json_decode($log->timeSheet->activities, true);
                    foreach ($entries as $entry) {
                        $clockInDate = Carbon::parse($entry['clock_in']);
                        if ($clockInDate->between($startDate, $endDate)) {
                            $schedule = Schedule::where(['gig_id' => $log->timeSheet->gig_id])->first();
                            $scheduleArray = json_decode($schedule->schedule, true);
                            $day = $clockInDate->format('l');
                            $times = $this->getStartAndEndTime($scheduleArray, $day);
                            $entryKey = $entry['clock_in'] . '-' . $entry['clock_out'];
                            if (!isset($dayActivities[$entryKey])) {
                                $dayActivities[$entryKey] = [
                                    'date' => $clockInDate->format('m-d-Y'),
                                    'day' => $clockInDate->format('l'),
                                    'clock_in' => $clockInDate->format('m-d-Y H:i:s'),
                                    'clock_out' => $entry['clock_out'] ? Carbon::parse($entry['clock_out'])->format('m-d-Y H:i:s') : null,
                                    'expected_clock_in_time' => isset($entry['emergency_clock_in']) && $entry['emergency_clock_in'] === true 
                                                                ? $clockInDate->format('H:i:s') 
                                                                : Carbon::parse($times['start_time'])->format('H:i:s'),
                                    'expected_clock_out_time' => isset($entry['emergency_clock_in']) && $entry['emergency_clock_in'] === true 
                                                                    ? ($entry['clock_out'] ? Carbon::parse($entry['clock_out'])->format('H:i:s') : null)
                                                                    : Carbon::parse($times['end_time'])->format('H:i:s'),
                                    'activity_id' => $entry['activity_id'],
                                    'report' => [],
                                    'progress' => $this->fetchProgressReports($request->timesheet_id, $entry['activity_id'])
                                ];
                            }
                        }
                    }
                }
            }

            foreach ($dayGroup as $log) {
                if ($log->incidentReports && $log->incidentReports->isNotEmpty()) {
                    foreach ($log->incidentReports as $report) {
                        $reportDate = Carbon::parse($report->created_at)->format('m-d-Y');
                        foreach ($dayActivities as &$activity) {
                            if ($reportDate == $activity['date'] && $report->activity_id == $activity['activity_id']) {
                                $reportExists = false;
                                foreach ($activity['report'] as $existingReport) {
                                    if ($existingReport['report_id'] == $report->id) {
                                        $reportExists = true;
                                        break;
                                    }
                                }
                                if (!$reportExists) {
                                    $activity['report'][] = [
                                        'report_id' => $report->id,
                                        'title' => $report->title,
                                        'description' => $report->description,
                                        'incident_time' => $report->incident_time,
                                        'created_at' => $report->created_at->format('m-d-Y H:i:s')
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            return array_values($dayActivities);
        });

        $flattenedActivities = $activitiesByDay->flatten(1);

        $lastEntry = $flattenedActivities->last();
        if ($lastEntry) {
            $state = is_null($lastEntry['clock_out']) ? 'Clock out' : 'Clock in';
        }

        return [
            'week' => $weekNumber,
            'year' => $year,
            'activities' => $flattenedActivities
        ];
    });

    $timeSheet = TimeSheet::where('unique_id', $request->timesheet_id)->first();
    $lastEntry = null;
    if ($timeSheet && $timeSheet->activities) {
        $activities = json_decode($timeSheet->activities, true);
        $lastEntry = end($activities);
        $lastEntry = [
            'clock_in' => Carbon::parse($lastEntry['clock_in'])->format('m-d-Y H:i:s'),
            'clock_out' => $lastEntry['clock_out'] ? Carbon::parse($lastEntry['clock_out'])->format('m-d-Y H:i:s') : null,
        ];
    }

    return response()->json([
        'status' => 200,
        'message' => 'Weekly activity log',
        'state' => $lastEntry && is_null($lastEntry['clock_out']) ? 'Clock out' : 'Clock in',
        'employee_name' => $user->first_name . ' ' . $user->last_name,
        'employee_title' => $user->roles->first()->name,
        'employee_image' => $user->passport,
        'employee_activities' => $formattedLogs->values()->all(),
        'last_entry' => $lastEntry
    ], 200);
}

// Fetch progress reports for a specific timesheet_id and activity_id
private function fetchProgressReports($timesheet_id, $activity_id)
{
    return ProgressReport::where('timesheet_id', $timesheet_id)
        ->where('activity_id', $activity_id)
        ->get()
        ->map(function ($progress) {
            return [
                'progress_id' => $progress->id,
                'title' => $progress->title,
                'description' => $progress->description,
                'progress_time' => $progress->progress_time,
                'created_at' => $progress->created_at->format('m-d-Y H:i:s')
            ];
        })
        ->toArray();
}







        private function getStartAndEndTime($scheduleArray, $day) {
            foreach ($scheduleArray as $schedule) {
                if (strtolower($schedule['day']) === strtolower($day)) {
                    return [
                        'start_time' => $schedule['start_time'],
                        'end_time' => $schedule['end_time']
                    ];
                }
            }
            return null; // Return null if the day is not found
        }
    
        public function timesheet(Request $request)
        {
            $timesheet = TimeSheet::where([
                'user_id' => auth('api')->user()->id
            ])->with(['gigs.client', 'gigs.schedule', 'user'])->orderBy('created_at', 'desc')->get();
            if ($timesheet->isEmpty()) {
                return response()->json(['status'=>200,'response'=>'Not Found','message'=>'Time Sheet(s) does not exist', 'data' => $timesheet], 200);
            }
            $formattedGigs = $timesheet->map(function ($timesheet) {
                return [
                    'id' => $timesheet->unique_id,
                    'title' => $timesheet->gigs->title,
                    'description' => $timesheet->gigs->description,
                    'type' => $timesheet->gigs->gig_type,
                    'client_address' => $timesheet->gigs->client ? $timesheet->gigs->client->address1 : null,
                    'schedule' => $timesheet->gigs->schedule,
                    'dateCreated' => $timesheet->gigs->created_at->format('m-d-Y'),
                ];
            });
            return response()->json(['status' => 200,'response' => 'Time Sheet(s) fetch successfully','data' => $formattedGigs], 200);
        }
        
        public function single_timesheet(Request $request)
        {
            $timesheet = TimeSheet::where(['user_id' => auth('api')->user()->id, 'id' => $request->id])->with(['incidents_report','gigs.schedule','gigs.client','user'])->first();
            if (!$timesheet) {
                return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Time Sheet does not exist'], 404);
            }
            return response()->json(['status' => 200,'response' => 'Time Sheet fetch successfully','data' => $timesheet], 200);
        }
    
        public function single_timesheet_by_uniqueID(Request $request)
        {
            $timesheet = TimeSheet::where(['user_id' => auth('api')->user()->id, 'unique_id' => $request->unique_id])->with(['gigs.schedule','gigs.client','user'])->first();
            if (!$timesheet) {
                return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Time Sheet does not exist'], 404);
            }
            return response()->json(['status' => 200,'response' => 'Time Sheet fetch successfully','data' => $timesheet], 200);
        }
}
