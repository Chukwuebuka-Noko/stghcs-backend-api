<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;

class ResetPasswordController extends Controller
{
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required',
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
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }
        
        $record = DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->where('token', $request->token)
                    ->first();
    
        if (!$record || now()->subMinutes(1440)->greaterThan($record->created_at)) {
            return response()->json(['status'=>422,'response'=>'Token Expired','message' => 'Invalid or expired reset code.'], 422);
        }
    
        $user = User::where('email', $request->email)->firstOrFail();
        $user->password = Hash::make($request->password);
        $user->is_temporary_password = false;
        $user->save();
    
        // Optionally delete the record from password_resets table
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
    
        return response()->json(['status'=>200,'response'=>'Successful','message' => 'Password has been successfully reset.'],200);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required',
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
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }
        
        $record = DB::table('otp_tokens')
                    ->where('email', $request->email)
                    ->where('otp', $request->otp)
                    ->first();
    
        if ($record->is_verified != true) {
            return response()->json(['status'=>422,'response'=>'Invalid OTP','message' => 'Invalid or expired reset code.'], 422);
        }
    
        $user = User::where('email', $request->email)->firstOrFail();
        $user->password = Hash::make($request->password);
        $user->is_temporary_password = false;
        $user->save();
    
        // Optionally delete the record from password_resets table
        DB::table('otp_tokens')->where('email', $request->email)->delete();
    
        return response()->json(['status'=>200,'response'=>'Successful','message' => 'Password has been successfully reset.'],200);
    }

}
