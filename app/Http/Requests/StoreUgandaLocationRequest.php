<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUgandaLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'district' => ['required', 'string', 'max:255'],
            'county' => ['required', 'string', 'max:255'],
            'subcounty' => ['required', 'string', 'max:255'],
            'parish' => ['required', 'string', 'max:255'],
            'village' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
