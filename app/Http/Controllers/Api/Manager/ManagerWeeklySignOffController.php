<?php

namespace App\Http\Controllers\Api\Manager;

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

class ManagerWeeklySignOffController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Manager']);
    }
    
    public function all_sign_off(Request $request)
{
    // Get the authenticated manager's location_id
    $managerLocationId = auth('api')->user()->location_id;

    // Fetch WeeklySignOff records where the support worker's location_id matches the manager's location_id
    $signOff = WeeklySignOff::whereHas('user', function($query) use ($managerLocationId) {
        $query->where('location_id', $managerLocationId);
    })->with(['gig.client', 'user'])
      ->get();

    if ($signOff->isEmpty()) {
        return response()->json([
            'status' => 404,
            'response' => 'Not Found',
            'message' => 'Assigned Gig(s) does not exist'
        ], 404);
    }

    return response()->json([
        'status' => 200,
        'response' => 'Sign Off(s) Fetched successfully',
        'data' => $signOff
    ], 200);
}


    public function single_sign_off(Request $request)
{
    // Get the authenticated manager's location_id
    $managerLocationId = auth('api')->user()->location_id;

    // Fetch the single WeeklySignOff record where support_worker's location_id matches the manager's location_id
    $signOff = WeeklySignOff::whereHas('user', function ($query) use ($managerLocationId) {
        $query->where('location_id', $managerLocationId);
    })->where(['id' => $request->sign_off_id
    ])->with(['gig.client', 'user'])->first();

    // Check if the sign off report was found
    if (!$signOff) {
        return response()->json([
            'status' => 404,
            'response' => 'Not Found',
            'message' => 'No matching sign off report found'
        ], 404);
    }

    return response()->json([
        'status' => 200,
        'response' => 'Sign Off Fetched successfully',
        'data' => $signOff
    ], 200);
}

}
