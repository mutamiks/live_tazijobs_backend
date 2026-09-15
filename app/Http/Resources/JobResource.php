<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // 🔒 Verify if the user is authenticated and has permission (Admin or Employer)
        $showPhone = $request->user() && $request->user()->canSeeContactDetails();

        return [
            'id' => $this->id,
            'employer_id' => $this->employer_id,
            'title' => $this->title,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'responsibilities' => $this->responsibilities,
            'location' => $this->location,
            'job_type' => $this->job_type,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'deadline' => $this->deadline ? $this->deadline->toDateString() : null,
            'status' => $this->status,
            'rejection_reason' => $this->when($request->user() && $request->user()->isAdmin(), $this->rejection_reason),
            
            // 🚫 Hidden completely for job seekers and guests. Visible only to employers and admins.
            'contact_phone' => $this->when($showPhone, $this->contact_phone),
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
