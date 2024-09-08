<?php

namespace App\Http\Controllers\Api\Supervisor;

use Carbon\Carbon;
use App\Models\Gig;
use App\Models\User;
use App\Models\TimeSheet;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Models\ActivityRemark;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\ProgressReport;
use App\Models\WeekLog;
use App\Models\AssignGig;
use App\Models\Schedule;
use App\Models\WeeklySignOff;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class SupervisorDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Supervisor']);
    }
    public function dashboard(Request $request) 
    {
        $supervisor = User::find(auth('api')->user()->id);
        $userLocationId = $supervisor->location_id;

        // Check if the user has the 'Supervisor' role
        if (!$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a Supervisor.'
            ], 403);
        }

        // Fetch users with the same location_id and the roles 'DSW' or 'CSP'
        $dsws = User::where('location_id', $supervisor->location_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['DSW']);
            })
            ->whereHas('supervisor_in_charges', function($query) use ($supervisor) {
                $query->where('supervisor_id', $supervisor->id);
            })
            ->count();
        $csps = User::where('location_id', $supervisor->location_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['CSP']);
            })
            ->whereHas('supervisor_in_charges', function($query) use ($supervisor) {
                $query->where('supervisor_id', $supervisor->id);
            })
            ->count();

        $totalGigs = Gig::whereHas('supervisor', function ($query) use ($userLocationId) {
            $query->where('location_id', $userLocationId);
        })->count();
        $acceptedGigs = Gig::whereIn('status', ['assigned', 'accepted'])->whereHas('supervisor', function ($query) use ($userLocationId) {
            $query->where('location_id', $userLocationId);
        })->count();
        $pendingGigs = Gig::where('status', 'pending')->whereHas('supervisor', function ($query) use ($userLocationId) {
            $query->where('location_id', $userLocationId);
        })->count();
        
        // 3. Active DSWs/Timesheets with detailed activities for today
    /*$activeDSWs = User::role('DSW')
    ->where('location_id', $supervisor->location_id)
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
        return [
            'id' => $dsw->id,
            'first_name' => $dsw->first_name,
            'last_name' => $dsw->last_name,
            'other_name' => $dsw->other_name,
            'location' => $dsw->address1,
            'gender' => $dsw->gender,
            'city' => $dsw->location->city,
            'passport' => $dsw->passport,
            'employee_id' => $dsw->employee_id,
            'activity' => $dsw->timesheets->map(function($timesheet) {
                $activities = json_decode($timesheet->activities, true);
                if (is_array($activities)) {
                    $filteredActivities = array_filter(array_map(function($activity) {
                        return [
                            'clock_in' => $activity['clock_in'] ?? null,
                            'clock_out' => $activity['clock_out'] ?? null,
                            'duration' => $activity['duration'] ?? null,
                            'clock_in_status' => $activity['clock_in_status'] ?? null,
                            'clock_out_status' => $activity['clock_out_status'] ?? null,
                            'flags' => $activity['flags'] ?? []
                        ];
                    }, $activities));

                    usort($filteredActivities, function($a, $b) {
                        return strtotime($b['clock_in']) - strtotime($a['clock_in']);
                    });

                    if ($filteredActivities) {
                        $lastActivity = reset($filteredActivities);
                        if ($lastActivity['clock_out']) {
                            return [
                                'timesheet_id' => $timesheet->unique_id,
                                'last_activity' => [
                                    'title' => 'Clocked Out',
                                    'time' => $lastActivity['clock_out'],
                                    'status' => $lastActivity['clock_out_status'],
                                    'flags' => $lastActivity['flags']
                                ]
                            ];
                        } else {
                            return [
                                'timesheet_id' => $timesheet->unique_id,
                                'last_activity' => [
                                    'title' => 'Clocked In',
                                    'time' => $lastActivity['clock_in'],
                                    'status' => $lastActivity['clock_in_status'],
                                    'flags' => $lastActivity['flags']
                                ]
                            ];
                        }
                    }
                    return null;
                }
                return null;
            })->filter()->values()->take(1)
        ];
    })
    ->filter(function($dsw) {
        return !empty($dsw['activity']);
    })
    ->sortByDesc(function($dsw) {
        if (!empty($dsw['activity']) && isset($dsw['activity'][0]['last_activity']['time'])) {
            return strtotime($dsw['activity'][0]['last_activity']['time']);
        }
        return 0;
    })
    ->values()
    ->take(7);



        $activeCSPs = User::role('CSP')
    ->where('location_id', $supervisor->location_id)
    ->whereHas('supervisor_in_charges', function($query) use ($supervisor) {
        $query->where('supervisor_id', $supervisor->id);
    })
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
        return [
            'id' => $csp->id,
            'first_name' => $csp->first_name,
            'last_name' => $csp->last_name,
            'other_name' => $csp->other_name,
            'location' => $csp->address1,
            'gender' => $csp->gender,
            'city' => $csp->location->city,
            'passport' => $csp->passport,
            'employee_id' => $csp->employee_id,
            'activity' => $csp->timesheets->map(function($timesheet) {
                $activities = json_decode($timesheet->activities, true);
                if (is_array($activities)) {
                    $filteredActivities = array_filter(array_map(function($activity) {
                        return [
                            'clock_in' => $activity['clock_in'] ?? null,
                            'clock_out' => $activity['clock_out'] ?? null,
                            'duration' => $activity['duration'] ?? null,
                            'clock_in_status' => $activity['clock_in_status'] ?? null,
                            'clock_out_status' => $activity['clock_out_status'] ?? null,
                            'flags' => $activity['flags'] ?? []
                        ];
                    }, $activities));

                    usort($filteredActivities, function($a, $b) {
                        return strtotime($b['clock_in']) - strtotime($a['clock_in']);
                    });

                    if ($filteredActivities) {
                        $lastActivity = reset($filteredActivities);
                        if ($lastActivity['clock_out']) {
                            return [
                                'timesheet_id' => $timesheet->unique_id,
                                'last_activity' => [
                                    'title' => 'Clocked Out',
                                    'time' => $lastActivity['clock_out'],
                                    'status' => $lastActivity['clock_out_status'],
                                    'flags' => $lastActivity['flags']
                                ]
                            ];
                        } else {
                            return [
                                'timesheet_id' => $timesheet->unique_id,
                                'last_activity' => [
                                    'title' => 'Clocked In',
                                    'time' => $lastActivity['clock_in'],
                                    'status' => $lastActivity['clock_in_status'],
                                    'flags' => $lastActivity['flags']
                                ]
                            ];
                        }
                    }
                    return null;
                }
                return null;
            })->filter()->values()->take(1)
        ];
    })
    ->filter(function($csp) {
        return !empty($csp['activity']);
    })
    ->sortByDesc(function($csp) {
        if (!empty($csp['activity']) && isset($csp['activity'][0]['last_activity']['time'])) {
            return strtotime($csp['activity'][0]['last_activity']['time']);
        }
        return 0;
    })
    ->values()
    ->take(7);*/
    
    $activeDSWs = User::role('DSW')
    ->where('location_id', $supervisor->location_id)
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


            
            /*$activeCSPs = User::role('CSP')
    ->where('location_id', $supervisor->location_id)
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
        return [
            'id' => $csp->id,
            'first_name' => $csp->first_name,
            'last_name' => $csp->last_name,
            'other_name' => $csp->other_name,
            'location' => $csp->address1,
            'gender' => $csp->gender,
            'city' => $csp->location->city,
            'passport' => $csp->passport,
            'employee_id' => $csp->employee_id,
            'activity' => $csp->timesheets->map(function($timesheet) {
                $activities = json_decode($timesheet->activities, true);
                if (is_array($activities)) {
                    $filteredActivities = array_filter(array_map(function($activity) {
                        return [
                            'clock_in' => $activity['clock_in'] ?? null,
                            'clock_out' => $activity['clock_out'] ?? null,
                            'duration' => $activity['duration'] ?? null,
                            'clock_in_status' => $activity['clock_in_status'] ?? null,
                            'clock_out_status' => $activity['clock_out_status'] ?? null,
                            'flags' => $activity['flags'] ?? []
                        ];
                    }, $activities));

                    usort($filteredActivities, function($a, $b) {
                        return strtotime($b['clock_in']) - strtotime($a['clock_in']);
                    });

                    if ($filteredActivities) {
                        $lastActivity = reset($filteredActivities);
                        if ($lastActivity['clock_out']) {
                            return [
                                'timesheet_id' => $timesheet->unique_id,
                                'last_activity' => [
                                    'title' => 'Clocked Out',
                                    'time' => $lastActivity['clock_out'],
                                    'status' => $lastActivity['clock_out_status'],
                                    'flags' => $lastActivity['flags']
                                ]
                            ];
                        } else {
                            return [
                                'timesheet_id' => $timesheet->unique_id,
                                'last_activity' => [
                                    'title' => 'Clocked In',
                                    'time' => $lastActivity['clock_in'],
                                    'status' => $lastActivity['clock_in_status'],
                                    'flags' => $lastActivity['flags']
                                ]
                            ];
                        }
                    }
                    return null;
                }
                return null;
            })->filter()->values()->take(1)
        ];
    })
    ->filter(function($csp) {
        return !empty($csp['activity']);
    })
    ->sortByDesc(function($csp) {
        if (!empty($csp['activity']) && isset($csp['activity'][0]['last_activity']['time'])) {
            return strtotime($csp['activity'][0]['last_activity']['time']);
        }
        return 0;
    })
    ->values()
    ->take(7);*/
    
    $activeCSPs = User::role('CSP')
    ->where('location_id', $supervisor->location_id)
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
            ->whereHas('user', function($query) use ($supervisor) {
                $query->where('location_id', $supervisor->location_id);
            })
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
    ->where('location_id', $supervisor->location_id)
    ->whereHas('supervisor_in_charges', function($query) use ($supervisor) {
        $query->where('supervisor_id', $supervisor->id);
    })
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
    });



    
        $inactiveCSPs = User::role('CSP')
            ->where('location_id', $supervisor->location_id)
            ->whereHas('supervisor_in_charges', function($query) use ($supervisor) {
                $query->where('supervisor_id', $supervisor->id);
            })
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
            ->whereHas('user', function($query) use ($supervisor) {
                $query->where('location_id', $supervisor->location_id);
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
            ->whereHas('user', function($query) use ($supervisor) {
                $query->where('location_id', $supervisor->location_id);
            })->orderBy('updated_at', 'desc')->get()->map(function($timesheet) {
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
        ->whereHas('user', function($query) use ($supervisor) {
            $query->where('location_id', $supervisor->location_id);
        })->orderBy('updated_at', 'desc')->take(10)->get()->map(function($timesheet) {
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
        
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'data' => [
                'team_members' => [
                    'dsws' => $dsws,
                    'csps' => $csps,
                ],
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
    
    public function remark(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id'],
            'gig_id' => ['required', 'exists:gigs,id'],
            'timesheet_id' => ['required', 'exists:time_sheets,unique_id'],
            'activity_id' => ['required'],
            'remark' => ['required'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }
        
        $remark = ActivityRemark::create([
            'user_id' => $request->user_id,
            'gig_id' => $request->gig_id,
            'timesheet_id' => $request->timesheet_id,
            'activity_id' => $request->activity_id,
            'remark' => $request->remark,
        ]);

        $user = User::find($request->id);
        $fullname = $user->first_name.' '.$user->last_name;

        ActivityLog::create([
            'action' => 'Created New User',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created a new remark for '.$fullname.' at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $remark->id,
            'subject_type' => get_class($remark),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>201,'response'=>'Task Created','message'=>'Task created successfully','data'=>$remark], 201);
    }
    
    public function convertToDateTime($weekNumber, $dayOfWeek, $year, $time) 
    {
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
        $supervisor = User::find(auth('api')->user()->id);
        $userLocationId = $supervisor->location_id;

        // Check if the user has the 'manager' role
        if (!$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }
        
        // 3. Active DSWs/Timesheets with detailed activities for today
        $activeDSWs = User::role('DSW')
    ->where('location_id', $supervisor->location_id)
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
    ->where('location_id', $supervisor->location_id)
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
        $supervisor = User::find(auth('api')->user()->id);
        $userLocationId = $supervisor->location_id;

        // Check if the user has the 'manager' role
        if (!$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }

    // Inactive DSW
        $inactiveDSWs = User::role('DSW')
        ->where('location_id', $supervisor->location_id)
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
        ->where('location_id', $supervisor->location_id)
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
        $supervisor = User::find(auth('api')->user()->id);
        $userLocationId = $supervisor->location_id;

        // Check if the user has the 'manager' role
        if (!$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }
        
        $inactiveGigs = Gig::where('status', 'pending')->whereHas('creator', function ($query) use ($userLocationId) {
            $query->where('location_id', $userLocationId);
        })->get()->take(300);
        
        $activeGigs = Gig::where('status', ['assigned', 'accepted'])->whereHas('creator', function ($query) use ($userLocationId) {
            $query->where('location_id', $userLocationId);
        })->get()->take(300);


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
        $supervisor = User::find(auth('api')->user()->id);
        $userLocationId = $supervisor->location_id;

        // Check if the user has the 'manager' role
        if (!$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }

        // 7. Recent flags from timesheet activities
        $recentFlags = TimeSheet::with(['gigs', 'user','weekLog'])->whereHas('user', function($query) use ($supervisor) {
                $query->where('location_id', $supervisor->location_id);
            })->orderBy('updated_at', 'desc')->take(300)->get()->map(function($timesheet) {
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
    
    public function progress_report(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required'],
            'description' => ['required'],
            'progress_time' => ['required'],
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

        // Create a new progress report with the validated data
        $progressReport = ProgressReport::create([
            'title' => $request->title,
            'description' => $request->description,
            'progress_time' => $request->progress_time,
            'progress_date' => Carbon::parse($request->progress_time)->format('m-d-Y'),
            'progress_week_number' => $now->weekOfYear,
            'progress_year' => Carbon::parse($request->progress_time)->format('Y'),
            'timesheet_id' => $timesheet->unique_id,
            'activity_id' => $lastActivityId,
            'gig_id' => $request->gig_id,
            'support_worker_id' => auth('api')->user()->id
        ]);
        WeekLog::create([
            'title'=> auth('api')->user()->last_name." ".auth('api')->user()->first_name." reported a progress",
            'week_number' => $now->weekOfYear,
            'year' => $now->year,
            'day' => $now->format('l'),
            'time' => $now->format('h:i A'),
            'user_id' => auth('api')->user()->id,
            'type' => "Progress Reported",
            'timesheet_id' => $timesheet->unique_id,
            'activity_id' => $lastActivityId
        ]);
        return response()->json(['status' => 200,'response' => 'Progress reported successfully','data' => $progressReport], 200);
    }
    
    public function all_reported_progress(Request $request)
    {
        $progressReport = ProgressReport::where([
            'support_worker_id' => auth('api')->user()->id
        ])->with(['gig.client','user'])->get();
        if ($progressReport->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Assigned Gig(s) does not exist'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Progress reported successfully','data' => $progressReport], 200);
    }

    public function single_reported_progress(Request $request)
    {
        $progressReport = ProgressReport::where(['support_worker_id' => auth('api')->user()->id,'id'=>$request->progress_report_id])->with(['gig.client','user'])->first();
        // Check if the progress report was found
        if (!$progressReport) {
            return response()->json(['status' => 404,'response' => 'Not Found','message' => 'No matching progress report found'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Progress reported successfully','data' => $progressReport], 200);
    }
    
    public function sign_off(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sign_off_time' => ['required'],
            'gig_id' => ['required', 'exists:gigs,id'],
            'support_worker_signature' => ['required', 'file', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'client_signature' => ['required', 'file', 'mimes:jpeg,png,jpg,gif', 'max:2048']
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
        $supportWorkerSignaturePath = $request->file('support_worker_signature')->getRealPath();
        $supportWorkerSignatureUpload = Cloudinary::upload($supportWorkerSignaturePath, [
            'folder' => 'signatures/support_worker'
        ]);
        $supportWorkerSignatureUrl = $supportWorkerSignatureUpload->getSecurePath();
    
        // Upload client_signature to Cloudinary
        $clientSignaturePath = $request->file('client_signature')->getRealPath();
        $clientSignatureUpload = Cloudinary::upload($clientSignaturePath, [
            'folder' => 'signatures/client'
        ]);
        $clientSignatureUrl = $clientSignatureUpload->getSecurePath();

        // Create a new sign_off report with the validated data
        $sign_offReport = WeeklySignOff::create([
            'sign_off_time' => $request->sign_off_time,
            'sign_off_date' => Carbon::parse($request->sign_off_time)->format('m-d-Y'),
            'sign_off_week_number' => $now->weekOfYear,
            'sign_off_year' => Carbon::parse($request->sign_off_time)->format('Y'),
            'sign_off_day' => Carbon::parse($request->sign_off_time)->format('l'),
            'timesheet_id' => $timesheet->unique_id,
            'client_signature' => $clientSignatureUrl,
            'support_worker_signature' => $supportWorkerSignatureUrl,
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
