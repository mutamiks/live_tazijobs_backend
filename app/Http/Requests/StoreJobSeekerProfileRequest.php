<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobSeekerProfileRequest extends FormRequest
{
    private const EDUCATION_LEVELS = [
        'Primary Level',
        'Secondary O Level',
        'Secondary A Level',
        'Tertiary Level',
        'University Level',
    ];

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:'.now()->subYears(15)->toDateString()],
            'location' => ['nullable', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'county' => ['required', 'string', 'max:255'],
            'subcounty' => ['required', 'string', 'max:255'],
            'parish' => ['required', 'string', 'max:255'],
            'village' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^(?:\+256|256|0)?7\d{8}$/'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['string', 'max:100', Rule::exists('languages', 'name')->where('is_active', true)],
            'religion' => ['required', 'string', 'max:100', Rule::exists('religions', 'name')->where('is_active', true)],
            'education_level' => ['nullable', Rule::in(self::EDUCATION_LEVELS)],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'bio' => ['nullable', 'string'],
            'work_experience' => ['nullable', 'string'],
            'preferred_job_categories' => ['nullable', 'array'],
            'preferred_job_categories.*' => ['string', 'max:100', Rule::exists('job_categories', 'name')->where('is_active', true)],
            'cv_file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'lc1_letter_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'id_document_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'id_document_front_file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'id_document_back_file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'terms_accepted' => ['accepted'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }
}
