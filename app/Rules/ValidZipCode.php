<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidZipCode implements ValidationRule
{
    protected $validZips = [
        'Alexandria' => ['71301', '71302', '71303', '71306', '71307', '71309', '71315'], // Add all relevant zip codes
        'Shreveport' => [
            '71101', '71102', '71103', '71104', '71105', '71106', '71107', '71108', '71109', 
            '71110', '71111', '71112', '71113', '71115', '71118', '71119', '71120', '71129', 
            '71130', '71133', '71134', '71135', '71136', '71137', '71138', '71148', '71149', 
            '71150', '71151', '71152', '71153', '71154', '71156', '71161', '71162', '71163', 
            '71164', '71165', '71166', '71171', '71172'
        ],
    ];
    
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
         // Check if the provided zip code is in any of the valid lists
         $found = false;
         foreach ($this->validZips as $city => $zips) {
             if (in_array($value, $zips)) {
                 $found = true;
                 break;
             }
         }
 
         // If not found, use the $fail closure to provide a custom failure message
         if (!$found) {
             $fail("The zip code {$value} is not valid for Alexandria or Shreveport.");
         }
    }
}
