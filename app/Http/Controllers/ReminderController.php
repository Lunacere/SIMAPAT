<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->latest()->paginate(15);
        $meetings = Meeting::visibleTo($request->user())->whereIn('id', $notifications->map(fn ($notification) => $notification->data['meeting_id']))->get()->keyBy('id');

        return view('reminders', compact('notifications', 'meetings'));
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return back()->with('success', 'Pengingat ditandai sudah dibaca.');
    }
}
