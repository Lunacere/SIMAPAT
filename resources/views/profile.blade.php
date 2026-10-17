@extends('layouts.app')
@section('title', 'Profil saya')
@section('content')
<div class="page-heading"><div><h1>Profil saya</h1><p class="muted">Informasi akun yang sedang Anda gunakan.</p></div><a class="button secondary" href="{{ route('dashboard') }}">Kembali ke dashboard</a></div>
<section class="panel profile-panel">
<div class="user-identity"><span class="avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div><h2>{{ auth()->user()->name }}</h2><span class="badge role-{{ auth()->user()->role }}">{{ App\Models\User::ROLES[auth()->user()->role] }}</span></div></div>
<dl class="profile-details"><div><dt>Username</dt><dd>{{ auth()->user()->username }}</dd></div><div><dt>Email</dt><dd>{{ auth()->user()->email }}</dd></div></dl>
</section>
@endsection

