<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TimesheetExport;
use App\Exports\UserTimesheetExport;

class ExportsController extends Controller
{
    public function exportTimesheet(Request $request)
    {
        $timesheetId = $request->timesheet_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
    
        if (is_null($timesheetId)) {
            return response()->json([
                'status' => 400,
                'message' => 'Timesheet ID is required'
            ], 400);
        }
    
        return Excel::download(new TimesheetExport($timesheetId,$start_date,$end_date), 'timesheet.csv');
    }
    
    public function exportUserTimesheet(Request $request)
    {
        $userId = $request->user_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
    
        if (is_null($userId)) {
            return response()->json([
                'status' => 400,
                'message' => 'User is required'
            ], 400);
        }
    
        return Excel::download(new UserTimesheetExport($userId,$start_date,$end_date), 'user_timesheet.csv');
    }
}
