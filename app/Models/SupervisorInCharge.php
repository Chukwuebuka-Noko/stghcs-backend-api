<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupervisorInCharge extends Model
{
    use HasFactory;
    protected $fillable = [
        'supervisor_id',
        'user_id',
    ];
    
    // Define the relationships
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'id');
    }

    public function dsw()
    {
        return $this->belongsTo(User::class, 'id');
    }
}
