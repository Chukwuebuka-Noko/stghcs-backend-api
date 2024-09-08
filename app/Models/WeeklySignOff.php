<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklySignOff extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'support_worker_id',
        'support_worker_signature',
        'client_signature',
        'sign_off_date',
        'sign_off_week_number',
        'sign_off_year',
        'sign_off_day',
        'gig_id',
        'timesheet_id',
        'sign_off_time'
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
