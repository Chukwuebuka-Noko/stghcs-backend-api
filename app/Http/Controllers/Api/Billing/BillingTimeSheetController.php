<?php

namespace App\Http\Controllers\Api\Billing;

use Carbon\Carbon;
use App\Models\WeekLog;
use App\Models\Schedule;
use App\Models\TimeSheet;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\ProgressReport;
use Illuminate\Support\Facades\Validator;

class BillingTimeSheetController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware(['role:Billing']);
    }

    public function weeklyLog(Request $request)
    {
        // Fetch week logs for a specific timesheet including time sheet and incident reports data
        $weekLogs = WeekLog::with(['timeSheet.user', 'incidentReports'])
            ->whereHas('timeSheet', function ($query) use ($request) {
                $query->where('unique_id', $request->timesheet_id);
            })
            ->orderBy('week_number', 'desc')
            ->get();
    
        // Default state
        $state = 'Clock in';
    
        // Check if there is at least one log and it has an associated timesheet
        $firstWeekLog = $weekLogs->first();
        if (is_null($firstWeekLog) || is_null($firstWeekLog->timeSheet)) {
            // Fetch the timesheet directly if not available in week logs
            $timeSheet = $firstWeekLog ? $firstWeekLog->timeSheet : TimeSheet::where('unique_id', $request->timesheet_id)->with('user')->first();
            
            // Check if timesheet is null
            if (is_null($timeSheet)) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Timesheet not found'
                ], 404);
            }
            
            // Check if user is null
            $user = $timeSheet->user;
            if (is_null($user)) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found'
                ], 404);
            }
    
            return response()->json([
                'status' => 200,
                'response' => 'Week Log not found',
                'message' => 'Weekly activity log',
                'state' => $state,
                'employee_name' => $user->first_name . ' ' . $user->last_name,
                'employee_title' => optional($user->roles->first())->name,
                'employee_image' => $user->passport,
                'employee_activities' => []
            ], 200);
        }
    
        // Get the user associated with the timeSheet
        $user = $firstWeekLog->timeSheet->user;
        if (is_null($user)) {
            return response()->json([
                'status' => 404,
                'message' => 'User not found'
            ], 404);
        }
    
        // Format the response according to the specified structure
        $formattedLogs = $weekLogs->groupBy('week_number')->map(function ($weekGroup) use (&$state, $request) {
            // Get the start and end dates of the current week
            $year = $weekGroup->first()->year;
            $weekNumber = $weekGroup->first()->week_number;
            $startDate = Carbon::now()->setISODate($year, $weekNumber)->startOfWeek();
            $endDate = Carbon::now()->setISODate($year, $weekNumber)->endOfWeek();
    
            // Grouping by day within each week to handle timesheet entries and incident reports correctly
            $days = $weekGroup->groupBy(function ($log) {
                return Carbon::parse($log->timeSheet->created_at)->format('m-d-Y');
            });
    
            // Mapping each day's data
            $activitiesByDay = $days->map(function ($dayGroup) use ($startDate, $endDate, $request) {
                $dayActivities = [];
    
                foreach ($dayGroup as $log) {
                    // Process time sheet entries
                    if ($log->timeSheet && $log->timeSheet->activities) {
                        $entries = json_decode($log->timeSheet->activities, true);
                        foreach ($entries as $entry) {
                            $clockInDate = Carbon::parse($entry['clock_in']);
                            if ($clockInDate->between($startDate, $endDate)) {
                                $schedule = Schedule::where(['gig_id' => $log->timeSheet->gig_id])->first();
                                $scheduleArray = json_decode($schedule->schedule ?? '[]', true);
                                $day = $clockInDate->format('l');
                                $times = $this->getStartAndEndTime($scheduleArray, $day);
                                $entryKey = $entry['clock_in'] . '-' . $entry['clock_out'];
                                if (!isset($dayActivities[$entryKey])) { // Check if this entry already exists
                                    $dayActivities[$entryKey] = [
                                        'date' => $clockInDate->format('m-d-Y'),
                                        'day' => $clockInDate->format('l'),
                                        'activity_id' => $entry['activity_id'],
                                        'clock_in' => $clockInDate->format('m-d-Y H:i:s'),
                                        'clock_out' => $entry['clock_out'] ? Carbon::parse($entry['clock_out'])->format('m-d-Y H:i:s') : null,
                                        'expected_clock_in_time' => isset($entry['emergency_clock_in']) && $entry['emergency_clock_in'] === true 
                                                                    ? $clockInDate->format('H:i:s') 
                                                                    : Carbon::parse($times['start_time'])->format('H:i:s'),
                                        'expected_clock_out_time' => isset($entry['emergency_clock_in']) && $entry['emergency_clock_in'] === true 
                                                                        ? ($entry['clock_out'] ? Carbon::parse($entry['clock_out'])->format('H:i:s') : null)
                                                                        : Carbon::parse($times['end_time'])->format('H:i:s'),
                                        'report' => [],
                                        'progress' => $this->fetchProgressReports($request->timesheet_id, $entry['activity_id'])
                                    ];
                                }
                            }
                        }
                    }
                }
    
                // Process incident reports for the same date
                foreach ($dayGroup as $log) {
                    if ($log->incidentReports && $log->incidentReports->isNotEmpty()) {
                        foreach ($log->incidentReports as $report) {
                            $reportDate = Carbon::parse($report->created_at)->format('m-d-Y');
                            foreach ($dayActivities as &$activity) {
                                if ($reportDate == $activity['date']) { // Ensure report is for the correct day
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
    
                return array_values($dayActivities); // Convert associative array back to indexed array
            });
    
            // Flatten the activities by day to a single level
            $flattenedActivities = $activitiesByDay->flatten(1);
    
            // Determine the state based on the last entry
            $lastEntry = $flattenedActivities->last();
            if ($lastEntry) {
                $state = $lastEntry['clock_out'] ? 'Clock in' : 'Clock out';
            }
    
            return [
                'week' => $weekNumber,
                'year' => $year,
                'activities' => $flattenedActivities
            ];
        });
    
        // Get the last entry from the timesheet activities directly
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

    public function weeklyLogNumber(Request $request)
    {
        // Fetch week logs for a specific week number including time sheet and incident reports data
        $weekLogs = WeekLog::with(['timeSheet.user', 'incidentReports'])
            ->where(['week_number' => $request->week_number, 'year' => $request->year])
            ->whereHas('timeSheet', function ($query) use ($request) {
                $query->where('user_id', $request->user_id); // Filter by user ID
            })
            ->orderBy('week_number', 'desc')
            ->get();
    
        // Check if there are any logs for the specified user
        if ($weekLogs->isEmpty()) {
            return response()->json([
                'status' => 404,
                'message' => 'No logs found for the specified user'
            ], 404);
        }
    
        // Get the start and end dates of the specified week number
        $year = $request->year ?? Carbon::now()->year;
        $startDate = Carbon::now()->setISODate($year, $request->week_number)->startOfWeek();
        $endDate = Carbon::now()->setISODate($year, $request->week_number)->endOfWeek();
    
        // Group logs by user (there should be only one user since we filtered by user ID)
        $logsGroupedByUser = $weekLogs->groupBy(function ($log) {
            return $log->timeSheet->user->id;
        })->map(function ($userLogs) use ($startDate, $endDate) {
            // Get user information
            $user = $userLogs->first()->timeSheet->user;
    
            // Map user logs to desired format
            $userActivities = $userLogs->groupBy('week_number')->map(function ($weekGroup) use ($startDate, $endDate) {
                // Group by day within each week
                $days = $weekGroup->groupBy(function ($log) {
                    return Carbon::parse($log->timeSheet->created_at)->format('m-d-Y');
                });
    
                // Map each day's data
                $activitiesByDay = $days->map(function ($dayGroup) use ($startDate, $endDate) {
                    $dayActivities = [];
    
                    foreach ($dayGroup as $log) {
                        // Process time sheet entries
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
                                    if (!isset($dayActivities[$entryKey])) { // Check if this entry already exists
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
                                            'report' => [],
                                            //'progress' => $this->fetchProgressReports($request->timesheet_id, $entry['activity_id'])
                                        ];
                                    }
                                }
                            }
                        }
                    }
    
                    // Process incident reports for the same date
                    foreach ($dayGroup as $log) {
                        if ($log->incidentReports && $log->incidentReports->isNotEmpty()) {
                            foreach ($log->incidentReports as $report) {
                                $reportDate = Carbon::parse($report->created_at)->format('m-d-Y');
                                foreach ($dayActivities as &$activity) {
                                    if ($reportDate == $activity['date']) { // Ensure report is for the correct day
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
    
                    return array_values($dayActivities); // Convert associative array back to indexed array
                });
    
                // Flatten the activities by day to a single level
                $flattenedActivities = $activitiesByDay->flatten(1);
    
                return [
                    'week' => $weekGroup->first()->week_number,
                    'year' => $weekGroup->first()->year,
                    'activities' => $flattenedActivities
                ];
            });
    
            return [
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'logs' => $userActivities->values()->all()
            ];
        })->first(); // Since there will be only one user, we can use first() to get the user data
    
        return response()->json([
            'status' => 200,
            'message' => 'Weekly activity log for specified user',
            'user' => $logsGroupedByUser
        ], 200);
    }

    private function getStartAndEndTime($scheduleArray, $day)
    {
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
        // Get the authenticated user's location_id
        $userLocationId = auth('api')->user()->location_id;

        // Fetch all timesheets where the user has the same location_id
        $timesheets = TimeSheet::whereHas('user')->with(['gigs.client', 'gigs.schedule', 'user'])->orderBy('created_at','desc')->get();

        if ($timesheets->isEmpty()) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Time Sheet(s) does not exist'], 404);
        }

        $formattedGigs = $timesheets->map(function ($timesheet) {
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

        return response()->json(['status' => 200, 'response' => 'Time Sheet(s) fetch successfully', 'data' => $formattedGigs], 200);
    }

    public function single_timesheet(Request $request)
    {
        $timesheet = TimeSheet::where(['user_id' => auth('api')->user()->id, 'id' => $request->id])->with(['incidents_report', 'gigs.client', 'user'])->first();
        if (!$timesheet) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Time Sheet does not exist'], 404);
        }
        return response()->json(['status' => 200, 'response' => 'Time Sheet fetch successfully', 'data' => $timesheet], 200);
    }

    public function single_timesheet_by_uniqueID(Request $request)
    {
        $timesheet = TimeSheet::where(['unique_id' => $request->unique_id])->with(['gigs.client', 'user'])->first();
        if (!$timesheet) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Time Sheet does not exist'], 404);
        }
        return response()->json(['status' => 200, 'response' => 'Time Sheet fetch successfully', 'data' => $timesheet], 200);
    }
    
    public function getActivities()
    {
    // Get the authenticated user's location_id
    $userLocationId = auth('api')->user()->location_id;

    // Fetch all timesheets where the user has the same location_id
    $timesheets = TimeSheet::whereHas('user')->with(['gigs.client', 'gigs.schedule', 'user'])->orderBy('created_at', 'desc')->get();

    if ($timesheets->isEmpty()) {
        return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Time Sheet(s) does not exist'], 404);
    }

    // Group the timesheets by user and format the data
    $groupedTimesheets = $timesheets->groupBy('user.id')->map(function ($group) {
        $user = $group->first()->user;
        $activities = $group->map(function ($timesheet) {
            return [
                'timesheet_id' => $timesheet->unique_id,
                'activities' => json_decode($timesheet->activities),
                'activity_time' => $timesheet->updated_at
            ];
        });

        return [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'activities' => $activities
        ];
    });

    return response()->json(['status' => 200, 'response' => 'Time Sheet(s) fetched successfully', 'data' => $groupedTimesheets->values()], 200);
}

    public function timesheet_activities(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => ['required', 'string'],
            'start_date' => ['required', 'string'],
            'end_date' => ['required', 'string']
        ]);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
        }
    
        $timesheet = TimeSheet::where(['unique_id' => $request->timesheet_id])
            ->with(['user', 'gigs', 'gigs.client', 'gigs.gig_type', 'assignments', 'weekLog', 'incidents_report', 'progress_report'])
            ->first();
    
        $start_date = $request->start_date;
        $end_date = $request->end_date;
    
        $activities = $timesheet->activities;
        $gig_title = $timesheet->gigs->title;
        $gig_type = $timesheet->gigs->gig_type;
        $client = $timesheet->gigs->client->first_name . " " . $timesheet->gigs->client->last_name . " " . $timesheet->gigs->client->other_name;
        $support_worker = $timesheet->user->first_name . " " . $timesheet->user->last_name . " " . $timesheet->user->other_name;
    
        // Assuming activities are stored as a JSON string, decode it to an array
        $activitiesArray = json_decode($activities, true);
    
        // Filter activities where clock_in is between start_date and end_date
        $filteredActivities = array_filter($activitiesArray, function ($activity) use ($start_date, $end_date) {
            $clockInDate = isset($activity['clock_in']) ? new \DateTime($activity['clock_in']) : null;
            $startDate = new \DateTime($start_date);
            $endDate = new \DateTime($end_date);
            $endDate->modify('+1 day');
    
            return $clockInDate && $clockInDate >= $startDate && $clockInDate <= $endDate;
        });
    
        // Reindex the filtered activities to make them a normal array
        $filteredActivities = array_values($filteredActivities);
    
        // Prepare a structured response
        $enrichedActivities = array_map(function ($activity) use ($gig_title, $gig_type, $client, $support_worker) {
            $flags = isset($activity['flags']) && is_string($activity['flags']) ? json_decode($activity['flags'], true) : $activity['flags'];
    
            $formattedFlags = array_map(function ($flag) {
                return [
                    'title' => $flag['title'] ?? '',
                    'remark' => $flag['remark'] ?? '',
                    'description' => $flag['description'] ?? ''
                ];
            }, $flags);
    
            $duration = null;
            if (isset($activity['clock_in']) && isset($activity['clock_out'])) {
                $clockInDate = new \DateTime($activity['clock_in']);
                $clockOutDate = new \DateTime($activity['clock_out']);
                $interval = $clockInDate->diff($clockOutDate);
                $duration = $interval->format('%h hours %i minutes');
            }
    
            return [
                'activity_id' => $activity['activity_id'] ?? null,
                'clock_in' => $activity['clock_in'] ?? null,
                'clock_out' => $activity['clock_out'] ?? null,
                'clock_in_status' => $activity['clock_in_status'] ?? null,
                'clock_out_status' => $activity['clock_out_status'] ?? null,
                'clock_in_report' => $activity['clock_in_report'] ?? null,
                'clock_out_report' => $activity['clock_out_report'] ?? null,
                'clock_in_coordinate' => isset($activity['clock_in_coordinate']) ? json_decode($activity['clock_in_coordinate'], true) : null,
                'clock_out_coordinate' => isset($activity['clock_out_coordinate']) ? json_decode($activity['clock_out_coordinate'], true) : null,
                'duration' => $duration,
                'gig_title' => $gig_title,
                'gig_type' => $gig_type,
                'client' => $client,
                'support_worker' => $support_worker,
                'flags' => $formattedFlags
            ];
        }, $filteredActivities);
    
        return response()->json([
            'status' => 200,
            'message' => 'Timesheet activities fetched successfully',
            'data' => [
                'gig_title' => $gig_title,
                'gig_type' => $gig_type,
                'client' => $client,
                'support_worker' => $support_worker,
                'activities' => $enrichedActivities // Returning as an indexed array
            ]
        ], 200);
    }

    public function user_timesheet_activities(Request $request)
{
    // Validate the request input
    $validator = Validator::make($request->all(), [
        'user_id' => ['required', 'string'],
        'start_date' => ['required', 'string'],
        'end_date' => ['required', 'string']
    ]);

    // If validation fails, return a 422 error response
    if ($validator->fails()) {
        $errors = $validator->errors()->all();
        return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
    }

    // Fetch the timesheets for the specified user with relationships
    $timesheet = TimeSheet::where(['user_id' => $request->user_id])
        ->with(['user', 'gigs', 'gigs.client', 'gigs.gig_type', 'assignments', 'weekLog', 'incidents_report', 'progress_report'])
        ->get();

    $enrichedActivities = [];

    foreach ($timesheet as $ts) {
        // Access the 'activities' field from each timesheet
        $activities = $ts->activities ?? null;

        // Check if activities is not null and is a valid JSON string
        if ($activities && is_string($activities)) {
            $activitiesArray = json_decode($activities, true);

            // Ensure json_decode was successful
            if (json_last_error() === JSON_ERROR_NONE && is_array($activitiesArray)) {

                // Get gig details and other information
                $gig_title = $ts->gigs->title;
                $gig_type = $ts->gigs->gig_type;
                $client = $ts->gigs->client->first_name . " " . $ts->gigs->client->last_name . " " . $ts->gigs->client->other_name;
                $support_worker = $ts->user->first_name . " " . $ts->user->last_name . " " . $ts->user->other_name;

                // Filter activities based on clock_in date
                $filteredActivities = array_filter($activitiesArray, function ($activity) use ($request) {
                    $clockInDate = isset($activity['clock_in']) ? new \DateTime($activity['clock_in']) : null;
                    $startDate = new \DateTime($request->start_date);
                    $endDate = new \DateTime($request->end_date);
                    $endDate->modify('+1 day');

                    return $clockInDate && $clockInDate >= $startDate && $clockInDate <= $endDate;
                });

                // Enrich the filtered activities
                foreach ($filteredActivities as $activity) {
                    $activity['gig_title'] = $gig_title;
                    $activity['gig_type'] = $gig_type;
                    $activity['client'] = $client;
                    $activity['support_worker'] = $support_worker;

                    // Initialize the duration key with a default value
                    $activity['duration'] = null;

                    // Calculate duration if both clock_in and clock_out are present
                    if (isset($activity['clock_in']) && isset($activity['clock_out'])) {
                        $clockInDate = new \DateTime($activity['clock_in']);
                        $clockOutDate = new \DateTime($activity['clock_out']);
                        $interval = $clockInDate->diff($clockOutDate);
                        $activity['duration'] = $interval->format('%h hours %i minutes');
                    }

                    $enrichedActivities[] = $activity;
                }
            }
        }
    }

    // Return the enriched activities as a collection
    return response()->json(['status' => 200, 'data' => $enrichedActivities], 200);
}

}
