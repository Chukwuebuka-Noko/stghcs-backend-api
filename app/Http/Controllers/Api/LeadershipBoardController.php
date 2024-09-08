<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LeadershipBoardController extends Controller
{
    public function leadershipboard()
    {
        $users = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['DSW', 'CSP']);
        })
        ->orderBy('points', 'desc')
        ->get();

        $rank = 1;
        $last_points = null;
        $skip_next = 0;

        $users = $users->map(function ($user) use (&$rank, &$last_points, &$skip_next) {
            if ($last_points === $user->points) {
                $skip_next++;
            } else {
                $rank += $skip_next;
                $last_points = $user->points;
                $skip_next = 1;
            }

            return [
                'name' => $user->last_name.' '.$user->first_name,
                'points' => $user->points,
                'rank' => $rank
            ];
        });

        return response()->json($users);
    }
}
