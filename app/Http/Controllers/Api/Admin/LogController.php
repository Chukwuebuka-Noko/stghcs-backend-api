<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    public function logs(){
        $logs = ActivityLog::get();
        if ($logs->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Log(s) does not exist'], 404);
        }
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Logs fetched successfully","data"=>$logs],200);
    }
}
