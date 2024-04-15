<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignGig extends Model
{
    use HasFactory;
    protected $fillable = [
        'gig_id',
        'user_id',
        'schedule_id'
    ];
    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship with Gig
    public function gig()
    {
        return $this->belongsTo(Gig::class);
    }

    // Optionally, if there's a relationship with Schedule
    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
