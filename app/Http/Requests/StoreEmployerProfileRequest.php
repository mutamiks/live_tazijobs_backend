<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployerProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_location' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'subcounty' => ['nullable', 'string', 'max:255'],
            'parish' => ['nullable', 'string', 'max:255'],
            'village' => ['nullable', 'string', 'max:255'],
            'company_registration_number' => ['nullable', 'string', 'max:255'],
            'company_description' => ['nullable', 'string'],
            'preferred_worker_type' => ['nullable', 'string', 'max:255'],
            'preferred_job_categories' => ['nullable', 'array'],
            'preferred_job_categories.*' => ['string', 'max:100', Rule::exists('job_categories', 'name')->where('is_active', true)],
            'company_logo' => ['nullable', 'image', 'max:2048'],
            'business_document_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'website' => ['nullable', 'url', 'max:255'],
        ];
    }
}
