<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\GenericMail;
use App\Models\Employer;
use App\Models\EmployerProfile;
use App\Models\User;
use App\Services\EmailService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class EmployerController extends Controller
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly SmsService $smsService,
    ) {}

    public function index()
    {
        return response()->json(Employer::query()->latest()->get(), 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:employers,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'category' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'in:potential,pending,working,active,approved,inactive'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['status'] = $this->normalizeStatus($data['status'] ?? 'potential');

        $employer = Employer::query()->create($data);

        return response()->json([
            'message' => 'Employer created successfully',
            'employer' => $employer,
        ], 201);
    }

    public function directory()
    {
        $directEmployers = Employer::query()->latest()->get()->map(function (Employer $employer) {
            return $this->formatEmployer($employer);
        });

        $approvedUsers = User::query()
            ->where('role', 'employer')
            ->whereIn('status', ['approved', 'working'])
            ->with('employerProfile')
            ->get();

        $systemEmployers = $approvedUsers->map(function (User $user) {
            $profile = $user->employerProfile;

            if (! $profile) {
                return null;
            }

            $jobs = data_get($profile, 'preferred_job_categories', []);
            $category = is_array($jobs) ? ($jobs[0] ?? 'General') : (is_string($jobs) ? $jobs : 'General');

            return [
                'id' => 'user-'.$user->id,
                'company_name' => $profile->company_name ?? $user->name,
                'contact_person' => $user->name ?? $profile->company_name,
                'email' => $profile->company_email ?? $user->email,
                'phone' => $profile->company_phone ?? $user->phone,
                'category' => $category,
                'status' => 'working',
                'location' => $profile->company_location,
                'notes' => $profile->company_description,
                'approved' => true,
            ];
        })->filter()->values();

        $records = $directEmployers->concat($systemEmployers)
            ->unique(fn ($employer) => $employer['email'] ?: ($employer['company_name'].'-'.$employer['id']))
            ->values();

        $summary = [
            'total' => $records->count(),
            'potential' => $records->filter(fn ($employer) => $this->normalizeStatus($employer['status']) === 'potential')->count(),
            'pending' => $records->filter(fn ($employer) => $this->normalizeStatus($employer['status']) === 'pending')->count(),
            'working' => $records->filter(fn ($employer) => $this->normalizeStatus($employer['status']) === 'working')->count(),
        ];

        return response()->json([
            'data' => [
                'summary' => $summary,
                'items' => $records,
            ],
        ]);
    }

    public function sendGroupMessage(Request $request)
    {
        $data = $request->validate([
            'status' => ['required', 'in:potential,pending,working'],
            'channel' => ['required', 'in:email,sms'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $directory = $this->directory()->getData(true)['data']['items'] ?? [];
        $matches = collect($directory)->filter(fn ($employer) => $this->normalizeStatus($employer['status']) === $data['status'])->values();

        if ($data['channel'] === 'email') {
            $emails = $matches->pluck('email')->filter()->unique()->values()->all();
            $sent = 0;
            $failed = [];

            foreach ($emails as $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failed[] = $email;
                    continue;
                }

                $result = $this->emailService->send(
                    $email,
                    $data['subject'] ?? 'Thank you for partnering with us',
                    $data['message'],
                    config('app.name', 'TaziJobs')
                );

                if ($result) {
                    $sent++;
                } else {
                    $failed[] = $email;
                }
            }

            return response()->json([
                'message' => 'Bulk email message processed.',
                'data' => ['sent' => $sent, 'failed' => $failed, 'channel' => 'email', 'status' => $data['status']],
            ]);
        }

        $phones = $matches->pluck('phone')->filter()->map(fn ($value) => preg_replace('/\D+/', '', (string) $value))->filter(fn ($value) => strlen($value) >= 9)->unique()->values()->all();
        $sent = 0;
        $failed = [];

        foreach ($phones as $phone) {
            try {
                $this->smsService->send($phone, $data['message']);
                $sent++;
            } catch (\Throwable $exception) {
                $failed[] = $phone;
            }
        }

        return response()->json([
            'message' => 'Bulk SMS message processed.',
            'data' => ['sent' => $sent, 'failed' => $failed, 'channel' => 'sms', 'status' => $data['status']],
        ]);
    }

    protected function formatEmployer(Employer $employer): array
    {
        return [
            'id' => $employer->id,
            'company_name' => $employer->company_name,
            'contact_person' => $employer->contact_person,
            'email' => $employer->email,
            'phone' => $employer->phone,
            'category' => $employer->category,
            'status' => $this->normalizeStatus($employer->status),
            'location' => $employer->location ?? null,
            'notes' => $employer->notes ?? null,
            'approved' => in_array($this->normalizeStatus($employer->status), ['working'], true),
        ];
    }

    protected function normalizeStatus(?string $status): string
    {
        $status = strtolower((string) ($status ?? 'potential'));

        if (in_array($status, ['approved', 'active', 'working'], true)) {
            return 'working';
        }

        if (in_array($status, ['pending'], true)) {
            return 'pending';
        }

        return 'potential';
    }
}
