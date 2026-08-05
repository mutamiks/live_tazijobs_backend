<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTicketController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'client_type' => ['nullable', Rule::in(['job_seeker', 'employer'])],
            'ticket_type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        return response()->json([
            'data' => Ticket::query()
                ->with('creator:id,name')
                ->when($request->query('client_type'), fn ($query, string $type) => $query->where('client_type', $type))
                ->when($request->query('ticket_type'), fn ($query, string $type) => $query->where('ticket_type', 'like', "%{$type}%"))
                ->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status))
                ->when($request->query('search'), function ($query, string $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('client_full_name', 'like', "%{$search}%")
                            ->orWhere('phones', 'like', "%{$search}%")
                            ->orWhere('client_type', 'like', "%{$search}%")
                            ->orWhere('ticket_type', 'like', "%{$search}%")
                            ->orWhere('comment', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate($perPage),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_full_name' => ['required', 'string', 'max:255'],
            'phones' => ['required', 'array', 'min:1'],
            'phones.*' => ['required', 'string', 'max:30'],
            'client_type' => ['required', Rule::in(['job_seeker', 'employer'])],
            'ticket_type' => ['required', 'string', 'max:100'],
            'comment' => ['required', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $ticket = Ticket::query()->create([
            ...$data,
            'phones' => collect($data['phones'])->map(fn ($phone) => trim($phone))->filter()->values()->all(),
            'status' => $data['status'] ?? 'open',
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Ticket added.', 'data' => $ticket->load('creator:id,name')], 201);
    }
}
