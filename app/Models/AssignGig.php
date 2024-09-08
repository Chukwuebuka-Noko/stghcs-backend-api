<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssignGig extends Model
{
    use SoftDeletes, HasFactory;
    protected $fillable = [
        'gig_id',
        'user_id',
        'schedule_id'
    ];
    // Relationship with User
    public function assignee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relationship with Gig using gig_id to reference gig_unique_id in Gig model
    // public function gig()
    // {
    //     return $this->belongsTo(Gig::class, 'gig_id', 'gig_unique_id');
    // }

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
