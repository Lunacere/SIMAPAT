<?php

namespace App\Http\Controllers;

use App\Http\Requests\MinuteRequest;
use App\Models\Meeting;
use App\Models\Participant;
use App\Services\ReminderDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeetingContentController extends Controller
{
    public function minutes(MinuteRequest $request, Meeting $meeting): RedirectResponse
    {
        DB::transaction(function () use ($request, $meeting): void {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            if ($meeting->status === 'cancelled' || $meeting->starts_at->isFuture()) {
                throw ValidationException::withMessages(['discussion' => 'Notulensi dicatat setelah rapat dimulai dan tidak untuk rapat batal.']);
            }
            $meeting->minute()->updateOrCreate([], $request->validated());
        });

        return back()->with('success', 'Notulensi berhasil disimpan.');
    }

    public function confirmation(Request $request, Meeting $meeting, Participant $participant): RedirectResponse
    {
        Gate::authorize('view', $meeting);
        abort_unless($participant->meeting_id === $meeting->id && $participant->user_id === $request->user()->id, 403);
        $data = $request->validate(['attendance_status' => ['required', Rule::in(['accepted', 'declined'])], 'user_id' => ['prohibited']]);
        DB::transaction(function () use ($meeting, $participant, $data): void {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            if ($meeting->status !== 'scheduled' || ! $meeting->starts_at->isFuture()) {
                throw ValidationException::withMessages(['attendance_status' => 'Konfirmasi hanya dapat diubah sebelum rapat dimulai.']);
            }
            $participant->refresh()->update($data);
            app(ReminderDelivery::class)->schedule($meeting);
        });

        return back()->with('success', 'Konfirmasi kehadiran diperbarui.');
    }

    public function preference(Request $request, Meeting $meeting, Participant $participant): RedirectResponse
    {
        Gate::authorize('view', $meeting);
        abort_unless($participant->meeting_id === $meeting->id && $participant->user_id === $request->user()->id, 403);
        $data = $request->validate(['reminder_preference' => ['required', Rule::in(array_keys(Participant::REMINDERS))]]);
        DB::transaction(function () use ($meeting, $participant, $data): void {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            if ($meeting->status !== 'scheduled' || ! $meeting->starts_at->isFuture()) {
                throw ValidationException::withMessages(['reminder_preference' => 'Pengingat hanya dapat diatur untuk rapat mendatang.']);
            }
            $participant->refresh()->update($data);
            app(ReminderDelivery::class)->schedule($meeting);
        });

        return back()->with('success', 'Pilihan pengingat disimpan.');
    }

    public function destroyAgenda(Meeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);
        DB::transaction(function () use ($meeting): void {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            if ($meeting->status !== 'scheduled' || ! $meeting->starts_at->isFuture()) {
                throw ValidationException::withMessages(['agenda' => 'Agenda hanya dapat dihapus sebelum rapat dimulai.']);
            }
            $meeting->agenda()->delete();
        });

        return back()->with('success', 'Agenda dihapus. Anda dapat menambahkannya kembali melalui Ubah rapat.');
    }
}
