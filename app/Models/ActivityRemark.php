<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityRemark extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'gig_id',
        'timesheet_id',
        'activity_id',
        'remark'
    ];
}
