<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncidentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'incident_time',
        'incident_date',
        'incident_week_number',
        'incident_year',
        'gig_id',
        'user_id',
        'timesheet_id',
        'activity_id'
    ];
    public function gig()
    {
        return $this->belongsTo(Gig::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function weekLog()
    {
        return $this->belongsTo(WeekLog::class, 'timesheet_id', 'timesheet_id');
    }
}
