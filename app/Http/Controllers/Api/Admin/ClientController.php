<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\Client;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $clients = Client::get();
        if ($clients->isEmpty()) {
            return response()->json(['message'=>'Client(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View All Clients',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all clients at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($clients),
            'user_id' => auth()->id(),
        ]);
        return response()->json(["message"=>"Client fetched successfully","data"=>$clients],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required','string'],
            'last_name' => ['required','string'],
            'email' => ['required','string','unique:clients,email'],
            'phone_number' => ['required','string','unique:clients,phone_number'],
            'coordinate' => ['required'],
            'city' => ['required','string'],
            'zip_code' => ['required','string'],
            'address1' => ['required','string'],
            'address2' => ['nullable','string'],
            'plan_of_care' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $coordinate = json_encode($request->coordinate);
        if($request->hasFile('plan_of_care')){
            $file = $request->file('plan_of_care');
            $folder = 'stghcs/plan_of_care';
            $uploadedFile = cloudinary()->upload($file->getRealPath(), [
                'folder' => $folder
            ]);

            $fileNameToStore = $uploadedFile->getSecurePath();
        }
        $client = Client::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'coordinate' => $coordinate,
            'plan_of_care' => $fileNameToStore
        ]);
        ActivityLog::create([
            'action' => 'Created New client',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new client at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $client->id,
            'subject_type' => get_class($client),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['message'=>'Client created successfully','data'=>$client], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $client = Client::where('id', $request->id)->first();
        if (!$client) {
            return response()->json(['message'=>'Client does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View A Client Details',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a client details at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $client->id,
            'subject_type' => get_class($client),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['message'=>'Client successfully fetched', 'data'=>$client], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required','string'],
            'last_name' => ['required','string'],
            'email' => ['required','string'],
            'phone_number' => ['required','string'],
            'coordinate' => ['required'],
            'city' => ['required','string'],
            'zip_code' => ['required','string'],
            'address1' => ['required','string'],
            'address2' => ['nullable','string']
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $coordinate = json_encode($request->coordinate);
        $client = Client::find($request->id);
        // Prepare the data to be updated
        $data = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'coordinate' => $coordinate,
        ];

        // Check if the file is uploaded and if so, process it
        
        if($request->hasFile('plan_of_care')){
            $file = $request->file('plan_of_care');
            $folder = 'stghcs/plan_of_care';
            $uploadedFile = cloudinary()->upload($file->getRealPath(), [
                'folder' => $folder
            ]);

            $fileNameToStore = $uploadedFile->getSecurePath();
            $data['plan_of_care'] = $fileNameToStore;
        }

        // Update the client
        $client->update($data);
        ActivityLog::create([
            'action' => 'Updated Client',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated a client at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $client->id,
            'subject_type' => get_class($client),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['message'=>'Client updated successfully','data'=>$client], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $client = Client::find($request->id);
        if (!$client) {
            return response()->json(['message' => 'Not Found!'], 404);
        }
        $client->delete();
        ActivityLog::create([
            'action' => 'Deleted A Client',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a client at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($client),
            'subject_id' => $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['message' => 'Client Deleted successfully']);
    }
}
