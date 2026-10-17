@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<h1>Masuk ke SIMAPAT</h1><p class="muted">Gunakan username dan password akun Anda.</p>
<form method="post" action="{{ route('login.store') }}">@csrf<label for="username">Username</label><input id="username" name="username" value="{{ old('username') }}" required autocomplete="username" autofocus placeholder="Masukkan username"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Masukkan password"><button class="button full" type="submit">Masuk ke SIMAPAT <span>→</span></button></form>
<p class="login-help">Belum memiliki akun? <a class="text-link" href="{{ route('register') }}">Daftar sekarang</a><br>Lupa password? Hubungi Admin untuk bantuan akses.</p>
@endsection

