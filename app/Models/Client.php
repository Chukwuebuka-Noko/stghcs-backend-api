<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model
{
    use SoftDeletes, HasFactory;
    protected $fillable = [
        'title',
        'first_name',
        'last_name',
        'other_name',
        'phone_number',
        'email',
        'dob',
        'location_id',
        'created_by',
        'plan_of_care',
        'city',
        'zip_code',
        'address1',
        'address2',
        'coordinate',
        'status'
    ];

    public function location() {
        return $this->belongsTo(Location::class);
    }

    public function gigs() {
        return $this->hasMany(Gig::class);
    }
    public function created_by() {
        return $this->belongsTo(User::class, 'created_by');
    }
}
