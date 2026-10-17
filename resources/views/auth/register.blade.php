@extends('layouts.guest')
@section('title', 'Daftar')
@section('content')
<h1>Buat akun SIMAPAT</h1><p class="muted">Akun baru mendapat peran User basic. Akses Klien diatur oleh Admin.</p>
<form method="post" action="{{ route('register.store') }}">@csrf
<label for="name">Nama lengkap</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name" autofocus>
<label for="username">Username</label><input id="username" name="username" value="{{ old('username') }}" required maxlength="100" pattern="[a-z0-9_.-]+" autocomplete="username"><small class="muted">Huruf kecil, angka, titik, garis bawah, atau tanda hubung.</small>
<label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
<label for="password">Password</label><input id="password" name="password" type="password" required minlength="12" maxlength="200" autocomplete="new-password"><small class="muted">Minimal 12 karakter.</small>
<label for="password_confirmation">Konfirmasi password</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password">
<button class="button full" type="submit">Daftar akun <span>→</span></button>
</form><p class="login-help">Sudah memiliki akun? <a class="text-link" href="{{ route('login') }}">Masuk di sini</a></p>
@endsection

