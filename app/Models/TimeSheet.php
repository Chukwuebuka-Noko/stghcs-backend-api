<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TimeSheet extends Model
{
    use SoftDeletes, HasFactory;
    protected $fillable = [
        'user_id',
        'gig_id',
        'activities',
        'unique_id',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gigs()
    {
        return $this->belongsTo(Gig::class,'gig_id','id');
    }
    
    public function assignments()
    {
        return $this->hasMany(AssignGig::class,'gig_id');
    }

    // Define relationship with WeekLog
    public function weekLog()
    {
        return $this->hasMany(WeekLog::class, 'timesheet_id', 'unique_id');
    }

    public function incidents_report()
    {
        return $this->hasMany(IncidentReport::class, 'timesheet_id');
    }
    
    public function progress_report()
    {
        return $this->hasMany(ProgressReport::class, 'timesheet_id');
    }
}
