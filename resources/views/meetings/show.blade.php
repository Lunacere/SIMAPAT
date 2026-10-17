@extends('layouts.app')
@section('title', 'Detail rapat')
@section('content')
<div class="page-heading"><div><h1>{{ $meeting->title }}</h1><p class="muted">Dikelola {{ $meeting->creator->name }} · <span class="badge {{ $meeting->status }}">{{ App\Models\Meeting::STATUSES[$meeting->status] }}</span></p></div>
@can('update', $meeting)
@if($meeting->status === 'scheduled')<a class="button secondary" href="{{ route('meetings.edit', $meeting) }}">Ubah rapat</a>
@endif
@endcan</div>
@can('viewNegotiation', $meeting)<div class="help-strip"><span aria-hidden="true">↔</span><div><strong>Sepakati jadwal bersama klien</strong><p>Klien dapat menyetujui atau mengajukan waktu alternatif.</p></div><a class="button secondary" href="{{ route('negotiations.show', $meeting) }}">Negosiasi jadwal</a></div>
@endcan
<div class="detail-grid"><section class="panel"><h2>Jadwal & peserta</h2><dl class="info-list"><dt>Mulai</dt><dd>{{ $meeting->starts_at->translatedFormat('l, d F Y · H:i') }} WIB</dd><dt>Selesai</dt><dd>{{ $meeting->ends_at->translatedFormat('l, j F Y · H:i') }} WIB</dd><dt>Tempat/media</dt><dd>{{ $meeting->location }}</dd><dt>Klien</dt><dd>{{ $meeting->client?->name ?? 'Rapat internal' }}</dd></dl>
@foreach($meeting->participants as $participant)<div class="participant-row"><span class="avatar">{{ mb_substr($participant->user->name, 0, 1) }}</span><div><strong>{{ $participant->user->name }}</strong><small>{{ App\Models\Participant::STATUSES[$participant->attendance_status] }}</small></div></div>
@endforeach
@if(($myParticipation = $meeting->participants->firstWhere('user_id', auth()->id())) && $meeting->status === 'scheduled' && $meeting->starts_at->isFuture())
<hr><h3>Konfirmasi kehadiranmu</h3><form class="inline-form" method="post" action="{{ route('participants.confirmation', [$meeting, $myParticipation]) }}">@csrf @method('PATCH')<select name="attendance_status" aria-label="Konfirmasi kehadiran">
@foreach(['accepted' => 'Akan hadir', 'declined' => 'Tidak hadir'] as $value => $label)<option value="{{ $value }}" @selected($myParticipation->attendance_status === $value)>{{ $label }}</option>
@endforeach</select><button class="button small" type="submit">Simpan</button></form>
<label for="reminder_preference">Pengingat untukmu</label><form class="inline-form" method="post" action="{{ route('participants.reminder', [$meeting, $myParticipation]) }}">@csrf @method('PUT')<select id="reminder_preference" name="reminder_preference">
@foreach(App\Models\Participant::REMINDERS as $value => $label)<option value="{{ $value }}" @selected($myParticipation->reminder_preference === $value)>{{ $label }}</option>
@endforeach</select><button class="button small" type="submit">Simpan</button></form><p class="muted small-text">Jika waktu pengingat sudah lewat, pengingat dikirim pada pemeriksaan berikutnya selama rapat belum dimulai.</p>
@endif
@can('update', $meeting)
@if($meeting->status === 'scheduled')<hr><div class="stack-actions"><form data-confirm="Batalkan rapat ini? Riwayat tetap tersimpan." method="post" action="{{ route('meetings.cancel', $meeting) }}">@csrf @method('PATCH')<button class="button danger" type="submit">Batalkan rapat</button></form><form method="post" action="{{ route('meetings.complete', $meeting) }}">@csrf @method('PATCH')<button class="button secondary" type="submit">Tandai rapat selesai</button></form></div><small class="muted">Rapat selesai setelah waktu berakhir dan notulensi disimpan.</small>
@endif
@endcan
</section><div class="detail-main">
<section class="panel"><div class="section-heading"><div><h2>Agenda</h2></div></div><h3>{{ $meeting->agenda?->title ?? 'Agenda belum tersedia' }}</h3><p class="prose">{{ $meeting->agenda?->description }}</p>
@can('update', $meeting)
@if($meeting->agenda && $meeting->status === 'scheduled' && $meeting->starts_at->isFuture())
<form data-confirm="Hapus agenda rapat ini?" method="post" action="{{ route('meetings.agenda.destroy', $meeting) }}">@csrf @method('DELETE')<button class="text-link danger-text" type="submit">Hapus agenda</button></form>
@endif
@endcan
</section>
<section class="panel"><div class="section-heading"><div><h2>Notulensi</h2></div></div>
@if($meeting->minute)<h3>Pembahasan</h3><p class="prose">{{ $meeting->minute->discussion }}</p><h3>Keputusan</h3><p class="prose">{{ $meeting->minute->decisions }}</p>
@if($meeting->minute->client_progress)<h3>Perkembangan klien saat rapat</h3><p class="prose">{{ $meeting->minute->client_progress }}</p>
@endif<small class="muted">Dicatat {{ $meeting->minute->recorded_on->translatedFormat('j F Y') }}</small>
@else<p class="muted">Hasil pembahasan belum dicatat.</p>
@endif
@can('update', $meeting)
@if($meeting->status !== 'cancelled' && ! $meeting->starts_at->isFuture())
<details class="edit-details" 
@if($errors->has('discussion') || $errors->has('decisions')) open 
@endif><summary>{{ $meeting->minute ? 'Perbarui notulensi' : 'Catat notulensi' }}</summary><form method="post" action="{{ route('meetings.minutes', $meeting) }}">@csrf @method('PUT')
@foreach(['discussion' => 'Pembahasan', 'decisions' => 'Keputusan', 'client_progress' => 'Perkembangan klien'] as $field => $label)<label for="{{ $field }}">{{ $label }}</label><textarea id="{{ $field }}" name="{{ $field }}" rows="4" @required($field !== 'client_progress')>{{ old($field, $meeting->minute?->$field) }}</textarea>
@endforeach
<label for="recorded_on">Tanggal pencatatan *</label><input id="recorded_on" name="recorded_on" type="date" required max="{{ today()->toDateString() }}" value="{{ old('recorded_on', $meeting->minute?->recorded_on->toDateString() ?? today()->toDateString()) }}"><div class="form-actions"><button class="button" type="submit">Simpan notulensi</button></div></form></details>
@endif
@endcan</section>
<section class="panel"><div class="section-heading"><div><h2>Tindak lanjut</h2></div><span class="muted">{{ $meeting->followUps->where('status', 'completed')->count() }}/{{ $meeting->followUps->count() }} selesai</span></div>
@forelse($meeting->followUps as $task)<div class="task-row"><span class="badge {{ $task->status }}">{{ App\Models\FollowUp::STATUSES[$task->status] }}</span><h3>{{ $task->task }}</h3><p>PIC: {{ $task->user->name }} · Tenggat: {{ $task->deadline->translatedFormat('j F Y') }}</p>
@if($task->user_id === auth()->id() && $meeting->status !== 'cancelled')<form class="inline-form" method="post" action="{{ route('follow-ups.status', [$meeting, $task]) }}">@csrf @method('PATCH')<select name="status" aria-label="Status tugas {{ $task->id }}">
@foreach(App\Models\FollowUp::STATUSES as $value => $label)<option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
@endforeach</select><button class="button small" type="submit">Simpan status</button></form>
@endif
@can('update', $meeting)
@if($meeting->status !== 'cancelled')
<details class="edit-details"><summary>Ubah tugas</summary>
@include('meetings.task-form', ['task' => $task])</details>
<form data-confirm="Hapus tindak lanjut ini?" method="post" action="{{ route('follow-ups.destroy', [$meeting, $task]) }}">@csrf @method('DELETE')<button class="text-link danger-text" type="submit">Hapus tugas</button></form>
@endif
@endcan</div>
@empty<p class="muted">Belum ada tindak lanjut. Rapat tanpa tugas tambahan tetap dapat diselesaikan.</p>
@endforelse
@can('update', $meeting)
@if($meeting->minute && $meeting->status !== 'cancelled')<details class="edit-details" 
@if($errors->has('task')) open 
@endif><summary>+ Tambahkan tindak lanjut</summary>
@include('meetings.task-form', ['task' => new App\Models\FollowUp])</details>
@endif
@endcan
</section></div></div>
@endsection

