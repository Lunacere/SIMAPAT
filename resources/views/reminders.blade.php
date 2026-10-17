@extends('layouts.app')
@section('title', 'Notifikasi')
@section('content')
<div class="page-heading"><div><h1>Notifikasi & pengingat</h1><p class="muted">Tanggapi ajuan jadwal dan pantau pengingat rapat.</p></div></div>
<section class="panel">
@forelse($notifications as $notification)
@php($meeting = $meetings->get($notification->data['meeting_id']))
@if(($notification->data['kind'] ?? null) === 'schedule_proposal')
@if($meeting && auth()->user()->can('viewNegotiation', $meeting))<div class="reminder-row"><span class="date-tile" aria-hidden="true">↔</span><div><span class="badge">{{ $notification->read_at ? 'Sudah dibaca' : 'Baru' }}</span><h3>{{ $notification->data['status'] === 'accepted' ? 'Jadwal disepakati' : 'Ajuan jadwal rapat' }} · {{ $meeting->title }}</h3><p>{{ $notification->data['starts_at'] }} WIB</p><a class="text-link" href="{{ route('negotiations.show', $meeting) }}">Lihat negosiasi terbaru →</a></div>
@if(! $notification->read_at)<form method="post" action="{{ route('reminders.read', $notification->id) }}">@csrf @method('PATCH')<button class="button secondary small" type="submit">Sudah dibaca</button></form>
@endif</div>
@else<div class="reminder-row"><div><h3>Notifikasi tidak lagi tersedia</h3><p class="muted">Akses rapat telah berubah.</p></div></div>
@endif
@elseif($meeting && $meeting->version === $notification->data['meeting_version'] && $meeting->status === 'scheduled')
<div class="reminder-row"><span class="date-tile">◷</span><div><span class="badge">{{ $notification->read_at ? 'Sudah dibaca' : 'Baru' }}</span><h3>{{ $meeting->title }}</h3><p>{{ $meeting->starts_at->translatedFormat('j F Y · H:i') }} WIB · {{ $meeting->location }}</p><a class="text-link" href="{{ route('meetings.show', $meeting) }}">Lihat detail rapat →</a></div>
@if(! $notification->read_at)<form method="post" action="{{ route('reminders.read', $notification->id) }}">@csrf @method('PATCH')<button class="button secondary small" type="submit">Sudah dibaca</button></form>
@endif</div>
@else<div class="reminder-row"><div><h3>Pengingat tidak lagi aktif</h3><p class="muted">Jadwal atau akses rapat telah berubah.</p></div></div>
@endif
@empty<div class="empty"><span>◷</span><h3>Belum ada notifikasi</h3><p>Ajuan jadwal dan pengingat rapat akan tampil di sini.</p></div>
@endforelse
<div class="pagination">{{ $notifications->links() }}</div></section>
@endsection

