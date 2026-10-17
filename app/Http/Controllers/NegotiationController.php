<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleProposalRequest;
use App\Models\Meeting;
use App\Models\ScheduleProposal;
use App\Services\MeetingNegotiation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NegotiationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(in_array($request->user()->role, ['admin', 'operator', 'client'], true), 403);
        $filters = $request->validate(['awaiting' => ['nullable', Rule::in(['me'])]]);
        $awaitingMe = ($filters['awaiting'] ?? null) === 'me';
        $query = Meeting::visibleTo($request->user())->whereNotNull('client_id');
        if ($request->user()->role === 'operator') {
            $query->where('creator_id', $request->user()->id);
        }
        if ($awaitingMe) {
            $query->where('status', 'scheduled')->where('starts_at', '>', now())
                ->whereHas('latestProposal', fn ($query) => $query->where('status', 'pending')
                    ->where('proposed_party', $request->user()->role === 'client' ? 'company' : 'client')
                    ->whereColumn('schedule_proposals.meeting_version', 'meetings.version'));
        }

        return view('negotiations.index', ['meetings' => $query->with(['client', 'latestProposal.proposer'])->orderByDesc('starts_at')->paginate(12)->withQueryString(),
            'awaitingMe' => $awaitingMe]);
    }

    public function show(Meeting $meeting): View
    {
        Gate::authorize('viewNegotiation', $meeting);

        return view('negotiations.show', ['meeting' => $meeting->load(['client', 'creator']),
            'hasClientAccounts' => $meeting->client->accounts()->where('role', 'client')->exists(),
            'proposals' => $meeting->scheduleProposals()->with(['proposer', 'responder'])->latest('id')->paginate(10)]);
    }

    public function store(ScheduleProposalRequest $request, Meeting $meeting, MeetingNegotiation $negotiation): RedirectResponse
    {
        $negotiation->propose($request->user(), $meeting, $request->validated());

        return redirect()->route('negotiations.show', $meeting)->with('success', 'Ajuan jadwal dikirim. Pihak penerima dapat menyetujui atau mengajukan waktu lain.');
    }

    public function accept(Request $request, Meeting $meeting, ScheduleProposal $proposal, MeetingNegotiation $negotiation): RedirectResponse
    {
        Gate::authorize('negotiate', $meeting);
        abort_unless($proposal->meeting_id === $meeting->id, 404);
        $negotiation->accept($request->user(), $meeting, $proposal);

        return redirect()->route('negotiations.show', $meeting)->with('success', 'Jadwal disepakati. Jadwal rapat dan pengingat telah diperbarui.');
    }
}
