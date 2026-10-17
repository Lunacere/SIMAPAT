<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\ScheduleProposal;
use App\Models\User;
use App\Notifications\ScheduleProposalNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MeetingNegotiation
{
    public function invite(Meeting $meeting): void
    {
        $meeting->load('client');
        $meeting->scheduleProposals()->where('status', 'pending')->update(['status' => 'superseded']);
        if (! $meeting->client_id || ! $meeting->starts_at->isFuture() || ! $meeting->client->accounts()->where('role', 'client')->exists()) {
            return;
        }
        $proposal = $meeting->scheduleProposals()->create([
            'status' => 'pending', 'proposed_party' => 'company', 'proposed_by' => $meeting->creator_id, 'meeting_version' => $meeting->version,
            'starts_at' => $meeting->starts_at, 'ends_at' => $meeting->ends_at,
            'message' => 'Silakan konfirmasi jadwal rapat atau ajukan waktu alternatif.',
        ]);
        $this->notifyOtherParty($proposal);
    }

    public function propose(User $user, Meeting $meeting, array $data): ScheduleProposal
    {
        return DB::transaction(function () use ($user, $meeting, $data): ScheduleProposal {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('negotiate', $meeting);
            $this->ensureOpen($meeting);
            $latestId = (int) ($meeting->scheduleProposals()->max('id') ?? 0);
            if ($meeting->version !== (int) $data['version'] || $latestId !== (int) $data['latest_proposal_id']) {
                throw ValidationException::withMessages(['starts_at' => 'Ajuan atau rapat sudah berubah. Muat ulang halaman sebelum mengirim.']);
            }
            $meeting->scheduleProposals()->where('status', 'pending')->update(['status' => 'superseded']);
            $proposal = $meeting->scheduleProposals()->create([
                'status' => 'pending', 'proposed_party' => $user->role === 'client' ? 'client' : 'company', 'proposed_by' => $user->id, 'meeting_version' => $meeting->version,
                'starts_at' => Carbon::parse($data['starts_at'])->format('Y-m-d H:i:s'),
                'ends_at' => Carbon::parse($data['ends_at'])->format('Y-m-d H:i:s'),
                'message' => $data['message'] ?? null,
            ]);
            $this->notifyOtherParty($proposal);

            return $proposal;
        }, attempts: 5);
    }

    public function accept(User $user, Meeting $meeting, ScheduleProposal $proposal): void
    {
        DB::transaction(function () use ($user, $meeting, $proposal): void {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('negotiate', $meeting);
            $this->ensureOpen($meeting);
            $proposal = $meeting->scheduleProposals()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            $proposer = $proposal->proposer()->firstOrFail();
            $proposerIsAuthorized = $proposer->can('negotiate', $meeting)
                && (($proposer->role === 'client') === ($proposal->proposed_party === 'client'));
            if (! $proposerIsAuthorized) {
                throw ValidationException::withMessages(['starts_at' => 'Otoritas pengusul sudah berubah. Kirim ajuan baru.']);
            }
            $companyProposal = $proposal->proposed_party === 'company';
            abort_if(($user->role === 'client') !== $companyProposal, 403, 'Ajuan harus disetujui oleh pihak penerima.');
            if ($proposal->status !== 'pending' || $proposal->meeting_version !== $meeting->version || ! $proposal->starts_at->isFuture()) {
                throw ValidationException::withMessages(['starts_at' => 'Ajuan tidak lagi aktif. Muat ulang dan tanggapi ajuan terbaru.']);
            }
            $meeting = app(MeetingScheduler::class)->reschedule($meeting, $proposal->starts_at->format('Y-m-d H:i:s'), $proposal->ends_at->format('Y-m-d H:i:s'));
            $proposal->update(['status' => 'accepted', 'responded_by' => $user->id, 'responded_at' => now(), 'meeting_version' => $meeting->version]);
            $this->notifyOtherParty($proposal, $user);
        }, attempts: 5);
    }

    private function ensureOpen(Meeting $meeting): void
    {
        if ($meeting->status !== 'scheduled' || ! $meeting->starts_at->isFuture()) {
            throw ValidationException::withMessages(['starts_at' => 'Negosiasi hanya tersedia sebelum rapat dimulai dan untuk rapat yang belum ditutup.']);
        }
    }

    public function notifyClientAccount(User $account): void
    {
        if ($account->role !== 'client' || ! $account->client_id) {
            return;
        }
        Meeting::where('client_id', $account->client_id)->where('status', 'scheduled')->where('starts_at', '>', now())
            ->orderBy('id')->each(function (Meeting $meeting) use ($account): void {
                DB::transaction(function () use ($meeting, $account): void {
                    $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
                    $proposal = $meeting->latestProposal;
                    if (! $proposal) {
                        $this->invite($meeting);
                    } elseif ($proposal->status === 'pending' && $proposal->proposed_party === 'company' && $proposal->meeting_version === $meeting->version) {
                        $account->notify(new ScheduleProposalNotification($proposal));
                    }
                });
            });
    }

    private function notifyOtherParty(ScheduleProposal $proposal, ?User $actor = null): void
    {
        $meeting = $proposal->meeting;
        $actor ??= $proposal->proposer;
        $recipients = $actor->role === 'client'
            ? collect([$meeting->creator])
            : $meeting->client->accounts()->where('role', 'client')->get();
        foreach ($recipients as $recipient) {
            $recipient->notify(new ScheduleProposalNotification($proposal));
        }
    }
}
