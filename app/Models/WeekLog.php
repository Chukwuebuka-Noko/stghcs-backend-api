<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeekLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'week_number',
        'year',
        'timesheet_id',
        'day',
        'time',
        'type',
        'report_id',
        'activity_id'
    ];

    // Define relationship with TimeSheet
    public function timeSheet()
    {
        return $this->belongsTo(TimeSheet::class, 'timesheet_id', 'unique_id');
    }

    // You might also define a relationship to User if not already defined
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    /*public function incidentReports() {
        return $this->hasMany(IncidentReport::class);
    }*/
    public function incidentReports()
    {
        return $this->hasMany(IncidentReport::class, 'timesheet_id', 'timesheet_id');
    }
    
    public function progressReports()
{
    return $this->hasMany(ProgressReport::class, 'timesheet_id', 'timesheet_id')
                ->where('activity_id', $this->activity_id);
}
}
