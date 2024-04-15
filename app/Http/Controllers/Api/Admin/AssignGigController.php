<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\AssignGig;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class AssignGigController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $assign_gig =  AssignGig::with('user')->with('gig')->with('schedule')->get();
        if ($assign_gig->isEmpty()) {
            return response()->json(['message'=>'Assigned Gig(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View All gigs assign',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all gigs assign at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($assign_gig ),
            'user_id' => auth()->id(),
        ]);
        return response()->json(["message"=>"All Assigned gig(s) fetched successfully","data"=>$assign_gig ],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_id' => ['required','exists:gigs,id'],
            'user_id' => ['required', 'exists:users,id'],
            'schedule_id' => ['required', 'exists:schedules,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $assign_gig = AssignGig::create([
            'gig_id' => $request->gig_id,
            'user_id' => $request->user_id,
            'schedule_id' => $request->schedule_id,
        ]);

        $assigned_gig = AssignGig::where('id', $assign_gig->id)->with('user')->with('gig')->with('schedule')->first();
        if (!$assigned_gig) {
            return response()->json(['message' => 'Assigned gig not found'], 404);
        }
        ActivityLog::create([
            'action' => 'Gig has been assigned to '.$user->last_name.' '.$user->first_name,
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' assigned gig to '.$user->last_name.' '.$user->first_name.' at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $assign_gig->id,
            'subject_type' => get_class($assign_gig),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['message'=>'Gig assigned successfully','data'=>[$assigned_gig]], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $assign_gig =  AssignGig::find($request->id)->with('user')->with('gig')->with('schedule')->first();
        if (!$assign_gig) {
            return response()->json(['message'=>'Assigned Gig not found'], 404);
        }
        $assigned_user = $assign_gig->user_id;
        $user = User::find($assigned_user);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        ActivityLog::create([
            'action' => 'View gigs assigned to '.$user->last_name.' '.$user->first_name,
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed gig assigned to '.$user->last_name.' '.$user->first_name.' at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($assign_gig ),
            'user_id' => auth()->id(),
        ]);
        return response()->json(["message"=>"Gig assigned to ".$user->last_name." ".$user->first_name." fetched successfully","data"=>$assign_gig ],200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_id' => ['required','exists:gigs,id'],
            'user_id' => ['required', 'exists:users,id'],
            'schedule_id' => ['required', 'exists:schedules,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $assign_gig = AssignGig::find($request->id);

        $assign_gig->update([
            'gig_id' => $request->gig_id,
            'user_id' => $request->user_id,
            'schedule_id' => $request->schedule_id,
        ]);

        $assigned_gig = AssignGig::where('id', $assign_gig->id)->with('user')->with('gig')->with('schedule')->first();
        if (!$assigned_gig) {
            return response()->json(['message' => 'Assigned gig not found'], 404);
        }
        ActivityLog::create([
            'action' => 'Gig has been assigned to '.$user->last_name.' '.$user->first_name.' was updated',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated gig assigned to '.$user->last_name.' '.$user->first_name.' at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $assign_gig->id,
            'subject_type' => get_class($assign_gig),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['message'=>'Assigned Gig updated successfully','data'=>[$assigned_gig]], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $gig = AssignGig::find($request->id);
        if (!$gig) {
            return response()->json(['message' => 'Not Found!'], 404);
        }
        $gig->delete();
        ActivityLog::create([
            'action' => 'Deleted Assigned Gig',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted assigned gig at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($gig),
            'subject_id' => $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['message' => 'Assigned Gig Deleted successfully']);
    }
}
