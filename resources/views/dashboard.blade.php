@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="page-heading"><div><h1>Halo, {{ auth()->user()->name }}</h1><p class="muted">Rapat mendatang dan pekerjaan yang perlu ditindaklanjuti.</p></div>
@can('create', App\Models\Meeting::class)<a class="button" href="{{ route('meetings.create') }}">Jadwalkan rapat</a>
@endcan</div>
<div class="metrics dashboard-metrics">
<a class="metric" href="{{ route('meetings.index', ['upcoming' => 1]) }}"><span>Rapat mendatang</span><strong>{{ $upcomingCount }}</strong><small>Lihat jadwal yang belum dimulai →</small></a>
@if(in_array(auth()->user()->role, ['admin', 'operator'], true))
<a class="metric {{ $awaitingCount > 0 ? 'attention' : '' }}" href="{{ route('negotiations.index', ['awaiting' => 'me']) }}"><span>Ajuan perlu tanggapan</span><strong>{{ $awaitingCount }}</strong><small>Ajuan dari klien →</small></a>
@else
<a class="metric" href="{{ route('tasks.index', ['status' => 'active']) }}"><span>Tugas aktif saya</span><strong>{{ $openTaskCount }}</strong><small>Lihat tugas yang belum selesai →</small></a>
@endif
<a class="metric {{ $overdueTaskCount > 0 ? 'attention' : '' }}" href="#attention-tasks"><span>Tugas melewati tenggat</span><strong>{{ $overdueTaskCount }}</strong><small>{{ auth()->user()->role === 'admin' ? 'Seluruh tugas aktif' : (auth()->user()->role === 'operator' ? 'Tugas saya dan rapat yang saya kelola' : 'Tugas saya') }} →</small></a>
</div>
@if(in_array(auth()->user()->role, ['admin', 'operator'], true))
<section class="panel dashboard-awaiting"><div class="section-heading"><div><h2>Ajuan yang perlu Anda tanggapi</h2><p class="muted">{{ $awaitingCount }} ajuan dari klien menunggu persetujuan perusahaan.</p></div><a class="text-link" href="{{ route('negotiations.index', ['awaiting' => 'me']) }}">Lihat semua →</a></div>
@forelse($awaitingMeetings as $meeting)
<a class="meeting-row" href="{{ route('negotiations.show', $meeting) }}"><div><h3>{{ $meeting->title }}</h3><p class="record-schedule">@include('layouts.schedule-time', ['startsAt' => $meeting->latestProposal->starts_at, 'endsAt' => $meeting->latestProposal->ends_at])</p><small>{{ $meeting->client->name }} · Diajukan {{ $meeting->latestProposal->proposer->name }}</small></div><span class="text-link">Tanggapi →</span></a>
@empty<p class="muted">Tidak ada ajuan yang memerlukan tanggapan Anda.</p>
@endforelse</section>
@endif
<div class="dashboard-grid">
<section class="panel"><div class="section-heading"><h2>Rapat mendatang</h2><a class="text-link" href="{{ route('meetings.index', ['upcoming' => 1]) }}">Lihat semua →</a></div>
@forelse($upcoming as $meeting)<a class="meeting-row" href="{{ route('meetings.show', $meeting) }}"><div class="date-tile"><strong>{{ $meeting->starts_at->format('d') }}</strong><span>{{ $meeting->starts_at->translatedFormat('M') }}</span></div><div><h3>{{ $meeting->title }}</h3><p class="record-schedule">@include('layouts.schedule-time', ['startsAt' => $meeting->starts_at, 'endsAt' => $meeting->ends_at])</p><small class="record-location" title="{{ $meeting->location }}">{{ $meeting->location }}</small><small>{{ $meeting->client?->name ?? 'Rapat internal' }} · {{ $meeting->participants_count }} peserta</small></div><span class="row-arrow" aria-hidden="true">→</span></a>
@empty<div class="empty"><h3>Belum ada rapat mendatang</h3><p>{{ auth()->user()->role === 'operator' ? 'Jadwalkan rapat melalui tombol di atas.' : (auth()->user()->role === 'admin' ? 'Rapat akan tampil setelah Operator membuat jadwal mendatang.' : 'Rapat akan tampil setelah Anda mendapat akses.') }}</p></div>
@endforelse</section>
<section class="panel" id="attention-tasks"><div class="section-heading"><div><h2>{{ auth()->user()->role === 'user' ? 'Tugas aktif saya' : 'Tugas yang perlu ditindaklanjuti' }}</h2><p class="muted">{{ $openTaskCount }} tugas aktif · Tenggat terdekat ditampilkan lebih dulu.</p></div><a class="text-link" href="{{ route('tasks.index', ['status' => 'active']) }}">Tugas saya →</a></div>
@forelse($tasks as $task)<div class="task-row"><span class="badge {{ $task->status }}">{{ App\Models\FollowUp::STATUSES[$task->status] }}</span><h3>
@can('view', $task->meeting)<a class="text-link" href="{{ route('meetings.show', $task->meeting) }}">{{ $task->task }}</a>
@else<a class="text-link" href="{{ route('tasks.index', ['status' => 'active']) }}">{{ $task->task }}</a>
@endcan</h3><p>{{ $task->user->name }} · {{ $task->deadline->translatedFormat('j F Y') }}</p>
@if($task->deadline->isBefore(today()))<small class="danger-text">Melewati tenggat</small>
@endif</div>
@empty<div class="empty"><h3>Tidak ada tugas aktif</h3><p>Tugas yang belum selesai akan tampil di sini.</p></div>
@endforelse</section></div>
@endsection



