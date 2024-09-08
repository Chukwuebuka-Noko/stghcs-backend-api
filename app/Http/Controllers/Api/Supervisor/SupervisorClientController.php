<?php

namespace App\Http\Controllers\Api\Supervisor;

use Carbon\Carbon;
use App\Models\Client;
use App\Models\ActivityLog;
use App\Rules\ValidZipCode;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class SupervisorClientController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Supervisor']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $supervisor = auth()->user();
        $city = $supervisor->city;
        if(is_null($city)){
            return response()->json(['status'=>409,'response'=>'Conflict','message'=>'Supervisor city is Undefined'], 409);
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
    // public function store(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'title' => ['nullable','string'],
    //         'first_name' => ['required','string'],
    //         'last_name' => ['required','string'],
    //         'email' => ['required','string','unique:clients,email'],
    //         'phone_number' => ['required','string','unique:clients,phone_number','regex:/^\(?[0-9]{3}\)?[-. ]?[0-9]{3}[-. ]?[0-9]{4}$/'],
    //         'coordinate' => ['required'],
    //         'city' => ['required','string'],
    //         'zip_code' => ['required','string', 'regex:/^\d{5}(-\d{4})?$/', new ValidZipCode],
    //         'address1' => ['required','string'],
    //         'address2' => ['nullable','string'],
    //         'plan_of_care' => ['required']
    //     ]);
    //     if ($validator->fails()) {
    //         return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
    //     }

    //     $coordinate = json_encode($request->coordinate);
    //     if($request->hasFile('plan_of_care')){
    //         $file = $request->file('plan_of_care');
    //         $folder = 'stghcs/plan_of_care';
    //         $uploadedFile = cloudinary()->upload($file->getRealPath(), [
    //             'folder' => $folder
    //         ]);

    //         $fileNameToStore = $uploadedFile->getSecurePath();
    //     }
    //     $client = Client::create([
    //         'title' => $request->title,
    //         'first_name' => $request->first_name,
    //         'last_name' => $request->last_name,
    //         'email' => $request->email,
    //         'phone_number' => $request->phone_number,
    //         'city' => $request->city,
    //         'zip_code' => $request->zip_code,
    //         'address1' => $request->address1,
    //         'address2' => $request->address2,
    //         'coordinate' => $coordinate,
    //         'plan_of_care' => $fileNameToStore
    //     ]);
    //     ActivityLog::create([
    //         'action' => 'Created New client',
    //         'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new client at '.Carbon::now()->format('h:i:s A'),
    //         'subject_id' => $client->id,
    //         'subject_type' => get_class($client),
    //         'user_id' => auth()->id(),
    //     ]);
    //     return response()->json(['status'=>201,'response'=>'Client Created','message'=>'Client created successfully','data'=>$client], 201);
    // }

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
            'plan_of_care' => ['nullable']
        ]);
    
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }
        
        $coord = [
            'lat' =>$request->lat,
            'long' => $request->long
            ];
    
        $coordinate = json_encode($coord);
    
        /*if($request->hasFile('plan_of_care')){
            $file = $request->file('plan_of_care');
            $folder = 'stghcs/plan_of_care';
            $uploadedFile = cloudinary()->upload($file->getRealPath(), [
                'folder' => $folder
            ]);
    
            $fileNameToStore = $uploadedFile->getSecurePath();
        } else {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => ['plan_of_care' => 'Plan of care file is required']], 422);
        }*/
        
        $fileNameToStore ='';
        
        
        if($request->hasFile('plan_of_care')){
            $file = $request->file('plan_of_care');
            $folder = 'stghcs/plan_of_care';
            $uploadedFile = cloudinary()->upload($file->getRealPath(), [
                'folder' => $folder
            ]);
    
            $fileNameToStore = $uploadedFile->getSecurePath();
        }
    
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
            'plan_of_care' => $fileNameToStore
        ]);
    
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
        $supervisor = auth()->user();
        $city = $supervisor->city;
        if(is_null($city)){
            return response()->json(['status'=>409,'response'=>'Conflict','message'=>'Supervisor city is Undefined'], 409);
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
            'address2' => ['nullable', 'string'],
            'plan_of_care' => ['nullable'],
        ]);
    
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }
    
        try {
            $client = Client::findOrFail($request->id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Client not found'], 404);
        }
    
        // Collect and handle request data
        $data = [
            'title' => is_null($request->title) ? '' : $request->title,
            'first_name' => $request->first_name,
            'last_name' =>  $request->last_name,
            'other_name' => is_null($request->other_name) ? '' : $request->other_name,
            'dob' => is_null($request->dob) ? '' : $request->dob,
            'email' => is_null($request->email) ? '' : $request->email,
            'phone_number' =>  $request->phone_number,
            'city' =>  $request->city,
            'zip_code' => is_null($request->zip_code) ? '' : $request->zip_code,
            'address1' =>  $request->address1,
            'address2' => is_null($request->address2) ? '' : $request->address2,
            'coordinate' => json_encode([
                'lat' =>  $request->lat,
                'long' => $request->long
            ])
        ];
    
        if ($request->hasFile('plan_of_care')) {
            $file = $request->file('plan_of_care');
            $folder = 'stghcs/plan_of_care';
            $uploadedFile = cloudinary()->upload($file->getRealPath(), [
                'folder' => $folder
            ]);
    
            $fileNameToStore = $uploadedFile->getSecurePath();
            $data['plan_of_care'] = $fileNameToStore;
        }
    
        $client->update($data);
    
        ActivityLog::create([
            'action' => 'Updated Client',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' updated a client at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $client->id,
            'subject_type' => get_class($client),
            'user_id' => auth()->id(),
        ]);
    
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
    public function archive_unarchive(Request $request)
    {
        // Validating the 'type' which is expected to be either in the body or as a query parameter
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:Archive,Unarchive,archive,unarchive'],
        ]);
    
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
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
    
    public function search(Request $request)
    {
        $supervisor = auth()->user();
        $city = $supervisor->city;
        if(is_null($city)){
            return response()->json(['status'=>409,'response'=>'Conflict','message'=>'Supervisor city is Undefined'], 409);
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
        $supervisor = auth()->user();
        $city = $supervisor->city;
        if(is_null($city)){
            return response()->json(['status'=>409,'response'=>'Conflict','message'=>'Supervisor city is Undefined'], 409);
        }
        $searchTerm = $request->input('query');
        $perPage = $request->input('per_page', 20);
        $clients = Client::with(['created_by']) // Eager load relationships
                 ->orderBy('created_at', 'desc') // Order by creation date
                 ->paginate($perPage); // Paginate the results
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
}
