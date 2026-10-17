@extends('layouts.app')
@section('title', $meeting->exists ? 'Ubah rapat' : 'Jadwalkan rapat')
@section('content')
<div class="page-heading"><div><h1>{{ $meeting->exists ? 'Ubah rapat' : 'Jadwalkan rapat baru' }}</h1><p class="muted">Benturan jadwal diperiksa untuk setiap peserta sebelum disimpan.</p></div><a class="button secondary" href="{{ route('meetings.index') }}">Kembali</a></div>
<form class="panel form-panel" method="post" action="{{ $meeting->exists ? route('meetings.update', $meeting) : route('meetings.store') }}">@csrf
@if($meeting->exists)@method('PUT')<input type="hidden" name="version" value="{{ old('version', $meeting->version) }}">
@endif
<h2>Informasi rapat</h2><label for="title">Judul rapat *</label><input id="title" name="title" required maxlength="255" value="{{ old('title', $meeting->title) }}" placeholder="Contoh: Evaluasi progres proyek">
@if($requiresNegotiation)<div class="alert notice" id="schedule-help"><p>Waktu rapat ini dikelola melalui negosiasi karena klien sudah memiliki akun. Judul, agenda, tempat, dan peserta tetap dapat diubah di sini.</p><a class="text-link" href="{{ route('negotiations.show', $meeting) }}">Ajukan perubahan waktu melalui negosiasi</a></div>
@endif
<div class="form-grid"><div><label for="starts_at">Waktu mulai (WIB) *</label><input id="starts_at" type="datetime-local" name="starts_at" required @readonly($requiresNegotiation) @if($requiresNegotiation) aria-describedby="schedule-help" @endif value="{{ $requiresNegotiation ? $meeting->starts_at->format('Y-m-d\TH:i') : old('starts_at', $meeting->starts_at?->format('Y-m-d\TH:i')) }}"></div><div><label for="ends_at">Waktu selesai (WIB) *</label><input id="ends_at" type="datetime-local" name="ends_at" required @readonly($requiresNegotiation) @if($requiresNegotiation) aria-describedby="schedule-help" @endif value="{{ $requiresNegotiation ? $meeting->ends_at->format('Y-m-d\TH:i') : old('ends_at', $meeting->ends_at?->format('Y-m-d\TH:i')) }}"></div></div>
<div class="form-grid"><div><label for="location">Tempat atau tautan rapat *</label><input id="location" name="location" required maxlength="255" value="{{ old('location', $meeting->location) }}"></div><div><label for="client_id">Klien terkait</label><select id="client_id" name="client_id"><option value="">Rapat internal (tanpa klien)</option>
@foreach($clients as $client)<option value="{{ $client->id }}" @selected(old('client_id', $meeting->client_id) == $client->id)>{{ $client->name }}</option>
@endforeach</select></div></div>
<hr><h2>Agenda pembahasan</h2><label for="agenda_title">Judul agenda *</label><input id="agenda_title" name="agenda_title" required value="{{ old('agenda_title', $meeting->agenda?->title) }}"><label for="agenda_description">Poin pembahasan *</label><textarea id="agenda_description" name="agenda_description" required rows="4">{{ old('agenda_description', $meeting->agenda?->description) }}</textarea>
<hr><h2>Peserta rapat</h2><p class="muted">Pilih minimal satu peserta. Pengingat dan konfirmasi dapat diatur peserta sendiri.</p>
@php($selected = old('participants', $meeting->participants->pluck('user_id')->all()))
<div class="participant-grid">
@foreach($users as $user)<label class="check-card"><input type="checkbox" name="participants[]" value="{{ $user->id }}" @checked(in_array($user->id, $selected))><span><strong>{{ $user->name }}</strong><small>{{ App\Models\User::ROLES[$user->role] }}</small></span></label>
@endforeach</div>
<div class="form-actions"><button class="button" type="submit">{{ $meeting->exists ? 'Simpan perubahan' : 'Simpan jadwal rapat' }}</button><a class="button secondary" href="{{ route('meetings.index') }}">Batal</a></div></form>
@endsection

