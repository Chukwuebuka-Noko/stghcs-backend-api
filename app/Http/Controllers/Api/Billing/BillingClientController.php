<?php

namespace App\Http\Controllers\Api\Billing;

use Carbon\Carbon;
use App\Models\Client;
use App\Models\ActivityLog;
use App\Rules\ValidZipCode;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\PlanOfCare;

class BillingClientController extends Controller
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
        $manager = auth()->user();
        $clients = Client::where(['status' => 'active'])->with(['created_by'])->orderBy('created_at', 'desc')->get();
        /*if ($clients->isEmpty()) {
            return response()->json(['status'=>200,'response'=>'Not Found','message'=>'Client(s) does not exist', 'data' => $clients], 200);
        }*/
        ActivityLog::create([
            'action' => 'View All Clients',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all clients at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($clients),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Client fetched successfully","data"=>$clients],200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $manager = auth()->user();
        
        $client = Client::where(['id'=> $request->id])->with(['created_by'])->first();
        if (!$client) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Client does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View A Client Details',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a client details at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $client->id,
            'subject_type' => get_class($client),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful','message'=>'Client successfully fetched', 'data'=>$client], 200);
    }
    
    public function search(Request $request)
    {
        $manager = auth()->user();
        
        $searchTerm = $request->input('query');
        $clients = Client::query()
            ->where('first_name', 'LIKE', "%{$searchTerm}%")
            ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
            ->orWhere('other_name', 'LIKE', "%{$searchTerm}%")
            ->orWhere('address1', 'LIKE', "%{$searchTerm}%")
            ->orWhere('city', 'LIKE', "%{$searchTerm}%")
            ->orWhere('email', 'LIKE', "%{$searchTerm}%")
            ->orWhere('phone_number', 'LIKE', "%{$searchTerm}%")->with(['created_by'])->orderBy('created_at', 'desc')->get();
        /*if ($clients->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Client(s) does not exist'], 404);
        }*/
        ActivityLog::create([
            'action' => 'View All Clients',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all clients at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($clients),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Client fetched successfully","data"=>$clients],200);
    }
    
    public function paginate(Request $request)
    {
        $manager = auth()->user();
        $locationId = $manager->location_id;
    
        $searchTerm = $request->input('query');
        $perPage = $request->input('per_page', 20);
    
        // Fetch clients that share the same location_id with the manager
        $clients = Client::with(['created_by']) // Eager load relationships
            ->orderBy('created_at', 'desc') // Order by creation date
            ->paginate($perPage); // Paginate the results
    
        ActivityLog::create([
            'action' => 'View All Clients',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all clients at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($clients),
            'user_id' => auth()->id(),
        ]);
    
        return response()->json(['status' => 200, 'response' => 'Successful', "message" => "Clients fetched successfully", "data" => $clients], 200);
    }
    
    public function plan_of_care(Request $request)
    {
        $manager = auth()->user();
        
        $plans = PlanOfCare::where(['client_id'=> $request->client_id])->select('id','client_id', 'review_date', 'needed_support', 'anticipated_outcome', 'services_area_frequency')->get();
        
        return response()->json(['status'=>200,'response'=>'Successful','message'=>'Client plan of care successfully fetched', 'data'=>$plans], 200);
    }

}
