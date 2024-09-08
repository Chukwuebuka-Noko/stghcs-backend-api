<?php

namespace App\Http\Controllers\Api\Billing;

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

class BillingGigController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware(['role:Billing']);
    }
    /**
     * Display a listing of the resource.
     */

    
    public function index()
    {
    
        // Fetch all gigs created by users with the same location_id and status not 'ended' or 'completed'
        $gigs = Gig::with(['client','schedule','supervisor','assignments.assignee'])
            ->whereHas('creator', function ($query) {
                $query->where('status', 'pending');
            })
            ->whereNotIn('status', ['ended', 'completed'])
            ->latest()
            ->get();
    
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
     * Display the specified resource.
     */
    public function show(Request $request)
    {

        $gig = Gig::where('id', $request->id)->with(['schedule','supervisor','client','assignments.assignee'])->whereHas('creator')->first();
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
    
    public function paginate(Request $request)
    {
        $perPage = $request->input('per_page', 20);
            // Get the authenticated user's location_id
        $userLocationId = auth()->user()->location_id;

        // Fetch all gigs created by users with the same location_id
        $gigs = Gig::with('schedule')
            ->whereHas('creator')->orderBy('created_at', 'desc')->paginate($perPage);
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
