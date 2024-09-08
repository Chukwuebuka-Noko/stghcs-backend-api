<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gig extends Model
{
    use SoftDeletes, HasFactory;
    // protected $primaryKey = 'gig_unique_id';
    // public $incrementing = false;
    // protected $keyType = 'string';
    protected $fillable = [
        'gig_unique_id',
        'title',
        'description',
        'client_id',
        'created_by',
        'grace_period',
        'gig_type_id',
        'gig_type',
        'gig_type_shortcode',
        'supervisor_id',
        'status',
        'start_date'
    ];
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
    // public function schedule()
    // {
    //     return $this->hasOne(Schedule::class, 'gig_id', 'gig_unique_id');
    // }
    // public function assignments()
    // {
    //     return $this->hasMany(AssignGig::class, 'gig_id', 'gig_unique_id');
    // }

    public function schedule()
    {
        return $this->hasOne(Schedule::class);
    }
    public function assignments()
    {
        return $this->hasMany(AssignGig::class);
    }
    
    public function timesheet()
    {
        return $this->hasOne(TimeSheet::class);
    }

    public function client() {
        return $this->belongsTo(Client::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }
    /*public function supervisor() {
        return $this->belongsTo(User::class, 'supervisor_id');
    }*/
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id', 'id');
    }
    public function incidents()
    {
        return $this->hasMany(IncidentReport::class);
    }
    
    public function gig_type()
    {
        return $this->hasOne(GigType::class,'id','gig_type_id');
    }
}
