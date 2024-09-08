<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanOfCare extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'client_id',
        'needed_support',
        'anticipated_outcome',
        'services_area_frequency',
        'review_date',
        'status'
    ];
}
