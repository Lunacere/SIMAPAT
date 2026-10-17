<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Client;
use App\Models\ScheduleProposal;
use App\Models\User;
use App\Services\MeetingNegotiation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('users.index', ['users' => User::orderBy('name')->paginate(15)]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.form', ['account' => new User, 'clients' => Client::orderBy('name')->get()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $account = User::create($request->validated());
        app(MeetingNegotiation::class)->notifyClientAccount($account);

        return redirect()->route('users.index')->with('success', 'Akun berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.form', ['account' => $user, 'clients' => Client::orderBy('name')->get()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $data['client_id'] = $data['role'] === 'client' ? (int) $data['client_id'] : null;
        if ($user->id === $request->user()->id && $data['role'] !== 'admin') {
            throw ValidationException::withMessages(['role' => 'Anda tidak dapat menghapus peran Admin dari akun sendiri.']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        DB::transaction(function () use ($user, $data): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->refresh();
            if ($user->role === 'operator' && $data['role'] !== 'operator' && $user->createdMeetings()->where('status', 'scheduled')->exists()) {
                throw ValidationException::withMessages(['role' => 'Tutup atau alihkan rapat aktif sebelum mengganti role Operator.']);
            }
            if ($data['role'] === 'client' && $user->role !== 'client' && $user->followUps()->exists()) {
                throw ValidationException::withMessages(['role' => 'Akun PIC internal tidak dapat dijadikan Klien. Gunakan akun Klien terpisah.']);
            }
            $authorityChanged = $user->role !== $data['role'] || $user->client_id !== $data['client_id'];
            if ($authorityChanged) {
                ScheduleProposal::where('proposed_by', $user->id)->where('status', 'pending')->update(['status' => 'superseded']);
            }
            $user->update($data);
            if ($authorityChanged) {
                app(MeetingNegotiation::class)->notifyClientAccount($user);
            }
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
        if ($user->id === $request->user()->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('success', 'Akun diperbarui. Silakan login kembali.');
        }

        return redirect()->route('users.index')->with('success', 'Akun diperbarui. Pengguna perlu login kembali.');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);
        DB::transaction(function () use ($user): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->participants()->exists() || $user->followUps()->exists() || $user->createdMeetings()->exists() || DB::table('reminders')->where('user_id', $user->id)->exists() || DB::table('schedule_proposals')->where('proposed_by', $user->id)->orWhere('responded_by', $user->id)->exists()) {
                throw ValidationException::withMessages(['user' => 'Akun masih terkait rapat atau tugas dan tidak dapat dihapus.']);
            }
            if (DB::table('chat_messages')->where('sender_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['user' => 'Akun masih terkait riwayat chat dan tidak dapat dihapus.']);
            }
            $user->notifications()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return back()->with('success', 'Akun dihapus.');
    }
}
