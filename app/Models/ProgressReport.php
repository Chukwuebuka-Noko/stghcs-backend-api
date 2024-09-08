<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgressReport extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'title',
        'description',
        'progress_time',
        'progress_date',
        'progress_week_number',
        'progress_year',
        'gig_id',
        'support_worker_id',
        'timesheet_id',
        'activity_id',
        'task_performed'
    ];
    public function gig()
    {
        return $this->belongsTo(Gig::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'support_worker_id', 'id');
    }
    
    public function weekLog()
    {
        return $this->belongsTo(WeekLog::class, 'timesheet_id', 'timesheet_id');
    }
}
