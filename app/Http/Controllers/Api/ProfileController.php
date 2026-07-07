<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployerProfileRequest;
use App\Http\Requests\StoreJobSeekerProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function submitJobSeeker(StoreJobSeekerProfileRequest $request)
    {
        $data = $request->validated();
        $data['cv_file'] = $request->file('cv_file')?->store('job-seeker-cvs', 'public') ?? ($data['cv_file'] ?? null);
        $data['lc1_letter_file'] = $request->file('lc1_letter_file')?->store('lc1-letters', 'public') ?? ($data['lc1_letter_file'] ?? null);
        $data['id_document_file'] = $request->file('id_document_file')?->store('id-documents', 'public') ?? ($data['id_document_file'] ?? null);
        $data['id_document_front_file'] = $request->file('id_document_front_file')?->store('id-documents', 'public') ?? ($data['id_document_front_file'] ?? null);
        $data['id_document_back_file'] = $request->file('id_document_back_file')?->store('id-documents', 'public') ?? ($data['id_document_back_file'] ?? null);
        $data['profile_photo'] = $request->file('profile_photo')?->store('profile-photos', 'public') ?? ($data['profile_photo'] ?? null);

        $profile = $request->user()->jobSeekerProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data + [
                'status' => 'pending',
                'rejection_reason' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]
        );

        if ($request->user()->status !== 'suspended') {
            $request->user()->forceFill(['status' => 'pending'])->save();
        }

        return response()->json(['message' => 'Job seeker profile submitted.', 'data' => $profile], 201);
    }

    public function submitEmployer(StoreEmployerProfileRequest $request)
    {
        $data = $request->validated();
        $data['company_logo'] = $request->file('company_logo')?->store('company-logos', 'public') ?? ($data['company_logo'] ?? null);
        $data['business_document_file'] = $request->file('business_document_file')?->store('business-documents', 'public') ?? ($data['business_document_file'] ?? null);

        $profile = $request->user()->employerProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data + [
                'status' => 'pending',
                'rejection_reason' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]
        );

        if ($request->user()->status !== 'suspended') {
            $request->user()->forceFill(['status' => 'pending'])->save();
        }

        return response()->json(['message' => 'Employer profile submitted.', 'data' => $profile], 201);
    }

    public function file(Request $request, string $field)
    {
        $user = $request->user();
        $profile = $user->role === 'employer' ? $user->employerProfile : $user->jobSeekerProfile;

        $allowedFields = $user->role === 'employer'
            ? ['company_logo', 'business_document_file']
            : ['profile_photo', 'cv_file', 'lc1_letter_file', 'id_document_file', 'id_document_front_file', 'id_document_back_file'];

        abort_unless($profile && in_array($field, $allowedFields, true), 404);

        $path = $profile->{$field};
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        $response = Storage::disk('public')->response($path);
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }
}
