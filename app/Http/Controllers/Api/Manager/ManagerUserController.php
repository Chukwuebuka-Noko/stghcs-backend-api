<?php

namespace App\Http\Controllers\Api\Manager;

use Carbon\Carbon;
use App\Models\User;
use App\Models\SupervisorInCharge;
use App\Mail\VerifyEmail;
use App\Mail\WelcomeMail;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use App\Rules\SupervisorRole;
use App\Rules\ValidZipCode;
use Spatie\Permission\Models\Role;

class ManagerUserController extends Controller
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
        $manager = User::find(auth('api')->user()->id);
    
        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }
    
        // Fetch users with the same location_id and the roles 'DSW', 'CSP', or 'Supervisor'
        $users = User::where('location_id', $manager->location_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['DSW', 'CSP', 'Supervisor']);
            })
            ->withCount('assigned_gig')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'other_name' => $user->other_name,
                    'location' => $user->address1,
                    'gender' => $user->gender,
                    'city' => $user->location->city,
                    'passport' => $user->passport,
                    'employee_id' => $user->employee_id,
                    'roles' => $user->roles->pluck('name'),
                    'assigned_gig_count' => $user->assigned_gig_count,
                ];
            })
            ->values();
    
        if ($users->isEmpty()) {
            return response()->json([
                'status' => 200,
                'response' => 'Not Found',
                'message' => 'User(s) do not exist within ' . $manager->location->city,
                'data' => $users
            ], 200);
        }
    
        ActivityLog::create([
            'action' => 'View All Users',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all users within ' . $manager->location->city . ' at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($users),
            'user_id' => auth()->id(),
        ]);
    
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'message' => 'Users fetched successfully',
            'data' => $users
        ], 200);
    }

    public function allDSW()
    {
        $manager = User::find(auth('api')->user()->id);

        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }

        // Fetch users with the same location_id and the roles 'DSW' or 'CSP'
        $users = User::where('location_id', $manager->location_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['DSW']);
            })
            ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])
            ->get();

        if ($users->isEmpty()) {
            return response()->json(['status' => 200, 'response' => 'Not Found', 'message' => 'DSW(s) does not exist within ' . $manager->location->city,'data' =>$users], 200);
        }

        ActivityLog::create([
            'action' => 'View All Users',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all users within ' . $manager->location->city . ' at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($users),
            'user_id' => auth()->id(),
        ]);

        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Users fetched successfully', 'data' => $users], 200);
    }

    public function allCSP()
    {
        $manager = User::find(auth('api')->user()->id);

        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }

        // Fetch users with the same location_id and the roles 'DSW' or 'CSP'
        $users = User::where('location_id', $manager->location_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['CSP']);
            })
            ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])
            ->get();

        if ($users->isEmpty()) {
            return response()->json(['status' => 200, 'response' => 'Not Found', 'message' => 'CSP(s) does not exist within ' . $manager->location->city,'data' =>$users], 200);
        }

        ActivityLog::create([
            'action' => 'View All Users',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all users within ' . $manager->location->city . ' at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($users),
            'user_id' => auth()->id(),
        ]);

        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Users fetched successfully', 'data' => $users], 200);
    }

    public function allSupervisor()
    {
        $manager = User::find(auth('api')->user()->id);

        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }

        // Fetch users with the same location_id and the roles 'DSW' or 'CSP'
        $users = User::where('location_id', $manager->location_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['Supervisor']);
            })
            ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])
            ->get();

        if ($users->isEmpty()) {
            return response()->json(['status' => 200, 'response' => 'Not Found', 'message' => 'Spervisor(s) does not exist within ' . $manager->location->city,'data' =>$users], 200);
        }

        ActivityLog::create([
            'action' => 'View All Users',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all users within ' . $manager->location->city . ' at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($users),
            'user_id' => auth()->id(),
        ]);

        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Users fetched successfully', 'data' => $users], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    
    public function store(Request $request)
    {
        $manager = User::find(auth('api')->user()->id);
    
        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }
    
        // Validation rules
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'other_name' => ['nullable', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone_number' => ['required', 'string'],
            //'role_id' => ['required', 'integer', 'exists:roles,id', 'in:5,6'],
            'supervisor_id' => ['required', 'exists:users,id'],
            'gender' => ['nullable', 'string'],
            'ssn' => ['nullable', 'string'],
            'address1' => ['nullable', 'string'],
            'address2' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'zip_code' => ['nullable','string', 'regex:/^\d{5}(-\d{4})?$/', new ValidZipCode],
            'dob' => ['nullable'],
            'passport_url' => ['nullable'],
        ]);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
        }
    
        /*if (in_array($request->role_id, [5, 6])) {
            if (in_array($request->role_id, [5, 6])) {
                $validator = Validator::make($request->all(), [
                    'supervisor_id' => ['required', 'exists:users,id', new SupervisorRole]
                ]);
            
                if ($validator->fails()) {
                    $errors = $validator->errors()->all();
                    return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
                }
            }
        }*/
        
    
        $employee_id = $request->role_id == 6 
            ? sprintf("CSP%05d", random_int(1000, 100000) + 1) 
            : '00'.sprintf("%05d", random_int(1000, 100000) + 1);
    
        $password = $this->generateSecurePassword();
        $hashed_password = Hash::make($password);
    
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'other_name' => $request->other_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'location_id' => $manager->location_id,
            'employee_id' => $employee_id,
            'password' => $hashed_password,
            'ssn' => $request->ssn,
            'gender' => $request->gender,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'dob' => $request->dob,
            'passport' => $request->passport_url
        ]);
        
        //if (in_array($request->role_id, [5, 6])) {
            SupervisorInCharge::create([
                'supervisor_id' => $request->supervisor_id,
                'user_id' => $user->id
            ]);
        //}
    
        // Ensure that the role exists before trying to assign it
        $role = Role::find($request->role_id);
        
        if ($role) {
            $user->syncRoles($role->name);
        } else {
            return response()->json([
                'status' => 422,
                'response' => 'Unprocessable Content',
                'message' => 'Role does not exist.'
            ], 422);
        }
        $assignedRole = $user->roles->first();
    
        
    
        $bytes = random_bytes(45);
        $token = substr(bin2hex($bytes), 0, 60);
        DB::table('password_reset_tokens')->updateOrInsert(
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
    
        $sender = "no-reply@stghcs.com";
        $token_mail = Crypt::encryptString($user->email);
        Mail::to($user->email)->send(new WelcomeMail($user, $password, $url));
        Mail::to($user->email)->send(new VerifyEmail($user, $token_mail, $sender));
    
        ActivityLog::create([
            'action' => 'Created New User',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new user for '.$manager->location->city.' at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);
    
        return response()->json(['status' => 201, 'response' => 'User Created', 'message' => 'User created successfully', 'data' => $user,'assigned_role' => $assignedRole ? $assignedRole->name : null ], 201);
}



    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $manager = User::find(auth('api')->user()->id);

        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }
        $user = User::where(['id'=> $request->id, 'location_id' => $manager->location_id])
        ->whereHas('roles', function($query) {
            $query->whereIn('name', ['DSW', 'CSP']);
        })
        ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])->first();
        if (!$user) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'User does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View A User Profile',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a user profile from '.$manager->location->city.' at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful','message'=>'User successfully fetched', 'data'=>$user], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    /*public function update(Request $request)
    {
        $manager = User::find(auth('api')->user()->id);

        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'other_name' => ['nullable', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'regex:/^\(?[0-9]{3}\)?[-. ]?[0-9]{3}[-. ]?[0-9]{4}$/'],
            'role_id' => ['required', 'integer', 'exists:roles,id', 'in:5,6'],
            'supervisor_id' => ['required', 'exists:users,id'],
            'gender' => ['nullable', 'string'],
            'ssn' => ['nullable', 'string'],
            'address1' => ['nullable', 'string'],
            'address2' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'zip_code' => ['nullable','string', 'regex:/^\d{5}(-\d{4})?$/', new ValidZipCode],
            'dob' => ['nullable'],
            'passport_url' => ['nullable'],
        ]);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
        }

        $user = User::where(['id'=>$request->id,'location_id' => $manager->location_id])->first();

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'other_name' => $request->other_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'location_id' => $manager->location_id,
        ]);
        ActivityLog::create([
            'action' => 'Updated User',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated a user from '.$manager->location->id.' at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'User Updated','message'=>'User updated successfully','data'=>$user], 200);
    }*/
    public function update(Request $request)
    {
        $manager = User::find(auth('api')->user()->id);
    
        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }
    
        $user = User::where(['id'=>$request->id,'location_id' => $manager->location_id])->first();
        if (!$user) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'User not found.'
            ], 404);
        }
    
        // Validation rules
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'other_name' => ['nullable', 'string'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'phone_number' => ['required', 'string'],
            'role_id' => ['required', 'integer', 'exists:roles,id', 'in:5,6'],
            'supervisor_id' => ['required', 'exists:users,id'],
            'gender' => ['nullable', 'string'],
            'ssn' => ['nullable', 'string'],
            'address1' => ['nullable', 'string'],
            'address2' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'zip_code' => ['nullable', 'string', 'regex:/^\d{5}(-\d{4})?$/', new ValidZipCode],
            'dob' => ['nullable'],
            'passport_url' => ['nullable'],
        ]);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
        }
    
        if (in_array($request->role_id, [5, 6])) {
            $validator = Validator::make($request->all(), [
                'supervisor_id' => ['required', 'exists:users,id', new SupervisorRole]
            ]);
    
            if ($validator->fails()) {
                $errors = $validator->errors()->all();
                return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
            }
        }
    
        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'other_name' => $request->other_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'location_id' => $manager->location_id,
            'ssn' => $request->ssn,
            'gender' => $request->gender,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'dob' => $request->dob,
            'passport' => $request->passport_url
        ]);
    
        if (in_array($request->role_id, [5, 6])) {
            SupervisorInCharge::updateOrCreate(
                ['user_id' => $user->id],
                ['supervisor_id' => $request->supervisor_id]
            );
        }
    
        $user->syncRoles($request->role_id);
    
        ActivityLog::create([
            'action' => 'Updated User',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated user for '.$manager->location->city.' at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);
    
        return response()->json(['status' => 200, 'response' => 'User Updated', 'message' => 'User updated successfully', 'data' => $user], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $manager = User::find(auth('api')->user()->id);

        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }

        $user = User::where(['id'=>$request->id,'location_id' => $manager->location_id])->first();
        if (!$user) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $user->delete();
        ActivityLog::create([
            'action' => 'Deleted A User',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a user '.$manager->location->city.' at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($user),
            'subject_id' => $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>204,'response'=>'No Content','message' => 'User Deleted successfully']);
    }

    public function generateSecurePassword($length = 20) 
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+-=[]{}|;:,.<>?';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $password;
    }

    public function assignRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id'],
            'roles' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
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
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
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
    
    public function paginate(Request $request)
    {
        $perPage = $request->input('per_page', 50);
    
        // Using auth()->user() directly assuming you have set up your auth guard correctly
        $manager = auth('api')->user();
    
        // Check if the manager exists and has the 'Manager' role
        if (!$manager || !$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager or not authenticated.'
            ], 403);
        }
    
        // Ensure the manager has a location set
        if (!$manager->location_id) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'Manager location not set.'
            ], 404);
        }
    
        // Use eager loading for 'roles' and location
        $users = User::where('location_id', $manager->location_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['DSW', 'CSP', 'Supervisor']);
            })
            ->with(['roles', 'location'])
            ->withCount('assigned_gig')
            ->paginate($perPage);
    
        if ($users->isEmpty()) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No users found within ' . $manager->location->city
            ], 404);
        }
    
        // Mapping over the paginator's items (done properly here)
        /*$transformedUsers = $users->getCollection()->map(function($user) {
            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'other_name' => $user->other_name,
                'location' => $user->address1,
                'gender' => $user->gender,
                'city' => $user->location->city ?? 'N/A',  // Handling possible null location
                'passport' => $user->passport,
                'employee_id' => $user->employee_id,
                'roles' => $user->roles->pluck('name'),
                'assigned_gig_count' => $user->assigned_gig_count,
            ];
        });*/
    
        // Log the view action
        ActivityLog::create([
            'action' => 'View All Users',
            'description' => $manager->last_name . ' ' . $manager->first_name . ' viewed all users within ' . ($manager->location->city ?? 'N/A') . ' at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $manager->id,
            'subject_type' => get_class($users),
            'user_id' => $manager->id,
        ]);
    
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'message' => 'Users fetched successfully',
            'data' => $users
        ], 200);
    }
    
    public function activate_deactivate(Request $request)
    {
        $manager = User::find(auth('api')->user()->id);

        // Check if the user has the 'manager' role
        if (!$manager->hasRole('Manager')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a manager.'
            ], 403);
        }
        
        // Validating the 'type' which is expected to be either in the body or as a query parameter
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:Activate,Deactivate,activate,deactivate'],
        ]);
    
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }
    
        // Getting the client ID from the query parameters
        $userId = $request->query('id');
        if (empty($userId)) {
            return response()->json(['status' => 400, 'response' => 'Bad Request', 'message' => 'User ID is required'], 400);
        }
    
        $user = User::where(['id'=>$request->id,'location_id' => $manager->location_id])->first();
        if (!$user) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'User not found'], 404);
        }
    
        $typeLower = strtolower($request->type);
        $status = ($typeLower == "deactivate") ? "inactive" : "active";
        $type = ($typeLower == "deactivate") ? "deactivated" : "activated";
    
        try {
            $user->update(['status' => $status]);
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'response' => 'Internal Server Error', 'message' => 'Failed to update user'], 500);
        }
    
        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'User ' . $type . ' successfully', 'user' => $user]);
    }
    
    public function role_list()
    {
        // Define the specific roles to fetch
        $desiredRoles = ['DSW', 'CSP'];
    
        // Fetch the roles that match the desired roles
        $roles = Role::whereIn('name', $desiredRoles)->get();
    
        if ($roles->isEmpty()) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Role(s) does not exist'], 404);
        }
    
        return response()->json(['status' => 200, 'response' => 'Successful', "message" => "Roles fetched successfully", "data" => $roles], 200);
    }


}
