<?php

namespace App\Http\Controllers\Api\Auth;

use Carbon\Carbon;
use App\Models\User;
use App\Mail\VerifyEmail;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\SetupRequest;
use App\Http\Controllers\Controller;
use App\Models\AssignGig;
use App\Models\IncidentReport;
use App\Models\RewardPoint;
use App\Models\RewardPointLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login','checkToken', 'profile']]);
    }
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = Auth::attempt($credentials)) {
            return response()->json(['status' => 403, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 403);
        }
        $user = User::where('email', $request->email)->first();
        $is_verified = $user->email_verified_at;

        $sender = "no-reply@stghcs.com";
        /*if ($is_verified == null) {
            return response()->json(['status' => 400, 'response' => 'Bad Request', 'message' => "E-mail Verification Required"], 400);
        }*/

        ActivityLog::create([
            'action' => 'Login',
            'description' => $user->last_name . ' ' . $user->first_name . ' Logged in at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);

        return $this->createToken($token);
    }

    public function createToken($token)
    {
        $user = User::find(auth('api')->user()->id);
        $active = AssignGig::where('user_id', auth('api')->user()->id)->count();
        $assign_gig =  AssignGig::where('user_id', auth('api')->user()->id)->with(['gig.client','schedule'])->get();
        $incidents =  IncidentReport::where('user_id', auth('api')->user()->id)->get();
        if($user->hasRole('Manager') || $user->hasRole('Admin')){
            $can_create = true;
        }else{
            $can_create = false;
        }
        if (!$user->hasRole('Admin')) {
            if ($user->ssn == null) {
                return response()->json([
                    'status' => 200,
                    'response' => 'Complete Setup',
                    'message' => "Please Update your details to continue.",
                    'profile_completed' => false,
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => auth('api')->factory()->getTTL() * 360000,
                    'active_gig' => $active,
                    'user' => $user,
                    'location' => $user->location->city,
                    'assigned_gigs' => $assign_gig,
                    'incidents' => $incidents,
                    'can_create_users' => true,
                ]);
            }
        }
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'profile_completed' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 360000,
            'active_gig' => $active,
            'user' => $user,
            'location' => $user->location->city,
            'assigned_gigs' => $assign_gig,
            'incidents' => $incidents,
            'can_create_users' => $can_create
        ]);
    }
    public function email_verify(Request $request)
    {
        $request->validate([
            'verification_code' => 'required',
        ]);

        try {
            $user = User::where('email', auth()->user()->email)->firstOrFail();
    
            if ($user->email_verified_at) {
                return response()->json([
                    'status' => 400,
                    'response' => 'Bad Request',
                    'message' => 'Email already verified',
                ], 400);
            }
    
            if ($user->verification_code != $request->verification_code) {
                return response()->json([
                    'status' => 400,
                    'response' => 'Bad Request',
                    'message' => 'Invalid verification code',
                ], 400);
            }
    
            $user->email_verified_at = Carbon::now();
            $user->verification_code = null; // Clear the code after successful verification
            $user->save();
    
            ActivityLog::create([
                'action' => 'E-mail Verification',
                'description' => $user->last_name . ' ' . $user->first_name . ' verified email address at ' . Carbon::now()->format('h:i:s A'),
                'subject_id' => $user->id,
                'subject_type' => get_class($user),
                'user_id' => auth()->id(),
            ]);
    
            return response()->json([
                'status' => 200,
                'response' => 'Successful',
                'message' => 'E-mail address has been verified',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'response' => 'Request Failed',
                'message' => 'Invalid email or verification code',
            ], 400);
        }
    }

    public function profile(Request $request)
    {
        try {
            // Get the authenticated user
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => 401,
                'response' => 'Unauthorized',
                'message' => 'Token has expired'
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'status' => 401,
                'response' => 'Unauthorized',
                'message' => 'Token is invalid'
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 401,
                'response' => 'Unauthorized',
                'message' => 'Token not provided'
            ], 401);
        }
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }
    
        // Eager load the user with roles and other relationships
        $user = User::where('employee_id', $request->employee_id)
                    ->with([
                        'assigned_gig.gig.client',
                        'assigned_gig.gig.schedule',
                        'assigned_gig.gig.timesheet',
                        'rewardPointLogs',
                        'incident_report',
                        'roles', // Ensure roles are loaded
                        'assigned_gig' => function($query) {
                            $query->orderBy('created_at', 'desc'); // Order gigs by descending order of created_at
                        }
                    ])->first();
    
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
    
        $active = AssignGig::where('user_id', $user->id)->count();
    
        // Extract roles data to include in the response
        $roles = $user->roles->pluck('name'); // You can change this if you need to send more details about the roles
    
        return response()->json([
            'status' => 200,
            'response' => $user->last_name . ' ' . $user->first_name . ' fetched successfully',
            'user' => $user,
            'roles' => $roles,
            'active_gig' => $active
        ], 200);
}


    public function logout()
    {
        $user = User::where('id', auth('api')->user()->id)->firstOrFail();
        ActivityLog::create([
            'action' => 'Logout',
            'description' => $user->last_name . ' ' . $user->first_name . ' Logged out at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);
        auth('api')->logout();
        return response()->json([
            'status' => 200, 'response' => 'Successful',
            'message' => 'User Logged out successful'
        ]);
    }
    
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|min:14', // Added min length for old password
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
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $validator->errors()->all()], 422);
        }
    
        $user = User::find(auth()->user()->id);
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['status' => 422, 'response' => 'Incorrect Current Password', 'message' => ['Your current password is incorrect.']], 422);
        }
    
        if (Hash::check($request->password, $user->password)) {
            return response()->json(['status' => 422, 'response' => 'Password Same As Old Password', 'message' => ['Password already used, choose a different password.']], 422);
        }
    
        // Consider a lock mechanism here if needed
    
        $user->password = Hash::make($request->password);
        $user->save();
    
        ActivityLog::create([
            'action' => 'Change of password by ' . $user->last_name . ' ' . $user->first_name,
            'description' => $user->last_name . ' ' . $user->first_name . ' changed his/her password at ' . now()->toDateTimeString(),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);
    
        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => ['Password has been successfully updated']], 200);
}

    public function reset_temporary(Request $request)
    {
        $validator = Validator::make($request->all(), [
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
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }

        $user = User::where('email', auth()->user()->email)->firstOrFail();
        $temp_password = $user->password;
        if (!Hash::check($request->temp_password, $temp_password)) {
            return response()->json(['status' => 401, 'response' => 'Temporary Password Mismatch', 'message' => 'Temporary Password do not match.'], 401);
        }
        $user->password = Hash::make($request->password);
        $user->is_temporary_password = 0;
        $user->save();

        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Password has been successfully reset.', 'data' => $user], 200);
    }
    
    
    public function complete_setup(SetupRequest $request)
    {
        $user = User::find(auth('api')->user()->id);
        if (!$user) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'User Not Found!'], 404);
        }
         // Format the date of birth
        $formattedDob = Carbon::parse($request->dob)->format('m-d-Y');

        $data = [
            'ssn' => encrypt($request->ssn),
            'gender' => $request->gender,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'dob' => $formattedDob,
        ];

        if ($request->hasFile('id_card')) {
            $idNameToStore = $this->uploadFile($request->file('id_card'), 'stghcs/id_card');
            if ($idNameToStore) {
                $data['id_card'] = $idNameToStore;
            } else {
                return response()->json(['status' => 422, 'response' => 'Unprocessable Entity', 'message' => 'Failed to upload ID card.'], 422);
            }
        }

        if (!$user->update($data)) {
            return response()->json(['status' => 500, 'response' => 'Internal Server Error', 'message' => 'Failed to update user data.'], 500);
        }

        $point = 0; // Initialize $point
        if ($user->ssn != null && $user->id_card != null && $user->passport != null && $user->gender != null && $user->address1 != null && $user->city != null && $user->zip_code != null && $user->dob != null) {
            $reward = RewardPoint::where(['name' => 'Setup Completion'])->first();
            if ($reward) {
                $point = $reward->points;
                $current_point = $user->point;
                $new_point = $point + $current_point;
                $user->update([
                    'points' => $new_point
                ]);
                RewardPointLog::create([
                    'title' => 'Setup Completion by ' . $user->last_name . ' ' . $user->first_name,
                    'user_id' => $user->id,
                    'points' => $point
                ]);
            }
        }

        if ($point > 0) {
            ActivityLog::create([
                'action' => 'Account Setup Completion by ' . $user->last_name . ' ' . $user->first_name,
                'description' => $user->last_name . ' ' . $user->first_name . ' was awarded ' . $point . ' for completing the profile setup at ' . Carbon::now()->format('h:i:s A'),
                'subject_id' => $user->id,
                'subject_type' => get_class($user),
                'user_id' => auth()->id(),
            ]);
        }

        return response()->json(['status' => 200, 'response' => 'Success', 'message' => 'Setup completed successfully.', 'user' => $user]);
    }
    
    
    private function uploadFile($file, $folder)
    {
        try {
            $uploadedFile = cloudinary()->upload($file->getRealPath(), [
                'folder' => $folder,
                'resource_type' => 'image'
            ]);
            return $uploadedFile->getSecurePath();
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function checkToken(Request $request)
    {
        try {
            // Get the authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => 401,
                'response' => 'Unauthorized',
                'message' => 'Token has expired'
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'status' => 401,
                'response' => 'Unauthorized',
                'message' => 'Token is invalid'
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 401,
                'response' => 'Unauthorized',
                'message' => 'Token not provided'
            ], 401);
        }
        // If the request reaches here, the token is still active
        return response()->json([
            'status' => 200,
            'response' => 'Authorized',
            'message' => 'Token is active',
        ],200);
    }
}
