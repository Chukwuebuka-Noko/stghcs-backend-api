<?php

namespace App\Http\Controllers\Api\Billing;

use Carbon\Carbon;
use App\Models\Gig;
use App\Models\User;
use App\Models\TimeSheet;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Models\ActivityRemark;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Rules\ActivityExists;

class BillingDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware(['role:Billing']);
    }
    
    public function dashboard(Request $request) 
    {
        $manager = User::find(auth('api')->user()->id);
        if (!$manager) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'Manager not found.'
            ], 404);
        }
    
        $userLocationId = $manager->location_id;
    
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }
    
        $dsws = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['DSW']);
            })
            ->count();
    
        $supervisor = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['Supervisor']);
            })
            ->count();
    
        $csps = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['CSP']);
            })
            ->count();
    
        $totalGigs = Gig::whereHas('creator')->count();
    
        $acceptedGigs = Gig::whereIn('status', ['assigned', 'accepted'])
            ->whereHas('creator')->count();
    
        $pendingGigs = Gig::where('status', 'pending')
            ->whereHas('creator')->count();

    $activeDSWs = User::role('DSW')
    ->whereHas('timesheets', function($query) {
        $query->where('status', 'started')
              ->whereDate('created_at', '>=', now()->subMonth())
              ->where(function ($q) {
                  $q->whereJsonContains('activities', function($activity) {
                      return isset($activity['clock_in_status']) || isset($activity['clock_out_status']);
                  });
              });
    })
    ->with(['timesheets' => function($query) {
        $query->where('status', 'started')
              ->whereDate('created_at', '>=', now()->subMonth())
              ->where(function ($q) {
                  $q->whereJsonContains('activities', function($activity) {
                      return isset($activity['clock_in_status']) || isset($activity['clock_out_status']);
                  });
              });
    }])
    ->get()
    ->map(function($dsw) {
        $activity = $dsw->timesheets->map(function($timesheet) {
            $activities = json_decode($timesheet->activities, true);
            if (is_array($activities)) {
                $filteredActivities = array_filter(array_map(function($activity) {
                    return (object) [
                        'clock_in' => $activity['clock_in'] ?? null,
                        'clock_out' => $activity['clock_out'] ?? null,
                        'duration' => $activity['duration'] ?? null,
                        'clock_in_status' => $activity['clock_in_status'] ?? null,
                        'clock_out_status' => $activity['clock_out_status'] ?? null,
                        'flags' => $activity['flags'] ?? []
                    ];
                }, $activities));

                // Sort activities by the most recent time (clock_in or clock_out)
                usort($filteredActivities, function($a, $b) {
                    $a_time = $a->clock_out ?? $a->clock_in;
                    $b_time = $b->clock_out ?? $b->clock_in;
                    return strtotime($b_time) - strtotime($a_time);
                });

                // Return the most recent activity
                if ($filteredActivities) {
                    $mostRecentActivity = reset($filteredActivities);
                    return (object) [
                        'timesheet_id' => $timesheet->unique_id,
                        'last_activity' => (object) [
                            'title' => $mostRecentActivity->clock_out ? 'Clocked Out' : 'Clocked In',
                            'time' => $mostRecentActivity->clock_out ?? $mostRecentActivity->clock_in,
                            'status' => $mostRecentActivity->clock_out_status ?? $mostRecentActivity->clock_in_status,
                            'flags' => $mostRecentActivity->flags
                        ]
                    ];
                }
            }
            return null;
        })->filter()->first();

        if ($activity) {
            return (object) [
                'id' => $dsw->id,
                'first_name' => $dsw->first_name,
                'last_name' => $dsw->last_name,
                'other_name' => $dsw->other_name,
                'location' => $dsw->address1,
                'gender' => $dsw->gender,
                'city' => $dsw->location->city,
                'passport' => $dsw->passport,
                'employee_id' => $dsw->employee_id,
                'activity' => $activity
            ];
        }
        return null;
    })
    ->filter()
    ->sortByDesc(function($dsw) {
        if (isset($dsw->activity->last_activity->time)) {
            return strtotime($dsw->activity->last_activity->time);
        }
        return 0;
    })
    ->values()
    ->take(7);
    
    $activeCSPs = User::role('CSP')
    ->whereHas('timesheets', function($query) {
        $query->where('status', 'started')
              ->whereDate('created_at', '>=', now()->subMonth())
              ->where(function ($q) {
                  $q->whereJsonContains('activities', function($activity) {
                      return isset($activity['clock_in_status']) || isset($activity['clock_out_status']);
                  });
              });
    })
    ->with(['timesheets' => function($query) {
        $query->where('status', 'started')
              ->whereDate('created_at', '>=', now()->subMonth())
              ->where(function ($q) {
                  $q->whereJsonContains('activities', function($activity) {
                      return isset($activity['clock_in_status']) || isset($activity['clock_out_status']);
                  });
              });
    }])
    ->get()
    ->map(function($csp) {
        $activity = $csp->timesheets->map(function($timesheet) {
            $activities = json_decode($timesheet->activities, true);
            if (is_array($activities)) {
                $filteredActivities = array_filter(array_map(function($activity) {
                    return (object) [
                        'clock_in' => $activity['clock_in'] ?? null,
                        'clock_out' => $activity['clock_out'] ?? null,
                        'duration' => $activity['duration'] ?? null,
                        'clock_in_status' => $activity['clock_in_status'] ?? null,
                        'clock_out_status' => $activity['clock_out_status'] ?? null,
                        'flags' => $activity['flags'] ?? []
                    ];
                }, $activities));

                // Sort activities to get the most recent one
                usort($filteredActivities, function($a, $b) {
                    $a_time = $a->clock_out ?? $a->clock_in;
                    $b_time = $b->clock_out ?? $b->clock_in;
                    return strtotime($b_time) - strtotime($a_time);
                });

                // Return the most recent activity
                if ($filteredActivities) {
                    $lastActivity = reset($filteredActivities);
                    return (object) [
                        'timesheet_id' => $timesheet->unique_id,
                        'last_activity' => (object) [
                            'title' => $lastActivity->clock_out ? 'Clocked Out' : 'Clocked In',
                            'time' => $lastActivity->clock_out ?? $lastActivity->clock_in,
                            'status' => $lastActivity->clock_out_status ?? $lastActivity->clock_in_status,
                            'flags' => $lastActivity->flags
                        ]
                    ];
                }
            }
            return null;
        })->filter()->first(); // Take the most recent valid activity

        if ($activity) {
            return (object) [
                'id' => $csp->id,
                'first_name' => $csp->first_name,
                'last_name' => $csp->last_name,
                'other_name' => $csp->other_name,
                'location' => $csp->address1,
                'gender' => $csp->gender,
                'city' => $csp->location->city,
                'passport' => $csp->passport,
                'employee_id' => $csp->employee_id,
                'activity' => $activity
            ];
        }
        return null;
    })
    ->filter()
    ->sortByDesc(function($csp) {
        if (isset($csp->activity->last_activity->time)) {
            return strtotime($csp->activity->last_activity->time);
        }
        return 0;
    })
    ->values()
    ->take(7);

    
        $recentTimesheetActions = TimeSheet::with(['user'])
            ->whereHas('user')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->flatMap(function($timesheet) {
                if ($timesheet->weekLog) {
                    $weekLog = json_decode($timesheet->weekLog, true);
                    return array_map(function($log) use ($timesheet) {
                        $activity_time = $this->convertToDateTime($log['week_number'], $log['day'], $log['year'], $log['time']);
                        return [
                            'first_name' => $timesheet->user->first_name,
                            'last_name' => $timesheet->user->last_name,
                            'other_name' => $timesheet->user->other_name,
                            'location' => $timesheet->user->address1,
                            'gender' => $timesheet->user->gender,
                            'passport' => $timesheet->user->passport,
                            'activity' => $this->convertToPastTense($log['type']),
                            'activity_time' => $activity_time,
                            'city' => $timesheet->user->location->city
                        ];
                    }, $weekLog);
                }
                return [];
            })
            ->sortByDesc('updated_at')
            ->take(7)
            ->values();
    
    $inactiveDSWs = User::role('DSW')
    ->where(function($query) {
        // Users who do not have any timesheets
        $query->doesntHave('timesheets')
              // Users who have timesheets but their activities are null
              ->orWhereHas('timesheets', function($query) {
                  $query->whereNull('activities');
              })
              // Users who have timesheets but haven't clocked in or out in the past month
              ->orWhereDoesntHave('timesheets', function($query) {
                  $query->whereDate('created_at', '>=', now()->subMonth())
                        ->where(function ($q) {
                            $q->whereJsonContains('activities', function($activity) {
                                return isset($activity['clock_in_status']) || isset($activity['clock_out_status']);
                            });
                        });
              });
    })
    ->limit(7)
    ->get()
    ->map(function($dsw) {
        return [
            'id' => $dsw->id,
            'first_name' => $dsw->first_name,
            'last_name' => $dsw->last_name,
            'other_name' => $dsw->other_name,
            'location' => $dsw->address1,
            'city' => $dsw->location->city,
            'gender' => $dsw->gender,
            'passport' => $dsw->passport,
            'employee_id' => $dsw->employee_id,
        ];
    })->take(7);



    
        $inactiveCSPs = User::role('CSP')
            ->doesntHave('timesheets')
            ->get()
            ->map(function($csp) {
                return [
                    'id' => $csp->id,
                    'first_name' => $csp->first_name,
                    'last_name' => $csp->last_name,
                    'other_name' => $csp->other_name,
                    'location' => $csp->address1,
                    'city' => $csp->location->city,
                    'gender' => $csp->gender,
                    'passport' => $csp->passport,
                    'employee_id' => $csp->employee_id,
                ];
            })->take(7);
    
        $lateClockIns = TimeSheet::with(['gigs.client', 'user'])
            ->whereHas('user', function($query) use ($manager) {
                $query->where('location_id', $manager->location_id);
            })->get()->filter(function($timesheet) {
                $activities = json_decode($timesheet->activities, true);
                if (is_array($activities)) {
                    foreach ($activities as $activity) {
                        if (isset($activity['clock_in_status']) && $activity['clock_in_status'] === 'Came Late') {
                            return true;
                        }
                    }
                }
                return false;
            })->map(function($timesheet) {
                $activities = json_decode($timesheet->activities, true);
                $lateClockInTimes = [];
                if (is_array($activities)) {
                    foreach ($activities as $activity) {
                        if (isset($activity['clock_in_status']) && $activity['clock_in_status'] === 'Came Late') {
                            $lateClockInTimes[] = [
                                'clock_in_time' => $activity['clock_in'],
                                'gig' => $timesheet->gigs,
                                'user' => [
                                    'id' => $timesheet->user->id,
                                    'first_name' => $timesheet->user->first_name,
                                    'last_name' => $timesheet->user->last_name,
                                    'other_name' => $timesheet->user->other_name,
                                    'location_id' => $timesheet->user->location_id,
                                    'gender' => $timesheet->user->gender,
                                    'passport' => $timesheet->user->passport,
                                    'employee_id' => $timesheet->user->employee_id,
                                ],
                            ];
                        }
                    }
                }
                return $lateClockInTimes;
            })->flatten(1)->take(7);
    
        $recentFlags = TimeSheet::with(['gigs', 'user', 'weekLog'])
            ->whereHas('user')->orderBy('updated_at', 'desc')->get()->map(function($timesheet) {
                $activities = json_decode($timesheet->activities, true);
                $flags = [];
                if (is_array($activities)) {
                    foreach ($activities as $activity) {
                        if (isset($activity['flags'])) {
                            foreach ($activity['flags'] as $flag) {
                                $flags[] = [
                                    'flag' => $flag,
                                    'gig' => $timesheet->gigs,
                                    'user' => [
                                        'id' => $timesheet->user->id,
                                        'first_name' => $timesheet->user->first_name,
                                        'last_name' => $timesheet->user->last_name,
                                        'other_name' => $timesheet->user->other_name,
                                        'location' => $timesheet->user->location->city,
                                        'address' => $timesheet->user->address1,
                                        'gender' => $timesheet->user->gender,
                                        'passport' => $timesheet->user->passport,
                                        'employee_id' => $timesheet->user->employee_id,
                                    ],
                                ];
                            }
                        }
                    }
                }
                return $flags;
            })->flatten(1)->take(7);
            
            $countFlags = TimeSheet::with(['gigs', 'user', 'weekLog'])
        ->whereHas('user')->orderBy('updated_at', 'desc')->take(10)->get()->map(function($timesheet) {
            $activities = json_decode($timesheet->activities, true);
            $flags = [];
            if (is_array($activities)) {
                foreach ($activities as $activity) {
                    if (isset($activity['flags'])) {
                        foreach ($activity['flags'] as $flag) {
                            $flags[] = [
                                'flag' => $flag,
                                'gig' => $timesheet->gigs,
                                'user' => [
                                    'id' => $timesheet->user->id,
                                    'first_name' => $timesheet->user->first_name,
                                    'last_name' => $timesheet->user->last_name,
                                    'other_name' => $timesheet->user->other_name,
                                    'location' => $timesheet->user->location->city,
                                    'address' => $timesheet->user->address1,
                                    'gender' => $timesheet->user->gender,
                                    'passport' => $timesheet->user->passport,
                                    'employee_id' => $timesheet->user->employee_id,
                                ],
                            ];
                        }
                    }
                }
            }
            return $flags;
        })->flatten(1);
    
    // Count the total number of flags
    $totalFlags = $countFlags->count();
    
        $supervisors = User::whereHas('roles', function($query) {
                $query->where('name', 'Supervisor');
            })
            ->with(['gigsInCharge' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->get()
            ->map(function($supervisor) {
                $gigsInCharge = $supervisor->gigsInCharge;
                $totalAssignedGigs = $gigsInCharge->where('status', 'assigned')->count();
                $totalPendingGigs = $gigsInCharge->where('status', 'pending')->count();
                $totalAcceptedGigs = $gigsInCharge->whereIn('status', ['accepted'])->count();
    
                return [
                    'id' => $supervisor->id,
                    'first_name' => $supervisor->first_name,
                    'last_name' => $supervisor->last_name,
                    'other_name' => $supervisor->other_name,
                    'city' => $supervisor->location->city,
                    'location' => $supervisor->address1,
                    'gender' => $supervisor->gender,
                    'passport' => $supervisor->passport,
                    'employee_id' => $supervisor->employee_id,
                    'total_pending_gigs' => $totalPendingGigs,
                    'total_assigned_gigs' => $totalAssignedGigs,
                    'total_accepted_gigs' => $totalAcceptedGigs,
                    'total_gigs' => $totalAcceptedGigs + $totalAssignedGigs + $totalPendingGigs,
                ];
            })
            ->take(7);
    
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'data' => [
                'team_members' => [
                    'dsws' => $dsws,
                    'csps' => $csps,
                    'supervisors' => $supervisor
                ],
                'supervisors' => $supervisors,
                'total_gigs' => [
                    'total' => $totalGigs,
                    'assigned_or_accepted' => $acceptedGigs,
                    'pending' => $pendingGigs
                ],
                'active_dsw_timesheets' => [
                    'dsws' => $activeDSWs,
                    'csps' => $activeCSPs
                ],
                'recent_timesheet_actions' => $recentTimesheetActions,
                'inactive_dsw_timesheets' => [
                    'dsws' => $inactiveDSWs,
                    'csp' => $inactiveCSPs
                ],
                'late_clock_ins' => $lateClockIns,
                'recent_flags' => $recentFlags,
                'total_flags' => $totalFlags
            ]
        ], 200);
}
    
    public function convertToDateTime($weekNumber, $dayOfWeek, $year, $time) {
        // Map day of the week names to numeric values
        $daysOfWeek = [
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
            'Sunday' => 7
        ];
    
        // Get the numeric value for the given day of the week
        if (!isset($daysOfWeek[$dayOfWeek])) {
            throw new InvalidArgumentException('Invalid day of the week.');
        }
    
        $dayOfWeekNumeric = $daysOfWeek[$dayOfWeek];
    
        // Create a Carbon instance for the first day of the year
        $carbonDate = Carbon::createFromFormat('Y-m-d', "$year-01-01");
    
        // Adjust to the first day of the week (Monday)
        $firstMonday = $carbonDate->startOfWeek();
    
        // Calculate the specific date by adding weeks and days
        $specificDate = $firstMonday->addWeeks($weekNumber - 1)->addDays($dayOfWeekNumeric - 1);
    
        // Combine the date with the given time
        $finalDateTime = Carbon::createFromFormat('Y-m-d h:i A', $specificDate->format('Y-m-d') . ' ' . $time);
    
        // Add a random second part
        $finalDateTime->second = rand(0, 59);
    
        // Return the result in the desired format
        return $finalDateTime->format('Y-m-d H:i:s');
    }
    
    private function convertToPastTense($activityType)
    {
        $activityTypes = [
            'clock in' => 'Clocked In',
            'clock out' => 'Clocked Out'
        ];
    
        return $activityTypes[strtolower($activityType)] ?? $activityType;
    }
    
    public function activeUsers()
    {
        $manager = User::find(auth('api')->user()->id);
        $userLocationId = $manager->location_id;

        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }
        
        // 3. Active DSWs/Timesheets with detailed activities for today
        $activeDSWs = User::role('DSW')
    ->whereHas('timesheets', function($query) {
        $query->where('status', 'started')
              ->whereDate('created_at', '>=', now()->subMonth())
              ->where(function ($q) {
                  $q->whereJsonContains('activities', function($activity) {
                      return isset($activity['clock_in_status']) || isset($activity['clock_out_status']);
                  });
              });
    })
    ->with(['timesheets' => function($query) {
        $query->where('status', 'started')
              ->whereDate('created_at', '>=', now()->subMonth())
              ->where(function ($q) {
                  $q->whereJsonContains('activities', function($activity) {
                      return isset($activity['clock_in_status']) || isset($activity['clock_out_status']);
                  });
              });
    }])
    ->get()
    ->map(function($dsw) {
        $activity = $dsw->timesheets->map(function($timesheet) {
            $activities = json_decode($timesheet->activities, true);
            if (is_array($activities)) {
                $filteredActivities = array_filter(array_map(function($activity) {
                    return (object) [
                        'clock_in' => $activity['clock_in'] ?? null,
                        'clock_out' => $activity['clock_out'] ?? null,
                        'duration' => $activity['duration'] ?? null,
                        'clock_in_status' => $activity['clock_in_status'] ?? null,
                        'clock_out_status' => $activity['clock_out_status'] ?? null,
                        'flags' => $activity['flags'] ?? []
                    ];
                }, $activities));

                // Sort activities by the most recent time (clock_in or clock_out)
                usort($filteredActivities, function($a, $b) {
                    $a_time = $a->clock_out ?? $a->clock_in;
                    $b_time = $b->clock_out ?? $b->clock_in;
                    return strtotime($b_time) - strtotime($a_time);
                });

                // Return the most recent activity
                if ($filteredActivities) {
                    $mostRecentActivity = reset($filteredActivities);
                    return (object) [
                        'timesheet_id' => $timesheet->unique_id,
                        'last_activity' => (object) [
                            'title' => $mostRecentActivity->clock_out ? 'Clocked Out' : 'Clocked In',
                            'time' => $mostRecentActivity->clock_out ?? $mostRecentActivity->clock_in,
                            'status' => $mostRecentActivity->clock_out_status ?? $mostRecentActivity->clock_in_status,
                            'flags' => $mostRecentActivity->flags
                        ]
                    ];
                }
            }
            return null;
        })->filter()->first();

        if ($activity) {
            return (object) [
                'id' => $dsw->id,
                'first_name' => $dsw->first_name,
                'last_name' => $dsw->last_name,
                'other_name' => $dsw->other_name,
                'location' => $dsw->address1,
                'gender' => $dsw->gender,
                'city' => $dsw->location->city,
                'passport' => $dsw->passport,
                'employee_id' => $dsw->employee_id,
                'activity' => $activity
            ];
        }
        return null;
    })
    ->filter()
    ->sortByDesc(function($dsw) {
        if (isset($dsw->activity->last_activity->time)) {
            return strtotime($dsw->activity->last_activity->time);
        }
        return 0;
    })
    ->values()
    ->take(300);


            
            
    
    $activeCSPs = User::role('CSP')
    ->whereHas('timesheets', function($query) {
        $query->where('status', 'started')
              ->whereDate('created_at', '>=', now()->subMonth())
              ->where(function ($q) {
                  $q->whereJsonContains('activities', function($activity) {
                      return isset($activity['clock_in_status']) || isset($activity['clock_out_status']);
                  });
              });
    })
    ->with(['timesheets' => function($query) {
        $query->where('status', 'started')
              ->whereDate('created_at', '>=', now()->subMonth())
              ->where(function ($q) {
                  $q->whereJsonContains('activities', function($activity) {
                      return isset($activity['clock_in_status']) || isset($activity['clock_out_status']);
                  });
              });
    }])
    ->get()
    ->map(function($csp) {
        $activity = $csp->timesheets->map(function($timesheet) {
            $activities = json_decode($timesheet->activities, true);
            if (is_array($activities)) {
                $filteredActivities = array_filter(array_map(function($activity) {
                    return (object) [
                        'clock_in' => $activity['clock_in'] ?? null,
                        'clock_out' => $activity['clock_out'] ?? null,
                        'duration' => $activity['duration'] ?? null,
                        'clock_in_status' => $activity['clock_in_status'] ?? null,
                        'clock_out_status' => $activity['clock_out_status'] ?? null,
                        'flags' => $activity['flags'] ?? []
                    ];
                }, $activities));

                // Sort activities to get the most recent one
                usort($filteredActivities, function($a, $b) {
                    $a_time = $a->clock_out ?? $a->clock_in;
                    $b_time = $b->clock_out ?? $b->clock_in;
                    return strtotime($b_time) - strtotime($a_time);
                });

                // Return the most recent activity
                if ($filteredActivities) {
                    $lastActivity = reset($filteredActivities);
                    return (object) [
                        'timesheet_id' => $timesheet->unique_id,
                        'last_activity' => (object) [
                            'title' => $lastActivity->clock_out ? 'Clocked Out' : 'Clocked In',
                            'time' => $lastActivity->clock_out ?? $lastActivity->clock_in,
                            'status' => $lastActivity->clock_out_status ?? $lastActivity->clock_in_status,
                            'flags' => $lastActivity->flags
                        ]
                    ];
                }
            }
            return null;
        })->filter()->first(); // Take the most recent valid activity

        if ($activity) {
            return (object) [
                'id' => $csp->id,
                'first_name' => $csp->first_name,
                'last_name' => $csp->last_name,
                'other_name' => $csp->other_name,
                'location' => $csp->address1,
                'gender' => $csp->gender,
                'city' => $csp->location->city,
                'passport' => $csp->passport,
                'employee_id' => $csp->employee_id,
                'activity' => $activity
            ];
        }
        return null;
    })
    ->filter()
    ->sortByDesc(function($csp) {
        if (isset($csp->activity->last_activity->time)) {
            return strtotime($csp->activity->last_activity->time);
        }
        return 0;
    })
    ->values()
    ->take(300);
        
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'data' => [
                'dsws' => $activeDSWs,
                'csps' => $activeCSPs
            ]
        ], 200);
    }
        
    public function inactiveUsers(Request $request) 
    {
        $manager = User::find(auth('api')->user()->id);
        $userLocationId = $manager->location_id;

        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }

    // Inactive DSW
        $inactiveDSWs = User::role('DSW')
        ->doesntHave('timesheets')
        ->get()
        ->map(function($dsw) {
            return [
                'id' => $dsw->id,
                'first_name' => $dsw->first_name,
                'last_name' => $dsw->last_name,
                'other_name' => $dsw->other_name,
                'location' => $dsw->address1,
                'city' => $dsw->location->city,
                'gender' => $dsw->gender,
                'passport' => $dsw->passport,
                'employee_id' => $dsw->employee_id,
            ];
        })
        ->take(300);
        
        // Inactive CSP
        $inactiveCSPs = User::role('CSP')
        ->doesntHave('timesheets')
        ->get()
        ->map(function($csp) {
            return [
                'id' => $csp->id,
                'first_name' => $csp->first_name,
                'last_name' => $csp->last_name,
                'other_name' => $csp->other_name,
                'location' => $csp->address1,
                'city' => $csp->location->city,
                'gender' => $csp->gender,
                'passport' => $csp->passport,
                'employee_id' => $csp->employee_id,
            ];
        })
        ->take(300);


        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'data' => [
                'dsws' => $inactiveDSWs,
                'csp' => $inactiveCSPs
            ]
        ], 200);
    }    
    
    public function active_inactiveGigs(Request $request) 
    {
        $manager = User::find(auth('api')->user()->id);
        $userLocationId = $manager->location_id;

        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }
        
        $inactiveGigs = Gig::where('status', 'pending')->whereHas('creator')->get()->take(300);
        
        $activeGigs = Gig::where('status', ['assigned', 'accepted'])->whereHas('creator')->get()->take(300);


        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'data' => [
                'inactive_gigs' => $inactiveGigs,
                'active_gigs' => $activeGigs
            ]
        ], 200);
    }
    
    public function flagged_activity(Request $request) 
    {
        $manager = User::find(auth('api')->user()->id);
        $userLocationId = $manager->location_id;

        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }

        // 7. Recent flags from timesheet activities
        $recentFlags = TimeSheet::with(['gigs', 'user','weekLog'])->whereHas('user')->orderBy('updated_at', 'desc')->take(300)->get()->map(function($timesheet) {
                $activities = json_decode($timesheet->activities, true);
                $flags = [];
                if (is_array($activities)) {
                    foreach ($activities as $activity) {
                        if (isset($activity['flags'])) {
                            foreach ($activity['flags'] as $flag) {
                                $flags[] = [
                                    'flag' => $flag,
                                    'gig' => $timesheet->gigs,
                                    'user' => [
                                        'id' => $timesheet->user->id,
                                        'first_name' => $timesheet->user->first_name,
                                        'last_name' => $timesheet->user->last_name,
                                        'other_name' => $timesheet->user->other_name,
                                        'location' => $timesheet->user->location->city,
                                        'address' => $timesheet->user->address1,
                                        'gender' => $timesheet->user->gender,
                                        'passport' => $timesheet->user->passport,
                                        'employee_id' => $timesheet->user->employee_id,
                                    ],
                                ];
                            }
                        }
                    }
                }
                return $flags;
            })->flatten(1);
            
            return response()->json([
                'status' => 200,
                'response' => 'Successful',
                'data' => [
                    'flags' => $recentFlags
                ]
            ], 200);
    }
}
