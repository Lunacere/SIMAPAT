<?php

namespace App\Http\Controllers;

use App\Http\Requests\FollowUpRequest;
use App\Models\FollowUp;
use App\Models\Meeting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'client', 403);

        $filters = $request->validate(['status' => ['nullable', Rule::in(['active'])]]);
        $tasks = FollowUp::where('user_id', $request->user()->id)->with('meeting');
        if (($filters['status'] ?? null) === 'active') {
            $tasks->where('status', '!=', 'completed')->whereHas('meeting', fn ($query) => $query->where('status', '!=', 'cancelled'));
        }

        return view('tasks', ['tasks' => $tasks->orderBy('deadline')->paginate(15)->withQueryString()]);
    }

    public function store(FollowUpRequest $request, Meeting $meeting): RedirectResponse
    {
        DB::transaction(function () use ($request, $meeting): void {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->assertHasMinutes($meeting);
            $meeting->followUps()->create($request->validated());
        });

        return back()->with('success', 'Tindak lanjut berhasil ditambahkan.');
    }

    public function update(FollowUpRequest $request, Meeting $meeting, FollowUp $followUp): RedirectResponse
    {
        abort_unless($followUp->meeting_id === $meeting->id, 404);
        DB::transaction(function () use ($request, $meeting, $followUp): void {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->assertHasMinutes($meeting);
            $followUp->refresh()->update($request->validated());
        });

        return back()->with('success', 'Tindak lanjut diperbarui.');
    }

    private function assertHasMinutes(Meeting $meeting): void
    {
        if ($meeting->status === 'cancelled' || ! $meeting->minute()->exists()) {
            throw ValidationException::withMessages(['task' => 'Simpan notulensi sebelum membuat atau mengubah tindak lanjut.']);
        }
    }

    public function status(Request $request, Meeting $meeting, FollowUp $followUp): RedirectResponse
    {
        abort_if($request->user()->role === 'client', 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(FollowUp::STATUSES))],
            'user_id' => ['prohibited'], 'task' => ['prohibited'], 'deadline' => ['prohibited'], 'meeting_id' => ['prohibited'],
        ]);
        DB::transaction(function () use ($request, $meeting, $followUp, $data): void {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $followUp->refresh();
            abort_unless($followUp->meeting_id === $meeting->id && $followUp->user_id === $request->user()->id, 403);
            if ($meeting->status === 'cancelled') {
                throw ValidationException::withMessages(['status' => 'Tugas pada rapat batal tidak dapat diubah.']);
            }
            $followUp->update($data);
        });

        return back()->with('success', 'Status tugas diperbarui.');
    }

    public function destroy(Meeting $meeting, FollowUp $followUp): RedirectResponse
    {
        Gate::authorize('update', $meeting);
        abort_unless($followUp->meeting_id === $meeting->id, 404);
        DB::transaction(function () use ($meeting, $followUp): void {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->assertHasMinutes($meeting);
            $followUp->delete();
        });

        return back()->with('success', 'Tindak lanjut dihapus.');
    }
}
