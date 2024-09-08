<?php

namespace App\Http\Controllers\Api\Billing;

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

class BillingWeeklySignOffController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Billing']);
    }
    
    public function all_sign_off(Request $request)
    {
        $signOff = WeeklySignOff::with(['gig.client','user'])->get();
        if ($signOff->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Assigned Gig(s) does not exist'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Sign Off(s) Fetched successfully','data' => $signOff], 200);
    }

    public function single_sign_off(Request $request)
    {
        $signOff = WeeklySignOff::where(['id'=>$request->sign_off_id])->with(['gig.client','user'])->first();
        // Check if the progress report was found
        if (!$signOff) {
            return response()->json(['status' => 404,'response' => 'Not Found','message' => 'No matching progress report found'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Sign Off Fetched successfully','data' => $signOff], 200);
    }
}
