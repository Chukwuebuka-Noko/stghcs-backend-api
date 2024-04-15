<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\Gig;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class GigsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $gigs = Gig::get();
        if ($gigs->isEmpty()) {
            return response()->json(['message'=>'Gig(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View All Users',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all users at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($gigs),
            'user_id' => auth()->id(),
        ]);
        return response()->json(["message"=>"Users fetched successfully","data"=>$gigs],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required','string'],
            'description' => ['required'],
            'client_id' => ['required', 'exists:clients,id'],
            'created_by' => ['required', 'exists:users,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $gig = Gig::create([
            'title' => $request->title,
            'description' => $request->description,
            'client_id' => $request->client_id,
            'created_by' => $request->created_by
        ]);
        ActivityLog::create([
            'action' => 'Created New gig',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new gig at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $gig->id,
            'subject_type' => get_class($gig),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['message'=>'Gig created successfully','data'=>$gig], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $gig = Gig::where('id', $request->id)->first();
        if (!$gig) {
            return response()->json(['message'=>'Gig does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View A Gig Details',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a gig details at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $gig->id,
            'subject_type' => get_class($gig),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['message'=>'Gig successfully fetched', 'data'=>$gig], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required','string'],
            'description' => ['required'],
            'client_id' => ['required', 'exists:clients,id'],
            'created_by' => ['required', 'exists:users,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $gig = Gig::find($request->id);
        $gig->update([
            'title' => $request->title,
            'description' => $request->description,
            'client_id' => $request->client_id,
            'created_by' => $request->created_by
        ]);
        ActivityLog::create([
            'action' => 'Updated Gig',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated a gig at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $gig->id,
            'subject_type' => get_class($gig),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['message'=>'Gig updated successfully','data'=>$gig], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $gig = Gig::find($request->id);
        if (!$gig) {
            return response()->json(['message' => 'Not Found!'], 404);
        }
        $gig->delete();
        ActivityLog::create([
            'action' => 'Deleted A Gig',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a gig at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($gig),
            'subject_id' => $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['message' => 'Gig Deleted successfully']);
    }
}
