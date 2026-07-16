<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'employer_id' => ['sometimes', 'integer', 'exists:users,id'],
            'job_category_id' => ['required', 'integer', 'exists:job_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'positions' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'description' => ['required', 'string'],
            'requirements' => ['nullable', 'string'],
            'responsibilities' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'subcounty' => ['nullable', 'string', 'max:255'],
            'parish' => ['nullable', 'string', 'max:255'],
            'village' => ['nullable', 'string', 'max:255'],
            'job_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'internship', 'remote'])],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'deadline' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
