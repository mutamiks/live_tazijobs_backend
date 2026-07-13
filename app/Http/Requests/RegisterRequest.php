<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('phone')) {
            return;
        }

        $phone = preg_replace('/\D+/', '', (string) $this->input('phone'));
        if (str_starts_with($phone, '0')) {
            $phone = '256'.substr($phone, 1);
        } elseif (str_starts_with($phone, '7')) {
            $phone = '256'.$phone;
        }

        $this->merge(['phone' => '+'.$phone]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:/^(?:\+256|256|0)?7\d{8}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['job_seeker', 'employer'])],
            'terms_accepted' => ['required', 'accepted'],
        ];
    }
}
