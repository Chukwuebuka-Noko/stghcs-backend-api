<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use App\Mail\SendOTP;
use App\Mail\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;

class ForgotPasswordController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email']);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        // Throttle reset attempts
        $throttleKey = 'password-reset:' . $request->ip();
        if (!RateLimiter::remaining($throttleKey, $maxAttempts = 5)) {
            return response()->json(['status'=>429,'response'=>'Too Many Requests','message' => 'Too many requests, please try again later.'], 429);
        }
        RateLimiter::hit($throttleKey, 60); // Reset allowed every 60 seconds

        $user = User::where('email',$request->email)->first();
        if (!$user) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'E-mail does not exist.'], 404);
        }

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
        $url = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email  // Including the email in the URL
        ], false));
        $expire = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');
        // Send the code to the user
        Mail::to($user->email)->send(new ResetPassword($user, $token, $url,$expire));

        return response()->json(['status'=>200,'response'=>'Successful','message' => 'Reset link has been sent to your email.']);
    }

    public function sendOTP(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email']);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }

        // Throttle OTP attempts
        $throttleKey = 'otp-request:' . $request->ip();
        if (!RateLimiter::remaining($throttleKey, $maxAttempts = 5)) {
            return response()->json(['status' => 429, 'response' => 'Too Many Requests', 'message' => 'Too many requests, please try again later.'], 429);
        }
        RateLimiter::hit($throttleKey, 60); // OTP allowed every 60 seconds

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'E-mail does not exist.'], 404);
        }

        $otp = rand(100000, 999999); // Generate a 6-digit OTP

        DB::table('otp_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'otp' => $otp,
                'created_at' => now()
            ]
        );
        $expire = config('otp.expire');

        // Send the OTP to the user
        Mail::to($user->email)->send(new SendOTP($user, $otp, $expire)); // Assuming you have a SendOTP Mailable setup

        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'OTP has been sent to your email.']);
    }

    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email', 'otp' => 'required|numeric']);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }

        $record = DB::table('otp_tokens')->where('email', $request->email)->first();

        if (!$record) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'OTP request not found.'], 404);
        }

        $expire = config('otp.expire');

        // Check if OTP is correct and not expired (5 minutes expiry)
        $otpIsValid = $record->otp == $request->otp;
        $otpIsNotExpired = now()->subMinutes($expire)->lessThan($record->created_at);

        if (!$otpIsValid || !$otpIsNotExpired) {
            return response()->json(['status' => 400, 'response' => 'Bad Request', 'message' => 'Invalid or expired OTP.'], 400);
        }

        // Optionally delete the record from password_resets table
        DB::table('otp_tokens')->where('email', $request->email)->update(['is_verified'=>true]);
        // OTP is correct and not expired, proceed with user verification or any other process
        return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'OTP verified successfully.']);
    }


}
