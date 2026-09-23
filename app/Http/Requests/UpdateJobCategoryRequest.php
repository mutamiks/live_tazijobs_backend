<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'skill_level' => ['sometimes', 'in:skilled,unskilled'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
