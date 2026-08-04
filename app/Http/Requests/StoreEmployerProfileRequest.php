<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployerProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'employer_type' => ['nullable', Rule::in(['company', 'individual'])],
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'regex:/^(?:\+256|256|0)?7\d{8}$/'],
            'company_location' => ['nullable', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'county' => ['required', 'string', 'max:255'],
            'subcounty' => ['required', 'string', 'max:255'],
            'parish' => ['required', 'string', 'max:255'],
            'village' => ['required', 'string', 'max:255'],
            'company_registration_number' => ['nullable', 'string', 'max:255'],
            'company_description' => ['nullable', 'string'],
            'preferred_worker_type' => ['nullable', 'string', 'max:255'],
            'preferred_job_categories' => ['nullable', 'array'],
            'preferred_job_categories.*' => ['string', 'max:100', Rule::exists('job_categories', 'name')->where('is_active', true)],
            'company_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'business_document_file' => ['required_if:employer_type,company,individual', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'website' => ['nullable', 'url', 'max:255'],
        ];
    }
}
