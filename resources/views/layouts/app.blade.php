<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title', 'Dashboard') · SIMAPAT</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body><div class="app-shell">
<aside class="sidebar">
<div class="sidebar-head">
<button class="menu-toggle" type="button" aria-label="Buka menu navigasi" aria-controls="sidebar-menu" aria-expanded="false" hidden><span aria-hidden="true"><i></i><i></i><i></i></span></button>
<a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">S</span><span>SIMAPAT<small>Ruang koordinasi tim</small></span></a>
</div>
<div class="sidebar-menu" id="sidebar-menu">
<span class="nav-label">RUANG KERJA</span><nav aria-label="Navigasi utama">
@foreach(['dashboard' => ['layout-dashboard', 'Dashboard'], 'meetings.index' => ['calendar-days', 'Rapat'], 'tasks.index' => ['list-todo', 'Tugas saya'], 'reminders.index' => ['bell', 'Notifikasi'], 'history' => ['history', 'Riwayat rapat']] as $route => [$icon, $label])
@continue(auth()->user()->role === 'client' && $route === 'tasks.index')
<a class="{{ request()->routeIs($route, explode('.', $route)[0].'.*') ? 'active' : '' }}" @if(request()->routeIs($route, explode('.', $route)[0].'.*')) aria-current="page" @endif href="{{ route($route) }}">@include('layouts.navigation-icon', ['icon' => $icon]){{ $label }}</a>
@endforeach
@if(in_array(auth()->user()->role, ['admin', 'operator', 'client'], true))<a class="{{ request()->routeIs('negotiations.*') ? 'active' : '' }}" @if(request()->routeIs('negotiations.*')) aria-current="page" @endif href="{{ route('negotiations.index') }}">@include('layouts.navigation-icon', ['icon' => 'calendar-clock'])Negosiasi jadwal</a>
@endif
@if(auth()->user()->role === 'admin')<a class="{{ request()->routeIs('reports.*') ? 'active' : '' }}" @if(request()->routeIs('reports.*')) aria-current="page" @endif href="{{ route('reports.meetings') }}">@include('layouts.navigation-icon', ['icon' => 'chart-no-axes-combined'])Laporan</a>
@endif
@can('viewAny', App\Models\Client::class)<a class="{{ request()->routeIs('clients.*') ? 'active' : '' }}" @if(request()->routeIs('clients.*')) aria-current="page" @endif href="{{ route('clients.index') }}">@include('layouts.navigation-icon', ['icon' => 'building-2'])Klien</a>
@endcan
@can('viewAny', App\Models\User::class)<a class="{{ request()->routeIs('users.*') ? 'active' : '' }}" @if(request()->routeIs('users.*')) aria-current="page" @endif href="{{ route('users.index') }}">@include('layouts.navigation-icon', ['icon' => 'users'])Pengguna</a>
@endcan
</nav>
<div class="account"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ App\Models\User::ROLES[auth()->user()->role] }}</small></div></div>
</div>
</aside>
<div class="workspace"><header class="topbar"><span class="breadcrumb"><span>Ruang kerja</span> <b>/ @yield('title', 'Dashboard')</b></span><div><button class="quicknav-trigger" type="button" data-open-quicknav aria-label="Buka halaman" aria-haspopup="dialog" aria-controls="quicknav-dialog" hidden><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg><span>Buka halaman</span><kbd aria-hidden="true">Ctrl K</kbd></button><span class="today">{{ now()->translatedFormat('d F Y') }}</span><a href="{{ route('reminders.index') }}">Notifikasi <span class="counter">{{ auth()->user()->unreadNotifications()->count() }}</span></a>
<details class="account-menu"><summary aria-label="Menu akun"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg></summary><div class="account-popup">
<div class="account-popup-heading"><strong>{{ auth()->user()->name }}</strong><small>{{ App\Models\User::ROLES[auth()->user()->role] }}</small></div>
<a href="{{ route('profile.show') }}">Profil <span aria-hidden="true">↗</span></a>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Keluar dari akun <span aria-hidden="true">→</span></button></form>
</div></details></div></header>
<main id="main">
@if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>
@endif
@include('layouts.validation-errors')
@yield('content')
</main><footer>SIMAPAT · Manajemen rapat dan tindak lanjut<span>Waktu ditampilkan dalam WIB</span></footer></div>
</div>
@include('layouts.workspace-dialogs')
</body></html>






