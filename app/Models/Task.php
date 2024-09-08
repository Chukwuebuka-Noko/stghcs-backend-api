<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'description',
        'location',
        'start_date',
        'end_date',
        'assigned_to',
        'created_by',
        'status'
    ];
}
