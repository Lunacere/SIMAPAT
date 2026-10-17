<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->role === 'client') {
            return redirect()->route('negotiations.index');
        }
        $meetings = Meeting::visibleTo($user);
        $tasks = FollowUp::where('status', '!=', 'completed')
            ->whereHas('meeting', fn (Builder $query) => $query->where('status', '!=', 'cancelled'));
        if ($user->role !== 'admin') {
            $tasks->where(function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id);
                if ($user->role === 'operator') {
                    $query->orWhereHas('meeting', fn (Builder $meeting) => $meeting->where('creator_id', $user->id));
                }
            });
        }
        $upcoming = (clone $meetings)->where('status', 'scheduled')->where('starts_at', '>=', now());
        $awaitingMeetings = collect();
        $awaitingCount = 0;
        if (in_array($user->role, ['admin', 'operator'], true)) {
            $awaiting = (clone $upcoming)->whereNotNull('client_id')
                ->whereHas('latestProposal', fn (Builder $proposal) => $proposal->where('status', 'pending')->where('proposed_party', 'client')->whereColumn('schedule_proposals.meeting_version', 'meetings.version'));
            if ($user->role === 'operator') {
                $awaiting->where('creator_id', $user->id);
            }
            $awaitingCount = (clone $awaiting)->count();
            $awaitingMeetings = $awaiting->with(['client', 'latestProposal.proposer'])->orderBy('starts_at')->limit(5)->get();
        }

        return view('dashboard', [
            'upcoming' => (clone $upcoming)->with('client')->withCount('participants')->orderBy('starts_at')->limit(5)->get(),
            'upcomingCount' => (clone $upcoming)->count(),
            'awaitingMeetings' => $awaitingMeetings,
            'awaitingCount' => $awaitingCount,
            'openTaskCount' => (clone $tasks)->count(),
            'overdueTaskCount' => (clone $tasks)->where('deadline', '<', today())->count(),
            'tasks' => (clone $tasks)->with(['user', 'meeting'])->orderBy('deadline')->orderBy('id')->limit(5)->get(),
        ]);
    }
}
