<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasApiTokens, HasFactory, SoftDeletes, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'other_name',
        'email',
        'phone_number',
        'location_id',
        'gender',
        'address1',
        'address2',
        'city',
        'zip_code',
        'dob',
        'employee_id',
        'password',
        'points',
        'ssn',
        'id_card',
        'passport',
        'status',
        'verification_code'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];



    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Send the email verification notification.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPasswordNotification($token));
    }

    public function gigs()
    {
        return $this->belongsToMany(Gig::class);
    }
    public function assigned_gig()
    {
        return $this->hasMany(AssignGig::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class); // Assuming users have a location
    }

    public function created_gigs()
    {
        return $this->hasMany(Gig::class, 'created_by');
    }
    
    public function created_clients()
    {
        return $this->hasMany(Client::class, 'created_by');
    }

    public function timeSheets()
    {
        return $this->hasMany(TimeSheet::class);
    }

    // User has many Reward Point Logs
    public function rewardPointLogs()
    {
        return $this->hasMany(RewardPointLog::class);
    }

    public function incident_report()
    {
        return $this->hasMany(IncidentReport::class);
    }

    public function weekLogs() {
        return $this->hasMany(WeekLog::class);
    }
    public function gigsInCharge()
    {
        return $this->hasMany(Gig::class, 'supervisor_id', 'id');
    }
    
    public function supervisor_in_charges()
    {
        return $this->hasMany(SupervisorInCharge::class, 'user_id');
    }
}
