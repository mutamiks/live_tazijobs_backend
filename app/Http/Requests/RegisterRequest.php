<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required_if:role,employer', 'nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required_if:role,job_seeker', 'nullable', 'string', 'max:50', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['job_seeker', 'employer'])],
            'terms_accepted' => ['required', 'accepted'],
        ];
    }
}
