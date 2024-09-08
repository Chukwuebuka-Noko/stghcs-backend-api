<?php

namespace App\Http\Requests;

use App\Rules\ValidZipCode;
use Illuminate\Foundation\Http\FormRequest;

class SetupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gender' => ['required','string'],
            'address1' => ['required','string'],
            'address2' => ['nullable','string'],
            'city' => ['required','string'],
            'zip_code' => ['required', 'string', 'regex:/^\d{5}(-\d{4})?$/', new ValidZipCode],
            'dob' => ['nullable'],
            'ssn' => ['required','regex:/^\d{3}-\d{2}-\d{4}$/'],
            'id_card' => ['nullable','file','mimes:jpeg,bmp,png'], // Specify the file types if needed
        ];
    }
}
