@extends('layouts.app')
@section('title', $account->exists ? 'Ubah pengguna' : 'Tambah pengguna')
@section('content')
<div class="page-heading"><div><h1>{{ $account->exists ? 'Ubah pengguna' : 'Tambah pengguna' }}</h1><p class="muted">Admin mengelola peran akun. Akun Klien harus ditautkan ke perusahaan klien.</p></div></div>
<form class="panel form-panel" method="post" action="{{ $account->exists ? route('users.update', $account) : route('users.store') }}">@csrf 
@if($account->exists)@method('PUT')
@endif
<label for="name">Nama *</label><input id="name" name="name" required value="{{ old('name', $account->name) }}">
<div class="form-grid"><div><label for="username">Username *</label><input id="username" name="username" required value="{{ old('username', $account->username) }}"><small class="muted">Huruf kecil, angka, titik, garis bawah, atau tanda hubung.</small></div><div><label for="email">Email *</label><input id="email" type="email" name="email" required value="{{ old('email', $account->email) }}"></div></div>
<label for="role">Peran *</label><select id="role" name="role">
@foreach(App\Models\User::ROLES as $value => $label)<option value="{{ $value }}" @selected(old('role', $account->role ?? 'user') === $value)>{{ $label }}</option>
@endforeach</select>
<div class="client-account-field" data-client-account><label for="client_id">Perusahaan klien *</label><select id="client_id" name="client_id"><option value="">Pilih perusahaan klien</option>
@foreach($clients as $client)<option value="{{ $client->id }}" @selected(old('client_id', $account->client_id) == $client->id)>{{ $client->name }}</option>
@endforeach</select><small class="muted">Akun Klien hanya dapat melihat dan menegosiasikan rapat perusahaan yang dipilih.</small></div>
<div class="form-grid"><div><label for="password">Password {{ $account->exists ? '(kosongkan jika tetap)' : '*' }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" @required(! $account->exists)><small class="muted">Minimal 12 karakter.</small></div><div><label for="password_confirmation">Konfirmasi password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(! $account->exists)></div></div>
<div class="form-actions"><button class="button" type="submit">Simpan akun</button><a class="button secondary" href="{{ route('users.index') }}">Batal</a></div></form>
@endsection
