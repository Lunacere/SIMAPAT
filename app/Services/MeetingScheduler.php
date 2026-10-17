<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeetingScheduler
{
    public function save(User $operator, array $data, ?Meeting $meeting = null): Meeting
    {
        return DB::transaction(function () use ($operator, $data, $meeting): Meeting {
            if ($meeting) {
                $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
                if ($meeting->status !== 'scheduled' || $meeting->version !== (int) $data['version']) {
                    throw ValidationException::withMessages(['title' => 'Rapat sudah berubah atau ditutup. Muat ulang halaman sebelum mengedit.']);
                }
            }
            $isNew = $meeting === null;
            $oldClientId = $meeting?->client_id;
            $participantIds = array_map('intval', $data['participants']);
            $oldIds = $meeting ? $meeting->participants()->pluck('user_id')->all() : [];
            $this->assertAvailable($participantIds, $data['starts_at'], $data['ends_at'], $meeting, isset($data['client_id']) ? (int) $data['client_id'] : null, $oldIds);
            $timeChanged = $meeting && ($meeting->starts_at->format('Y-m-d H:i:s') !== $data['starts_at'] || $meeting->ends_at->format('Y-m-d H:i:s') !== $data['ends_at']);
            if ($timeChanged && $meeting->client_id && $meeting->client->accounts()->where('role', 'client')->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'Gunakan Negosiasi jadwal untuk perubahan waktu rapat dengan akun Klien.']);
            }
            $meeting ??= new Meeting(['creator_id' => $operator->id, 'status' => 'scheduled', 'version' => 1]);
            $meeting->fill(Arr::only($data, ['title', 'starts_at', 'ends_at', 'location', 'client_id']));
            if ($meeting->exists) {
                $meeting->version++;
            }
            $meeting->save();
            $meeting->participants()->whereNotIn('user_id', $participantIds)->delete();
            foreach ($participantIds as $userId) {
                $participant = $meeting->participants()->firstOrCreate(['user_id' => $userId]);
                if ($timeChanged) {
                    $participant->update(['attendance_status' => 'pending']);
                }
            }
            $meeting->agenda()->updateOrCreate([], ['title' => $data['agenda_title'], 'description' => $data['agenda_description']]);
            app(ReminderDelivery::class)->schedule($meeting);
            if ($isNew || $timeChanged || $oldClientId !== $meeting->client_id) {
                app(MeetingNegotiation::class)->invite($meeting);
            } else {
                $latest = $meeting->latestProposal;
                if ($latest && in_array($latest->status, ['pending', 'accepted'], true)) {
                    $latest->update(['meeting_version' => $meeting->version]);
                }
            }

            return $meeting;
        }, attempts: 5);
    }

    public function reschedule(Meeting $meeting, string $startsAt, string $endsAt): Meeting
    {
        $participantIds = $meeting->participants()->pluck('user_id')->all();
        $this->assertAvailable($participantIds, $startsAt, $endsAt, $meeting, $meeting->client_id);
        if ($meeting->starts_at->format('Y-m-d H:i:s') !== $startsAt || $meeting->ends_at->format('Y-m-d H:i:s') !== $endsAt) {
            $meeting->update(['starts_at' => $startsAt, 'ends_at' => $endsAt, 'version' => $meeting->version + 1]);
            $meeting->participants()->update(['attendance_status' => 'pending']);
            app(ReminderDelivery::class)->schedule($meeting);
        }

        return $meeting;
    }

    private function assertAvailable(array $participantIds, string $startsAt, string $endsAt, ?Meeting $meeting, ?int $clientId, array $oldParticipantIds = []): void
    {
        User::whereIn('id', array_unique(array_merge($participantIds, $oldParticipantIds)))->orderBy('id')->lockForUpdate()->get();
        if ($clientId) {
            Client::whereKey($clientId)->lockForUpdate()->firstOrFail();
        }
        $conflict = Meeting::where('status', '!=', 'cancelled')
            ->when($meeting, fn (Builder $query) => $query->whereKeyNot($meeting->id))
            ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)
            ->where(function (Builder $query) use ($participantIds, $clientId): void {
                $query->whereHas('participants', fn (Builder $query) => $query->whereIn('user_id', $participantIds));
                if ($clientId) {
                    $query->orWhere('client_id', $clientId);
                }
            })->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['participants' => 'Ada peserta atau klien yang memiliki rapat lain pada waktu tersebut. Pilih waktu lain.']);
        }
    }
}
