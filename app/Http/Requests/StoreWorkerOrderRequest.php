<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkerOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'job_seeker_profile_id' => ['required', 'exists:job_seeker_profiles,id'],
            'salary_offered' => ['required', 'numeric', 'min:0'],
            'job_location' => ['required', 'string', 'max:255'],
            'working_terms' => ['required', 'string'],
            'allowances' => ['nullable', 'string'],
            'job_description' => ['required', 'string'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
