<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Schedule extends Model
{
    use SoftDeletes, HasFactory;
    protected $fillable = [
        'gig_id',
        'gig_unique_id',
        'days',
        'schedule'
    ];
    public function gig()
    {
        // return $this->belongsToMany(Gig::class);
        return $this->belongsTo(Gig::class, 'gig_id','id');
    }
}
