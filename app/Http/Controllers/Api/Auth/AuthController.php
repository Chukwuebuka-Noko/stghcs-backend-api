<?php

namespace App\Http\Controllers\Api\Auth;

use Carbon\Carbon;
use App\Models\User;
use App\Mail\VerifyEmail;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api',['except' => ['login','email_verify']]);
    }
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (! $token = Auth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $user = User::where('email',$request->email)->first();
        $is_verified = $user->email_verified_at;
        
        $sender = "no-reply@stghcs.com";
        if ($is_verified == null) {
            $token_mail = Crypt::encryptString($user->email);
            Mail::to($request->email)->send(new VerifyEmail($user, $token_mail, $sender));
            return response()->json(['message' => "E-mail Verification Required"], 400);
        }

        ActivityLog::create([
            'action' => 'Login',
            'description' => $user->last_name.' '.$user->first_name.' Logged in at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);

        return $this->createToken($token);
    }

    public function createToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 3600,
            'user' => auth('api')->user()
        ]);
    }

    public function email_verify(Request $request)
    {
        $token = $request->query('token', '');

        if (!$token) {
            return response()->json([
                'status' => 'Request Failed',
                'message' => 'Invalid URL provided',
            ], 404);
        }

        try {
            $email = Crypt::decryptString($token);
            $user = User::where('email', $email)->firstOrFail();

            if ($user->email_verified_at) {
                return response()->json([
                    'status' => 'Request Failed',
                    'message' => 'Email already verified',
                ], 400);
            }

            $user->email_verified_at = Carbon::now();
            $user->save();

            ActivityLog::create([
                'action' => 'E-mail Verification',
                'description' => $user->last_name.' '.$user->first_name.' Verified email address at '.Carbon::now()->format('h:i:s A'),
                'subject_id' => $user->id,
                'subject_type' => get_class($user),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'status' => 'Successful',
                'message' => 'E-mail address has been verified',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Request Failed',
                'message' => 'Invalid token provided',
            ], 404);
        }
    }

    public function profile()
    {
        return response()->json(auth('api')->user());
    }

    public function logout()
    {
        $user = User::where('id', auth('api')->user()->id)->firstOrFail();
        ActivityLog::create([
            'action' => 'Logout',
            'description' => $user->last_name.' '.$user->first_name.' Logged out at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);
        auth('api')->logout();
        return response()->json([
            'message' => 'User Logged out successful'
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'password' => ['required','confirmed','min:8','regex:/[a-z]/','regex:/[A-Z]/','regex:/[0-9]/','regex:/[@$!%*#?&]/',],
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $user = User::find(auth()->user()->id);
        // Check if the old_password matches the current password
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['message' => 'Your current password is incorrect.'], 422);
        }
        if (Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password already used, choose a different password.'], 422);
        }
        $user->password = Hash::make($request->password);
        $user->save();

        ActivityLog::create([
            'action' => 'Change of password by '.$user->last_name.' '.$user->first_name,
            'description' => $user->last_name.' '.$user->first_name.' changed his/her password at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);

        auth('api')->logout();
        return response()->json(['message' => 'Password has been successfully updated, Please login with the new password to continue'], 200);
    }
}
