<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployerProfileRequest;
use App\Http\Requests\StoreJobSeekerProfileRequest;
use App\Services\SmsService;
use App\Support\NotifiesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    use NotifiesUsers;

    public function submitJobSeeker(StoreJobSeekerProfileRequest $request)
    {
        $data = $request->validated();
        if (filled($data['phone'] ?? null)) {
            $data['phone'] = '+'.app(SmsService::class)->normalizePhone($data['phone']);
        }
        $data['cv_file'] = $request->file('cv_file')?->store('job-seeker-cvs', 'public') ?? ($data['cv_file'] ?? null);
        $data['lc1_letter_file'] = $request->file('lc1_letter_file')?->store('lc1-letters', 'public') ?? ($data['lc1_letter_file'] ?? null);
        $data['id_document_file'] = $request->file('id_document_file')?->store('id-documents', 'public') ?? ($data['id_document_file'] ?? null);
        $data['id_document_front_file'] = $request->file('id_document_front_file')?->store('id-documents', 'public') ?? ($data['id_document_front_file'] ?? null);
        $data['id_document_back_file'] = $request->file('id_document_back_file')?->store('id-documents', 'public') ?? ($data['id_document_back_file'] ?? null);
        if ($photo = $request->file('profile_photo')) {
            [$data['profile_photo'], $data['profile_photo_thumbnail']] = $this->storePassportPhoto($photo);
        }

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

        $this->notifyProfileUnderReview(
            $request->user(),
            'job_seeker_profile_under_review',
            'Worker profile under review',
            'Your TaziJobs worker account has been created and is under admin review.',
            'TaziJobs: Your worker account was created and is under review.'
        );

        return response()->json(['message' => 'Job seeker profile submitted.', 'data' => $profile], 201);
    }

    public function submitEmployer(StoreEmployerProfileRequest $request)
    {
        $data = $request->validated();
        if (filled($data['company_phone'] ?? null)) {
            $data['company_phone'] = '+'.app(SmsService::class)->normalizePhone($data['company_phone']);
        }
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

        $this->notifyProfileUnderReview(
            $request->user(),
            'employer_profile_under_review',
            'Employer account under review',
            'Your TaziJobs employer account has been created and is under admin review.',
            'TaziJobs: Your employer account was created and is under review.'
        );

        return response()->json(['message' => 'Employer profile submitted.', 'data' => $profile], 201);
    }

    private function notifyProfileUnderReview($user, string $type, string $title, string $message, string $sms): void
    {
        $this->notifyUser($user, $type, $title, $message);

        if (filled($user->phone)) {
            try {
                app(SmsService::class)->send($user->phone, Str::limit($sms, 159, ''));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    public function file(Request $request, string $field)
    {
        $user = $request->user();
        $profile = $user->role === 'employer' ? $user->employerProfile : $user->jobSeekerProfile;

        $allowedFields = $user->role === 'employer'
            ? ['company_logo', 'business_document_file']
            : ['profile_photo', 'profile_photo_thumbnail', 'cv_file', 'lc1_letter_file', 'id_document_file', 'id_document_front_file', 'id_document_back_file'];

        abort_unless($profile && in_array($field, $allowedFields, true), 404);

        $path = $profile->{$field};
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        $response = Storage::disk('public')->response($path);
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }

    private function storePassportPhoto($file): array
    {
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages(['profile_photo' => 'Photo processing is not available on the server.']);
        }

        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $source) {
            throw ValidationException::withMessages(['profile_photo' => 'The passport photo could not be processed.']);
        }

        $store = function (int $width, int $height, string $folder) use ($source): string {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $targetRatio = $width / $height;
            $sourceRatio = $sourceWidth / $sourceHeight;
            $cropWidth = $sourceRatio > $targetRatio ? (int) ($sourceHeight * $targetRatio) : $sourceWidth;
            $cropHeight = $sourceRatio > $targetRatio ? $sourceHeight : (int) ($sourceWidth / $targetRatio);
            $cropX = (int) (($sourceWidth - $cropWidth) / 2);
            $cropY = (int) (($sourceHeight - $cropHeight) / 2);
            $canvas = imagecreatetruecolor($width, $height);
            imagecopyresampled($canvas, $source, 0, 0, $cropX, $cropY, $width, $height, $cropWidth, $cropHeight);
            ob_start();
            imagejpeg($canvas, null, 82);
            $contents = ob_get_clean();
            imagedestroy($canvas);
            $path = $folder.'/'.str()->uuid().'.jpg';
            Storage::disk('public')->put($path, $contents);
            return $path;
        };

        $paths = [$store(300, 400, 'profile-photos'), $store(96, 128, 'profile-photo-thumbnails')];
        imagedestroy($source);

        return $paths;
    }
}
