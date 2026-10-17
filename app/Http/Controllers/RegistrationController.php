<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegistrationRequest $request): RedirectResponse
    {
        $user = User::create([...$request->validated(), 'role' => 'user', 'client_id' => null]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Akun User basic berhasil dibuat. Selamat datang di SIMAPAT.');
    }
}
