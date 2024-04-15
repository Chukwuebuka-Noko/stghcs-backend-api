<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
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
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Throttle reset attempts
        $throttleKey = 'password-reset:' . $request->ip();
        if (!RateLimiter::remaining($throttleKey, $maxAttempts = 5)) {
            return response()->json(['message' => 'Too many requests, please try again later.'], 429);
        }
        RateLimiter::hit($throttleKey, 60); // Reset allowed every 60 seconds

        $user = User::where('email',$request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'E-mail does not exist.'], 404);
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

        return response()->json(['message' => 'Reset link has been sent to your email.']);
    }
}
