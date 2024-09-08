<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\Gig;
use App\Models\GigType;
use App\Models\ActivityLog;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class GigTypeController extends Controller
{
    public function index()
    {
        $gig_types = GigType::all();
        if ($gig_types->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Gig type(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View Gig types',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all Gig types at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($gig_types),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Gig types fetched successfully","data"=>$gig_types],200);
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'shortcode' => 'required|string|max:255'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $shortcode = Str::upper($request->shortcode);

        $gig_type = GigType::create([
            'title' => $request->title,
            'shortcode'=> $shortcode
        ]);
        ActivityLog::create([
            'action' => 'Created New Gig type',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new gig type at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $gig_type->id,
            'subject_type' => get_class($gig_type),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>201,'response'=>'Gig type Created','message'=>'Gig type created successfully','data'=>$gig_type], 201);
    }
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'shortcode' => 'required|string|max:255'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $shortcode = Str::upper($request->shortcode);
        
        $gig_type = GigType::find($request->id);
        if (!$gig_type) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $gig_type->update([
            'title' => $request->title,
            'shortcode'=> $shortcode
        ]);
        ActivityLog::create([
            'action' => 'Updated Gig types',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated a gig type at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $gig_type->id,
            'subject_type' => get_class($gig_type),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Gig type Updated','message'=>'Gig type updated successfully','data'=>$gig_type], 201);
    }

    public function destroy(Request $request)
    {
        $gig_type = GigType::find($request->id);
        if (!$gig_type) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $gig_type->delete();
        ActivityLog::create([
            'action' => 'Deleted A Gig types',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a gig type at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($gig_type),
            'subject_id'=> $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>204,'response'=>'No Content','message' => 'Gig type Deleted successfully']);
    }
}
