<?php

namespace App\Http\Controllers\Api\Manager;

use Carbon\Carbon;
use App\Models\Client;
use App\Models\ActivityLog;
use App\Rules\ValidZipCode;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\PlanOfCare;

class ManagerClientController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware(['role:Manager']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $manager = auth()->user();
        $city = $manager->city;
        if(is_null($city)){
            return response()->json(['status'=>409,'response'=>'Conflict','message'=>'Manager city is Undefined'], 409);
        }
        $clients = Client::where(['location_id' => auth()->user()->location_id, 'status' => 'active'])->with(['created_by'])->orderBy('created_at', 'desc')->get();
        if ($clients->isEmpty()) {
            return response()->json(['status'=>200,'response'=>'Not Found','message'=>'Client(s) does not exist', 'data' => $clients], 200);
        }
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['nullable','string'],
            'first_name' => ['required','string'],
            'last_name' => ['required','string'],        
            'other_name' => ['nullable','string'],
            'dob' => ['nullable','string'],
            'email' => ['nullable','string'],
            // 'email' => ['required','string','unique:clients,email'],
            // 'phone_number' => ['required','string','regex:/^\(?[0-9]{3}\)?[-. ]?[0-9]{3}[-. ]?[0-9]{4}$/'],
            'phone_number' => ['required','string'],
            'lat' => ['required'],
            'long' => ['required'],
            'city' => ['required','string'],
            // 'zip_code' => ['required','string', 'regex:/^\d{5}(-\d{4})?$/', new ValidZipCode],
            'zip_code' => ['nullable','string'],
            'address1' => ['required','string'],
            'address2' => ['nullable','string'],
            'plan_of_care' => ['nullable'],
            'plan_of_care.*.needed_support' => 'nullable|string',
            'plan_of_care.*.anticipated_outcome' => 'nullable|string',
            'plan_of_care.*.services_area_frequency' => 'nullable|string',
            'plan_of_care.*.review_date' => 'nullable',
        ]);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
        }
        
        $coord = [
            'lat' =>$request->lat,
            'long' => $request->long
            ];
    
        $coordinate = json_encode($coord);
        
        // Convert the plan_of_care array to a JSON string
        /*$plan_of_care = null;
        if ($request->has('plan_of_care')) {
            $plan_of_care = json_encode($request->plan_of_care);
        }*/
        
        /*$fileNameToStore ='';
        
        
        if($request->hasFile('plan_of_care')){
            $file = $request->file('plan_of_care');
            $folder = 'stghcs/plan_of_care';
            $uploadedFile = cloudinary()->upload($file->getRealPath(), [
                'folder' => $folder
            ]);
    
            $fileNameToStore = $uploadedFile->getSecurePath();
        }*/
    
        $client = Client::create([
            'title' => $request->title,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'other_name' => $request->other_name,
            'email' => $request->email,
            'dob' => $request->dob,
            'phone_number' => $request->phone_number,
            'created_by' => auth('api')->user()->id,
            'location_id' => auth('api')->user()->location_id,
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'coordinate' => $coordinate,
            'plan_of_care' => 'false'
        ]);
        
        if($request->has('plan_of_care'))
        {
            foreach ($request->plan_of_care as $data) {
                PlanOfCare::create(array_merge($data, ['client_id' => $client->id]));
            }
            
            $client->update(['plan_of_care' => 'true']); // Ensure 'plan_of_care' field exists in 'clients' table
        }
    
        /*ActivityLog::create([
            'action' => 'Created New client',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new client at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $client->id,
            'subject_type' => get_class($client),
            'user_id' => auth()->id(),
        ]);*/
    
        return response()->json(['status'=>201,'response'=>'Client Created','message'=>'Client created successfully','data'=>$client], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $manager = auth()->user();
        $city = $manager->city;
        if(is_null($city)){
            return response()->json(['status'=>409,'response'=>'Conflict','message'=>'Manager city is Undefined'], 409);
        }
        $client = Client::where(['id'=> $request->id, 'location_id' => auth()->user()->location_id])->with(['created_by'])->first();
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
    /**
     * Update the specified resource in storage.
     */

public function update(Request $request)
{
    $validator = Validator::make($request->all(), [
        'title' => ['nullable', 'string'],
        'first_name' => ['required', 'string'],
        'last_name' => ['required', 'string'],
        'other_name' => ['nullable', 'string'],
        'dob' => ['nullable', 'string'],
        'email' => ['nullable', 'string'],
        'phone_number' => ['required', 'string'],
        'lat' => ['required'],
        'long' => ['required'],
        'city' => ['required', 'string'],
        'zip_code' => ['nullable', 'string'],
        'address1' => ['required', 'string'],
        'address2' => ['nullable', 'string']
    ]);

    if ($validator->fails()) {
        $errors = $validator->errors()->all();
        return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
    }

    try {
        $client = Client::findOrFail($request->id);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Client not found'], 404);
    }
    
    // Dynamically build the data array based on the request inputs
    $data = [];

    if ($request->has('title')) {
        $data['title'] = $request->title;
    }

    if ($request->has('first_name')) {
        $data['first_name'] = $request->first_name;
    }

    if ($request->has('last_name')) {
        $data['last_name'] = $request->last_name;
    }

    if ($request->has('other_name')) {
        $data['other_name'] = $request->other_name;
    }

    if ($request->has('dob')) {
        $data['dob'] = $request->dob;
    }

    if ($request->has('email')) {
        $data['email'] = $request->email;
    }

    if ($request->has('phone_number')) {
        $data['phone_number'] = $request->phone_number;
    }

    if ($request->has('lat') && $request->has('long')) {
        $data['coordinate'] = json_encode([
            'lat' => $request->lat,
            'long' => $request->long
        ]);
    }

    if ($request->has('city')) {
        $data['city'] = $request->city;
    }

    if ($request->has('zip_code')) {
        $data['zip_code'] = $request->zip_code;
    }

    if ($request->has('address1')) {
        $data['address1'] = $request->address1;
    }

    if ($request->has('address2')) {
        $data['address2'] = $request->address2;
    }

    /*if ($request->has('plan_of_care')) {
        //$data['plan_of_care'] = json_encode($request->plan_of_care);
        if($request->hasFile('plan_of_care')){
            $file = $request->file('plan_of_care');
            $folder = 'stghcs/plan_of_care';
            $uploadedFile = cloudinary()->upload($file->getRealPath(), [
                'folder' => $folder
            ]);
    
            $fileNameToStore = $uploadedFile->getSecurePath();
            $data['plan_of_care'] = $fileNameToStore;
        }
    }*/

    // Only update if there are changes
    if (!empty($data)) {
        $client->update($data);

        ActivityLog::create([
            'action' => 'Updated Client',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' updated a client at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $client->id,
            'subject_type' => get_class($client),
            'user_id' => auth()->id(),
        ]);
    }

    return response()->json(['status' => 200, 'response' => 'Client Updated', 'message' => 'Client updated successfully', 'data' => $client], 200);
}



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $client = Client::find($request->id);
        if (!$client) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $client->delete();
        ActivityLog::create([
            'action' => 'Deleted A Client',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a client at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($client),
            'subject_id' => $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>204,'response'=>'No Content','message' => 'Client Deleted successfully']);
    }
    /*public function archive_unarchive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:Archive,Unarchive,archive,unarchive'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>422, 'response'=>'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        $client = Client::find($request->id);
        if (!$client) {
            return response()->json(['status'=>404, 'response'=>'Not Found', 'message' => 'Not Found!'], 404);
        }

        $status = ($request->type == "Archive" || $request->type == 'archive') ? "inactive" : "active";
        $type = ($request->type == "Archive" || $request->type == 'archive') ? "archived" : "unarchived";

        try {
            $client->update(['status' => $status]);
        } catch (\Exception $e) {
            return response()->json(['status'=>500, 'response'=>'Internal Server Error', 'message' => 'Failed to update client'], 500);
        }

        // Optional: Return client data to verify update
        return response()->json(['status'=>200, 'response'=>'Successful', 'message' => 'Client ' . $type . ' successfully', 'client' => $client]);
    }*/
    
    public function archive_unarchive(Request $request)
    {
        // Validating the 'type' which is expected to be either in the body or as a query parameter
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:Archive,Unarchive,archive,unarchive'],
        ]);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
        }
    
        // Getting the client ID from the query parameters
        $clientId = $request->query('id');
        if (empty($clientId)) {
            return response()->json(['status' => 400, 'response' => 'Bad Request', 'message' => 'Client ID is required'], 400);
        }
    
        $client = Client::find($clientId);
        if (!$client) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Client not found'], 404);
        }
    
        $typeLower = strtolower($request->type);
        $status = ($typeLower == "archive") ? "inactive" : "active";
        $type = ($typeLower == "archive") ? "archived" : "unarchived";
    
        try {
            $client->update(['status' => $status]);
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'response' => 'Internal Server Error', 'message' => 'Failed to update client'], 500);
        }
    
        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Client ' . $type . ' successfully', 'client' => $client]);
}

    public function multiple_archive_unarchive(Request $request)
    {
        // Validating the 'type' which is expected to be either in the body or as a query parameter
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:Archive,Unarchive,archive,unarchive'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:clients,id']
        ]);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);;
        }
    
        // Getting the client IDs from the body
        $clientIds = $request->input('ids');
    
        $typeLower = strtolower($request->input('type'));
        $status = ($typeLower == "archive") ? "inactive" : "active";
        $type = ($typeLower == "archive") ? "archived" : "unarchived";
    
        try {
            $clients = Client::whereIn('id', $clientIds)->get();
            if ($clients->isEmpty()) {
                return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Clients not found'], 404);
            }
    
            foreach ($clients as $client) {
                $client->update(['status' => $status]);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'response' => 'Internal Server Error', 'message' => 'Failed to update clients'], 500);
        }
    
        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Clients ' . $type . ' successfully', 'clients' => $clients]);
    }

    
    public function search(Request $request)
    {
        $manager = auth()->user();
        $city = $manager->city;
        if(is_null($city)){
            return response()->json(['status'=>409,'response'=>'Conflict','message'=>'Manager city is Undefined'], 409);
        }
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
    
        if (is_null($locationId)) {
            return response()->json(['status' => 409, 'response' => 'Conflict', 'message' => 'Manager location is undefined'], 409);
        }
    
        $searchTerm = $request->input('query');
        $perPage = $request->input('per_page', 20);
    
        // Fetch clients that share the same location_id with the manager
        $clients = Client::with(['created_by']) // Eager load relationships
            ->where('location_id', $locationId) // Filter by location_id
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
        $city = $manager->city;
        if(is_null($city)){
            return response()->json(['status'=>409,'response'=>'Conflict','message'=>'Manager city is Undefined'], 409);
        }
        $plans = PlanOfCare::where(['client_id'=> $request->client_id,'status' =>'active'])->select('id','client_id', 'review_date', 'needed_support', 'anticipated_outcome', 'services_area_frequency')->get();
        /*if (!$plans) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Client does not exist'], 404);
        }*/
        /*ActivityLog::create([
            'action' => 'View A Client Plan of Care',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a client details at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $client->id,
            'subject_type' => get_class($client),
            'user_id' => auth()->id(),
        ]);*/
        return response()->json(['status'=>200,'response'=>'Successful','message'=>'Client plan of care successfully fetched', 'data'=>$plans], 200);
    }
    
    public function create_plan_of_care(Request $request)
    {
        $validator = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'plan_of_care.*.needed_support' => 'nullable|string',
            'plan_of_care.*.anticipated_outcome' => 'nullable|string',
            'plan_of_care.*.services_area_frequency' => 'nullable|string',
            'plan_of_care.*.review_date' => 'nullable',
        ]);
        
        /*if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);;
        }*/

        $client_id = $validator['client_id'];
        $plan_of_care_data = $validator['plan_of_care'];

        foreach ($plan_of_care_data as $data) {
            PlanOfCare::create(array_merge($data, ['client_id' => $client_id]));
        }
        
        $client = Client::find($client_id);

    // Check if client exists
    if (!$client) {
        return response()->json([
            'status' => 404,
            'response' => 'Not Found',
            'message' => 'Client not found'
        ], 404);
    }

    // Update client record
    try {
        $client->update(['plan_of_care' => 'true']); // Ensure 'plan_of_care' field exists in 'clients' table
    } catch (\Exception $e) {
        return response()->json([
            'status' => 500,
            'response' => 'Internal Server Error',
            'message' => 'Failed to update client: ' . $e->getMessage()
        ], 500);
    }
        $plans = PlanOfCare::where(['client_id'=> $request->client_id])->select('id','client_id', 'review_date', 'needed_support', 'anticipated_outcome', 'services_area_frequency')->get();

        return response()->json(['status' => 200, 'response' => 'Successful','message' => 'Plan of care updated successfully!', 'data'=>$plans], 200);
    }
    
    public function replace_plan_of_care(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => ['required', 'exists:clients,id'],
            'plan_of_care_id' => ['required'],
            'plan_of_care' => ['nullable'],
            'plan_of_care.*.needed_support' => 'nullable|string',
            'plan_of_care.*.anticipated_outcome' => 'nullable|string',
            'plan_of_care.*.services_area_frequency' => 'nullable|string',
            'plan_of_care.*.review_date' => 'nullable',
        ]);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);;
        }
        
        $replace = PlanOfCare::where(['id' => $request->plan_of_care_id, 'client_id' => $request->client_id])->first();
        
        if (!$replace) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'Plan of care not found'
            ], 404);
        }
        $replace->update(['status' => 'suspended']);
        
        if($request->has('plan_of_care'))
        {
            foreach ($request->plan_of_care as $data) {
                PlanOfCare::create(array_merge($data, ['client_id' => $request->client_id]));
            }
        }
        
        $client = Client::find($request->client_id);
        
        return response()->json(['status'=>201,'response'=>'Plan of care update','message'=>'Plan of care updated successfully','data'=>$client], 200);
    }

}
