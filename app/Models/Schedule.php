<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;
    protected $fillable = [
        'gig_id',
        'days',
        'schedule'
    ];
    public function gig()
    {
        return $this->belongsToMany(Gig::class);
    }
}
