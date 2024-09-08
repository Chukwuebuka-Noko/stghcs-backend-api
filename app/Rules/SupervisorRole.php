<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Spatie\Permission\Models\Role;

class SupervisorRole implements Rule
{
    public function passes($attribute, $value)
    {
        $supervisor = \App\Models\User::find($value);

        if (!$supervisor) {
            return false;
        }

        return $supervisor->hasRole('Supervisor');
    }

    public function message()
    {
        return 'The selected supervisor must have the Supervisor role.';
    }
}
