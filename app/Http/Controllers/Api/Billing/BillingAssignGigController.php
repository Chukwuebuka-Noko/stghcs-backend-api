<?php

namespace App\Http\Controllers\Api\Billing;

use Carbon\Carbon;
use App\Models\Gig;
use App\Models\User;
use App\Models\Schedule;
use App\Models\AssignGig;
use App\Models\TimeSheet;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class BillingAssignGigController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth:api');
        $this->middleware(['role:Billing']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        // Fetch all assigned gigs where the assignee has the same location_id
        $assign_gig = AssignGig::with(['assignee' => function($query) {
            $query->select('id', 'first_name', 'last_name', 'other_name', 'email', 'phone_number', 'location_id', 'gender', 'id_card', 'address1', 'address2', 'city', 'zip_code', 'dob', 'employee_id', 'points', 'email_verified_at', 'is_temporary_password', 'status', 'created_at', 'updated_at', 'deleted_at');
        }, 'gig.client','gig.supervisor', 'schedule'])
        ->whereHas('assignee')
        ->get();
        
        ActivityLog::create([
            'action' => 'View All gigs assign',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all gigs assign at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($assign_gig ),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"All Assigned gig(s) fetched successfully","data"=>$assign_gig ],200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        // Get the authenticated user's location_id
        $userLocationId = auth()->user()->location_id;
    
        // Fetch the first assigned gig where the assignee has the same location_id
        $assign_gig = AssignGig::with(['assignee' => function($query) {
            $query->select('id', 'first_name', 'last_name', 'other_name', 'email', 'phone_number', 'location_id', 'gender', 'id_card', 'address1', 'address2', 'city', 'zip_code', 'dob', 'employee_id', 'points', 'email_verified_at', 'is_temporary_password', 'status', 'created_at', 'updated_at', 'deleted_at');
        }, 'gig.client', 'schedule'])
        ->whereHas('assignee')
        ->first();
    
        if (is_null($assign_gig)) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Assigned Gig does not exist'], 404);
        }
    
        ActivityLog::create([
            'action' => 'View Assigned gig',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed an assigned gig at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => AssignGig::class,
            'user_id' => auth()->id(),
        ]);
    
        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Assigned gig fetched successfully', 'data' => $assign_gig], 200);
    }
}
