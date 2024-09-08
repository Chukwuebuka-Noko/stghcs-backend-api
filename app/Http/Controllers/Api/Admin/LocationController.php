<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\Location;
use App\Models\ActivityLog;
use App\Rules\ValidZipCode;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $locations = Location::get();
        if ($locations->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Location(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View Locations',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all locations at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($locations),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Locations fetched successfully","data"=>$locations],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'city' => [
                'required',
                'string',
                'unique:locations,city'
            ],
            'zip_code' => ['required','string', 'regex:/^\d{5}(-\d{4})?$/', new ValidZipCode],
            'address1' => ['required','string'],
            'address2' => ['nullable','string'],
            'coordinate' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $coordinate = json_encode($request->coordinate);
        
        $location = Location::create([
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'coordinate' => $coordinate
        ]);
        ActivityLog::create([
            'action' => 'Created New location',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new location at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $location->id,
            'subject_type' => get_class($location),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>201,'response'=>'Location Created','message'=>'Location created successfully','data'=>$location], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Location $location)
    {
        $validator = Validator::make($request->all(), [
            'city' => [
                'required',
                'string',
                'unique:locations,city,'.$request->id
            ],
            'zip_code' => ['required','string', 'regex:/^\d{5}(-\d{4})?$/', new ValidZipCode],
            'address1' => ['required','string'],
            'address2' => ['nullable','string'],
            'coordinate' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $location = Location::find($request->id);
        if (!$location) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }

        $coordinate = json_encode($request->coordinate);

        $location->update([
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'coordinate' => $coordinate
        ]);
        ActivityLog::create([
            'action' => 'Updated location',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated a location at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $location->id,
            'subject_type' => get_class($location),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Location Updated','message'=>'Role updated successfully','data'=>$location], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $location = Location::find($request->id);
        if (!$location) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $location->delete();
        ActivityLog::create([
            'action' => 'Deleted A Location',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a location at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($location),
            'subject_id' => $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>204,'response'=>'No Content','message' => 'Location Deleted successfully']);
    }
}
