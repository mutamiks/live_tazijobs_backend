<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkerOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'job_seeker_profile_id' => ['nullable', 'exists:job_seeker_profiles,id', 'required_without:job_seeker_profile_ids'],
            'job_seeker_profile_ids' => ['nullable', 'array', 'min:1', 'required_without:job_seeker_profile_id'],
            'job_seeker_profile_ids.*' => ['integer', 'distinct', 'exists:job_seeker_profiles,id'],
            'salary_offered' => ['required', 'numeric', 'min:0'],
            'job_location' => ['required', 'string', 'max:255'],
            'working_terms' => ['required', 'string'],
            'allowances' => ['nullable', 'string'],
            'job_description' => ['required', 'string'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
