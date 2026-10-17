<?php

namespace App\Http\Controllers;

use App\Http\Requests\MeetingRequest;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingScheduler;
use App\Services\ReminderDelivery;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MeetingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'], 'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'participant' => ['nullable', 'integer', 'exists:users,id'],
            'client' => ['nullable', 'integer', 'exists:clients,id'],
            'status' => ['nullable', Rule::in(array_keys(Meeting::STATUSES))],
            'upcoming' => ['nullable', 'boolean'],
        ]);
        $query = Meeting::visibleTo($request->user());
        if ($request->routeIs('history')) {
            $query->where(function ($query): void {
                $query->where('ends_at', '<', now())->orWhereIn('status', ['completed', 'cancelled']);
            });
        }
        $query->when($filters['q'] ?? null, fn ($query, $value) => $query->where('title', 'like', '%'.$value.'%'))
            ->when($request->boolean('upcoming'), fn ($query) => $query->where('status', 'scheduled')->where('starts_at', '>=', now()))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->where('starts_at', '>=', Carbon::parse($value)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->where('starts_at', '<=', Carbon::parse($value)->endOfDay()))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['client'] ?? null, fn ($query, $value) => $query->where('client_id', $value))
            ->when($filters['participant'] ?? null, fn ($query, $value) => $query->whereHas('participants', fn ($query) => $query->where('user_id', $value)));
        $visible = Meeting::visibleTo($request->user())->select('id');

        return view('meetings.index', [
            'meetings' => $query->with(['client', 'creator', 'minute', 'followUps.user'])->withCount('participants')->orderByDesc('starts_at')->paginate(12)->withQueryString(),
            'clients' => Client::whereHas('meetings', fn ($query) => $query->whereIn('id', clone $visible))->orderBy('name')->get(),
            'users' => $request->user()->role === 'client' ? collect() : User::whereHas('participants', fn ($query) => $query->whereIn('meeting_id', clone $visible))->orderBy('name')->get(),
            'report' => false,
        ]);
    }

    public function report(Request $request): View
    {
        abort_unless($request->user()->role === 'admin', 403);
        $response = $this->index($request);
        $response->with('report', true);

        return $response;
    }

    public function create(): View
    {
        Gate::authorize('create', Meeting::class);

        return $this->form(new Meeting);
    }

    public function edit(Meeting $meeting): View
    {
        Gate::authorize('update', $meeting);
        abort_unless($meeting->status === 'scheduled', 409, 'Rapat yang sudah ditutup tidak dapat dijadwalkan ulang.');

        return $this->form($meeting);
    }

    private function form(Meeting $meeting): View
    {
        return view('meetings.form', ['meeting' => $meeting->load(['participants', 'agenda']),
            'users' => User::orderBy('name')->get(), 'clients' => Client::orderBy('name')->get(),
            'requiresNegotiation' => $meeting->exists && $meeting->client_id !== null && $meeting->client->accounts()->where('role', 'client')->exists()]);
    }

    public function store(MeetingRequest $request, MeetingScheduler $scheduler): RedirectResponse
    {
        $meeting = $scheduler->save($request->user(), $this->scheduleData($request));

        return redirect()->route('meetings.show', $meeting)->with('success', 'Rapat berhasil dijadwalkan.');
    }

    public function update(MeetingRequest $request, Meeting $meeting, MeetingScheduler $scheduler): RedirectResponse
    {
        $scheduler->save($request->user(), $this->scheduleData($request), $meeting);

        return redirect()->route('meetings.show', $meeting)->with('success', 'Jadwal, agenda, dan peserta berhasil diperbarui.');
    }

    private function scheduleData(MeetingRequest $request): array
    {
        $data = $request->validated();
        foreach (['starts_at', 'ends_at'] as $field) {
            $data[$field] = Carbon::parse($data[$field])->format('Y-m-d H:i:s');
        }

        return $data;
    }

    public function show(Meeting $meeting): View|RedirectResponse
    {
        Gate::authorize('view', $meeting);
        if (auth()->user()->role === 'client') {
            return redirect()->route('negotiations.show', $meeting);
        }

        return view('meetings.show', ['meeting' => $meeting->load(['client', 'creator', 'participants.user', 'agenda', 'minute', 'followUps.user'])]);
    }

    public function cancel(Meeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);
        DB::transaction(function () use ($meeting): void {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            if ($meeting->status !== 'scheduled') {
                throw ValidationException::withMessages(['status' => 'Hanya rapat terjadwal yang dapat dibatalkan.']);
            }
            $meeting->update(['status' => 'cancelled', 'version' => $meeting->version + 1]);
            $meeting->scheduleProposals()->where('status', 'pending')->update(['status' => 'superseded']);
            app(ReminderDelivery::class)->schedule($meeting);
        });

        return back()->with('success', 'Rapat dibatalkan. Riwayat tetap tersimpan.');
    }

    public function complete(Meeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);
        DB::transaction(function () use ($meeting): void {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            if ($meeting->status !== 'scheduled' || $meeting->ends_at->isFuture() || ! $meeting->minute()->exists()) {
                throw ValidationException::withMessages(['status' => 'Rapat dapat diselesaikan setelah waktunya berakhir dan notulensi tersimpan.']);
            }
            $meeting->update(['status' => 'completed']);
            app(ReminderDelivery::class)->schedule($meeting);
        });

        return back()->with('success', 'Rapat selesai. Tindak lanjut tetap dapat dikerjakan.');
    }
}
