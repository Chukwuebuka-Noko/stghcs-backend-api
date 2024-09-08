<?php

namespace App\Http\Controllers\Api\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class UserRewardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:DSW|CSP']);
    }

    // Display total and available points
    public function showPoints()
    {
        $user = User::find(auth('api')->user()->id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $totalPointsEarned = $user->rewardPointLogs()->sum('points');
        return response()->json(['total_points_earned' => $totalPointsEarned,'current_point' => $user->points]);
    }

    // Display a history of all point transactions
    public function history()
    {
        $user = User::find(auth('api')->user()->id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $user->load('rewardPointLogs');
        $history = $user->rewardPointLogs()->latest()->get(['title', 'points', 'created_at']);
        return response()->json($history);
    }
}
