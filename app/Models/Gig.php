<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gig extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'description',
        'client_id',
        'created_by'
    ];
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
    public function assignments()
    {
        return $this->hasMany(AssignGig::class);
    }
}
