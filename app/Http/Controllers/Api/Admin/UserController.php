<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Mail\WelcomeMail;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::get();
        if ($users->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'User(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View All Users',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all users at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($users),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Users fetched successfully","data"=>$users],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required','string'],
            'last_name' => ['required','string'],
            'other_name' => ['nullable','string'],
            'email' => ['required','email','unique:users,email'],
            'phone_number' => ['required','string'],
            'location_id' => ['required', 'exists:locations,id'],
            'gender' => ['required', 'string'],
            'address1' => ['required', 'string'],
            'address2' => ['nullable', 'string'],
            'city' => ['required', 'string'],
            'zip_code' => ['required', 'string'],
            'dob' => ['required', 'date_format:Y-m-d'],
            'role_id' => ['required', 'exists:roles,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()], 422);
        }
        if($request->role_id == 6){
            $employee_id = sprintf("CSP%05d", random_int(1000, 100000) + 1);
        }else{
            $employee_id = sprintf("%05d", random_int(1000, 100000) + 1);
        }
        $password = $this->generateSecurePassword();
        $hashed_password = Hash::make($password);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'other_name' => $request->other_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'location_id' => $request->location_id,
            'gender' => $request->gender,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'dob' => $request->dob,
            'employee_id' => $employee_id,
            'password' => $hashed_password
        ]);
        $user->syncRoles($request->role_id);

        $bytes = random_bytes(45);
        $token = substr(bin2hex($bytes), 0, 60);
        $response=  DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => $token,
                'created_at' => now()
            ]
        );
        $url = url(route('temporary.reset', [
            'token' => $token,
            'email' => $user->email  // Including the email in the URL
        ], false));
        Mail::to($user->email)->send(new WelcomeMail($user, $password, $url));
        ActivityLog::create([
            'action' => 'Created New User',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new user at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>201,'response'=>'User Created','message'=>'User created successfully','data'=>$user], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $user = User::where('id', $request->id)->first();
        if (!$user) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'User does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View A User Profile',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a user profile at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful','message'=>'User successfully fetched', 'data'=>$user], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required','string'],
            'last_name' => ['required','string'],
            'other_name' => ['nullable','string'],
            'email' => ['required','email'],
            'phone_number' => ['required','string'],
            'location_id' => ['required', 'exists:locations,id'],
            'gender' => ['required', 'string'],
            'address1' => ['required', 'string'],
            'address2' => ['nullable', 'string'],
            'city' => ['required', 'string'],
            'zip_code' => ['required', 'string'],
            'dob' => ['required', 'date_format:Y-m-d'],
            'role_id' => ['required', 'exists:roles,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()], 422);
        }

        $user = User::find($request->id);

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'other_name' => $request->other_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'location_id' => $request->location_id,
            'gender' => $request->gender,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'dob' => $request->dob,
        ]);
        ActivityLog::create([
            'action' => 'Updated User',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated a user at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'User Updated','message'=>'User updated successfully','data'=>$user], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $user = User::find($request->id);
        if (!$user) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $user->delete();
        ActivityLog::create([
            'action' => 'Deleted A User',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a user at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($user),
            'subject_id' => $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>204,'response'=>'No Content','message' => 'User Deleted successfully']);
    }

    public function generateSecurePassword($length = 20) 
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+-=[]{}|;:",.<>?';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $password;
    }

    public function reset_temporary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required',
            'temp_password' => 'required',
            'password' => [
                'required',
                'confirmed',
                'min:14',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
            ],
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()], 422);
        }

        $record = DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->where('token', $request->token)
                    ->first();
    
        if (!$record || now()->subMinutes(1440)->greaterThan($record->created_at)) {
            return response()->json(['status'=>422,'response'=>'Token Expired','message' => 'Invalid or expired reset token.'], 422);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $temp_password = $user->password;
        if(!Hash::check($request->temp_password, $temp_password))
        {
            return response()->json(['status'=>401,'response'=>'Unauthorized','message' => 'Temporary Password do not match.'], 401);
        }
        $user->password = Hash::make($request->password);
        $user->is_temporary_password = false;
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        return response()->json(['status'=>200,'response'=>'Successful','message' => 'Password has been successfully reset.','data'=> $user], 200);
    }

    public function assignRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id'],
            'roles' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()], 422);
        }

        $user = User::find($request->user_id);
        $user->assignRole($request->roles);
        ActivityLog::create([
            'action' => 'Assign role to '.$user->last_name.' '.$user->first_name,
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' assigned a role to '.$user->last_name.' '.$user->first_name.' at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($user),
            'subject_id' => $user->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful','message' => 'Role(s) successfully assign to '.$user->last_name.' '.$user->first_name]);
    }

    public function fetchRoles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()], 422);
        }

        $user = User::find($request->user_id);
        $roleNames = $user->getRoleNames();
        $roles = $roleNames->join(', ');
        ActivityLog::create([
            'action' => 'View roles assigned to '.$user->last_name.' '.$user->first_name,
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' view roles assigned to '.$user->last_name.' '.$user->first_name.' at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($user),
            'subject_id' => $user->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful','message' => 'Role(s) assigned to '.$user->last_name.' '.$user->first_name, 'roles' => $roles]);
    }
}
