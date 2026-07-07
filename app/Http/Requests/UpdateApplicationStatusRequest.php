<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['submitted', 'reviewed', 'shortlisted', 'rejected', 'hired'])],
            'employer_notes' => ['nullable', 'string'],
        ];
    }
}
