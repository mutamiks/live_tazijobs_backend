<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email', 'required_without:login'],
            'login' => ['nullable', 'string', 'required_without:email'],
            'password' => ['required', 'string'],
        ];
    }
}
