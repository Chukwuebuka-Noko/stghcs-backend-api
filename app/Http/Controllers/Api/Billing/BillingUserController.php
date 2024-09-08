<?php

namespace App\Http\Controllers\Api\Billing;

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

class BillingUserController extends Controller
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
        $users = User::whereHas('roles', function($query) {
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
    
        ActivityLog::create([
            'action' => 'View All Users',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all users at ' . Carbon::now()->format('h:i:s A'),
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
        $users = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['DSW']);
            })
            ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])
            ->get();
/*
        if ($users->isEmpty()) {
            return response()->json(['status' => 200, 'response' => 'Not Found', 'message' => 'DSW(s) does not exist within ' . $manager->location->city,'data' =>$users], 200);
        }*/

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
        $users = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['CSP']);
            })
            ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])
            ->get();

        /*if ($users->isEmpty()) {
            return response()->json(['status' => 200, 'response' => 'Not Found', 'message' => 'CSP(s) does not exist within ' . $manager->location->city,'data' =>$users], 200);
        }*/

        ActivityLog::create([
            'action' => 'View All Users',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all users at ' . Carbon::now()->format('h:i:s A'),
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
        $users = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['Supervisor']);
            })
            ->with(['assigned_gig', 'timeSheets', 'rewardPointLogs', 'incident_report'])
            ->get();

        /*if ($users->isEmpty()) {
            return response()->json(['status' => 200, 'response' => 'Not Found', 'message' => 'Spervisor(s) does not exist within ' . $manager->location->city,'data' =>$users], 200);
        }*/

        ActivityLog::create([
            'action' => 'View All Users',
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' viewed all users at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($users),
            'user_id' => auth()->id(),
        ]);

        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Users fetched successfully', 'data' => $users], 200);
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
        $user = User::where(['id'=> $request->id])
        ->whereHas('roles', function($query) {
            $query->whereIn('name', ['DSW', 'CSP','Supervisor','Manager']);
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
    
        // Use eager loading for 'roles' and location
        $users = User::whereHas('roles', function($query) {
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
