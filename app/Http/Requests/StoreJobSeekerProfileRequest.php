<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobSeekerProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'location' => ['nullable', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'county' => ['required', 'string', 'max:255'],
            'subcounty' => ['required', 'string', 'max:255'],
            'parish' => ['required', 'string', 'max:255'],
            'village' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['string', 'max:100', Rule::exists('languages', 'name')->where('is_active', true)],
            'religion' => ['required', 'string', 'max:100', Rule::exists('religions', 'name')->where('is_active', true)],
            'education_level' => ['nullable', 'string', 'max:255'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'bio' => ['nullable', 'string'],
            'work_experience' => ['nullable', 'string'],
            'preferred_job_categories' => ['nullable', 'array'],
            'preferred_job_categories.*' => ['string', 'max:100', Rule::exists('job_categories', 'name')->where('is_active', true)],
            'cv_file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'lc1_letter_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'id_document_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'id_document_front_file' => ['required', 'image', 'max:4096'],
            'id_document_back_file' => ['required', 'image', 'max:4096'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
            'terms_accepted' => ['accepted'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }
}
