<?php

namespace App\Http\Controllers\Api\Supervisor;

use Carbon\Carbon;
use App\Models\User;
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

class SupervisorUserController extends Controller
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
        $supervisor = User::find(auth('api')->user()->id);

        // Check if the user has the 'Supervisor' role
        if (!$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a Supervisor.'
            ], 403);
        }

        // Fetch users with the same location_id and the roles 'DSW' or 'CSP'
        $users = User::where('location_id', $supervisor->location_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['DSW', 'CSP']);
            })
            ->whereHas('supervisor_in_charges', function($query) use ($supervisor) {
                $query->where('supervisor_id', $supervisor->id);
            })
            ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])
            ->get();

        if ($users->isEmpty()) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'User(s) does not exist within ' . $supervisor->location->city,'data' =>$users], 404);
        }

        ActivityLog::create([
            'action' => 'View All Users',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all users within ' . $supervisor->location->city . ' at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($users),
            'user_id' => auth()->id(),
        ]);

        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Users fetched successfully', 'data' => $users], 200);
    }

    /*public function allDSW()
    {
        $supervisor = User::find(auth('api')->user()->id);

        // Check if the user has the 'Supervisor' role
        if (!$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a Supervisor.'
            ], 403);
        }

        // Fetch users with the same location_id and the roles 'DSW' or 'CSP'
        $users = User::where('location_id', $supervisor->location_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['DSW']);
            })
            ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])
            ->get();

        if ($users->isEmpty()) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'DSW(s) does not exist within ' . $supervisor->location->city,'data' =>$users], 404);
        }

        ActivityLog::create([
            'action' => 'View All Users',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all users within ' . $supervisor->location->city . ' at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($users),
            'user_id' => auth()->id(),
        ]);

        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Users fetched successfully', 'data' => $users], 200);
    }*/
    
    public function allDSW()
{
    $supervisor = User::find(auth('api')->user()->id);

    // Check if the user has the 'Supervisor' role
    if (!$supervisor->hasRole('Supervisor')) {
        return response()->json([
            'status' => 403,
            'response' => 'Forbidden',
            'message' => 'Access denied. User is not a Supervisor.'
        ], 403);
    }

    // Fetch DSW users supervised by the logged-in supervisor
    $users = User::whereHas('roles', function($query) {
            $query->where('name', 'DSW');
        })
        ->whereHas('supervisor_in_charges', function($query) use ($supervisor) {
            $query->where('supervisor_id', $supervisor->id);
        })
        ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])
        ->get();

    if ($users->isEmpty()) {
        return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'DSW(s) does not exist under the supervisor','data' => $users], 404);
    }

    ActivityLog::create([
        'action' => 'View All DSW Users',
        'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all DSW users under supervision at ' . Carbon::now()->format('h:i:s A'),
        'subject_id' => auth()->id(),
        'subject_type' => get_class($users),
        'user_id' => auth()->id(),
    ]);

    return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Users fetched successfully', 'data' => $users], 200);
}


    public function allCSP()
    {
        $supervisor = User::find(auth('api')->user()->id);

        // Check if the user has the 'Supervisor' role
        if (!$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a Supervisor.'
            ], 403);
        }
    
        // Fetch DSW users supervised by the logged-in supervisor
        $users = User::whereHas('roles', function($query) {
                $query->where('name', 'CSP');
            })
            ->whereHas('supervisor_in_charges', function($query) use ($supervisor) {
                $query->where('supervisor_id', $supervisor->id);
            })
            ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])
            ->get();
    
        if ($users->isEmpty()) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'CSP(s) does not exist under the supervisor','data' => $users], 404);
        }
    
        ActivityLog::create([
            'action' => 'View All CSP Users',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all CSP users under supervision at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($users),
            'user_id' => auth()->id(),
        ]);
    
        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Users fetched successfully', 'data' => $users], 200);
    }

    public function allSupervisor()
    {
        $supervisor = User::find(auth('api')->user()->id);

        // Check if the user has the 'Supervisor' role
        if (!$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a Supervisor.'
            ], 403);
        }

        // Fetch users with the same location_id and the roles 'DSW' or 'CSP'
        $users = User::where('location_id', $supervisor->location_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['Supervisor']);
            })
            ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])
            ->get();

        if ($users->isEmpty()) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Spervisor(s) does not exist within ' . $supervisor->location->city,'data' =>$users], 404);
        }

        ActivityLog::create([
            'action' => 'View All Users',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all users within ' . $supervisor->location->city . ' at ' . Carbon::now()->format('h:i:s A'),
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
    $supervisor = User::find(auth('api')->user()->id);

    // Check if the user has the 'supervisor' role
    if (!$supervisor->hasRole('Supervisor')) {
        return response()->json([
            'status' => 403,
            'response' => 'Forbidden',
            'message' => 'Access denied. User is not a supervisor.'
        ], 403);
    }

    // Validation rules
    $validator = Validator::make($request->all(), [
        'first_name' => ['required', 'string'],
        'last_name' => ['required', 'string'],
        'other_name' => ['nullable', 'string'],
        'email' => ['required', 'email', 'unique:users,email'],
        'phone_number' => ['required', 'string'],
        'role_id' => ['required', 'integer', 'exists:roles,id']
    ]);

    if ($validator->fails()) {
        return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $validator->errors()->all()], 422);
    }

    if (in_array($request->role_id, [5, 6])) {
        if (in_array($request->role_id, [5, 6])) {
            $validator = Validator::make($request->all(), [
                'supervisor_id' => ['required', 'exists:users,id']
            ]);
        
            if ($validator->fails()) {
                return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $validator->errors()->all()], 422);
            }
        }
    }
    

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
        'location_id' => $supervisor->location_id,
        'employee_id' => $employee_id,
        'password' => $hashed_password
    ]);
    
    if (in_array($request->role_id, [5, 6])) {
        SupervisorInCharge::create([
            'supervisor_id' => $request->supervisor_id,
            'user_id' => $user->id
        ]);
    }

    $user->syncRoles($request->role_id);

    

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
        'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new user for '.$supervisor->location->city.' at '.Carbon::now()->format('h:i:s A'),
        'subject_id' => $user->id,
        'subject_type' => get_class($user),
        'user_id' => auth()->id(),
    ]);

    return response()->json(['status' => 201, 'response' => 'User Created', 'message' => 'User created successfully', 'data' => $user], 201);
}

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $supervisor = User::find(auth('api')->user()->id);

        // Check if the user has the 'Supervisor' role
        if (!$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a Supervisor.'
            ], 403);
        }
        $user = User::where(['id'=> $request->id, 'location_id' => $supervisor->location_id])
        ->whereHas('roles', function($query) {
            $query->whereIn('name', ['DSW', 'CSP']);
        })
        ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])->first();
        if (!$user) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'User does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View A User Profile',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a user profile from '.$supervisor->location->city.' at '.Carbon::now()->format('h:i:s A'),
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
        $supervisor = User::find(auth('api')->user()->id);

        // Check if the user has the 'Supervisor' role
        if (!$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a Supervisor.'
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'first_name' => ['required','string'],
            'last_name' => ['required','string'],
            'other_name' => ['nullable','string'],
            'email' => ['required','email'],
            'phone_number' => ['required','string', 'regex:/^\(?[0-9]{3}\)?[-. ]?[0-9]{3}[-. ]?[0-9]{4}$/'],
            'role_id' => ['required', 'exists:roles,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $user = User::where(['id'=>$request->id,'location_id' => $supervisor->location_id])->first();

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'other_name' => $request->other_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'location_id' => $supervisor->location_id,
        ]);
        ActivityLog::create([
            'action' => 'Updated User',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated a user from '.$supervisor->location->id.' at '.Carbon::now()->format('h:i:s A'),
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
        $supervisor = User::find(auth('api')->user()->id);

        // Check if the user has the 'Supervisor' role
        if (!$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a Supervisor.'
            ], 403);
        }

        $user = User::where(['id'=>$request->id,'location_id' => $supervisor->location_id])->first();
        if (!$user) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $user->delete();
        ActivityLog::create([
            'action' => 'Deleted A User',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a user '.$supervisor->location->city.' at '.Carbon::now()->format('h:i:s A'),
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
        $supervisor = auth('api')->user();
    
        // Check if the supervisor exists and has the 'supervisor' role
        if (!$supervisor || !$supervisor->hasRole('Supervisor')) {
            return response()->json([
                'status' => 403,
                'response' => 'Forbidden',
                'message' => 'Access denied. User is not a supervisor or not authenticated.'
            ], 403);
        }
    
        // Ensure the supervisor has a location set
        if (!$supervisor->location_id) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'supervisor location not set.'
            ], 404);
        }
    
        // Use eager loading for 'roles' and location
        $users = User::where('location_id', $supervisor->location_id)
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
                'message' => 'No users found within ' . $supervisor->location->city
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
            'description' => $supervisor->last_name . ' ' . $supervisor->first_name . ' viewed all users within ' . ($supervisor->location->city ?? 'N/A') . ' at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $supervisor->id,
            'subject_type' => get_class($users),
            'user_id' => $supervisor->id,
        ]);
    
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'message' => 'Users fetched successfully',
            'data' => $users
        ], 200);
}
}
