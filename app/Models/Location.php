<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Location extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'city',
        'zip_code',
        'address1',
        'address2',
        'coordinate'
    ];

    public function clients() {
        return $this->hasMany(Client::class);
    }

    public function users() {
        return $this->hasMany(User::class);
    }
}
