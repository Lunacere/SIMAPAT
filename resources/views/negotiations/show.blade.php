@extends('layouts.app')
@section('title', 'Negosiasi jadwal')
@section('content')
@php($latest = $meeting->latestProposal)
@php($isOpen = $meeting->status === 'scheduled' && $meeting->starts_at->isFuture())
<div class="page-heading"><div><h1>{{ $meeting->title }}</h1><p class="muted">{{ $meeting->client->name }} · Dikelola {{ $meeting->creator->name }}</p></div><a class="button secondary" href="{{ route('negotiations.index') }}">Semua negosiasi</a></div>
@if(! $hasClientAccounts)<div class="alert error" role="status">Belum ada akun Klien untuk perusahaan ini. Minta Admin menautkan akun Klien agar ajuan dapat ditanggapi.</div>
@endif
<div class="negotiation-grid"><div class="detail-main">
<section class="panel"><div class="section-heading"><h2>Jadwal rapat saat ini</h2><span class="badge {{ $meeting->status }}">{{ App\Models\Meeting::STATUSES[$meeting->status] }}</span></div><dl class="info-list"><dt>Mulai</dt><dd>{{ $meeting->starts_at->translatedFormat('l, j F Y · H:i') }} WIB</dd><dt>Selesai</dt><dd>{{ $meeting->ends_at->translatedFormat('l, j F Y · H:i') }} WIB</dd><dt>Tempat/media</dt><dd>{{ $meeting->location }}</dd></dl><p class="muted">Jadwal alternatif akan berlaku setelah disetujui oleh pihak penerima.</p></section>
<section class="panel proposal-current" aria-labelledby="proposal-heading">
    
    @if($latest)
        <div class="section-heading">
            <h2 id="proposal-heading">{{ $latest->status === 'accepted' ? 'Jadwal disepakati' : 'Ajuan terbaru' }}</h2>
            <span class="badge {{ $latest->status }}">{{ $latest->status === 'pending' && ! $isOpen ? 'Ajuan ditutup' : App\Models\ScheduleProposal::STATUSES[$latest->status] }}</span>
        </div>
        <p class="proposal-by">Diajukan oleh <strong>{{ $latest->proposer->name }}</strong><span class="proposal-party">{{ $latest->proposed_party === 'company' ? 'Perusahaan' : 'Klien' }}</span></p>
        <div class="proposal-schedule">
            @if($latest->starts_at->format('Y-m-d') === $latest->ends_at->format('Y-m-d'))
                <span class="proposal-label">Jadwal yang diajukan</span>
                <p class="proposal-date">{{ $latest->starts_at->translatedFormat('l, j F Y') }}</p>
                <p class="proposal-time"><time datetime="{{ $latest->starts_at->toIso8601String() }}">{{ $latest->starts_at->format('H:i') }}</time><span>–</span><time datetime="{{ $latest->ends_at->toIso8601String() }}">{{ $latest->ends_at->format('H:i') }}</time><span class="proposal-timezone">WIB</span></p>
            @else
                <dl class="proposal-range">
                    <div><dt>Mulai</dt><dd><time datetime="{{ $latest->starts_at->toIso8601String() }}"><span>{{ $latest->starts_at->translatedFormat('l, j F Y') }}</span><strong>{{ $latest->starts_at->format('H:i') }} WIB</strong></time></dd></div>
                    <div><dt>Selesai</dt><dd><time datetime="{{ $latest->ends_at->toIso8601String() }}"><span>{{ $latest->ends_at->translatedFormat('l, j F Y') }}</span><strong>{{ $latest->ends_at->format('H:i') }} WIB</strong></time></dd></div>
                </dl>
            @endif
        </div>
        @if($latest->message !== null && $latest->message !== '')
            <div class="proposal-note"><span class="proposal-label">Catatan pengusul</span><p class="prose">{{ $latest->message }}</p></div>
        @endif
        @if($latest->status === 'accepted')
            <p class="proposal-feedback">Disetujui {{ $latest->responder?->name }} pada {{ $latest->responded_at?->translatedFormat('j F Y · H:i') }} WIB.</p>
        @elseif($latest->status === 'pending' && $isOpen)
            @can('negotiate', $meeting)
                @if((auth()->user()->role === 'client') === ($latest->proposed_party === 'company'))
                    <div class="proposal-response">
                        <p>Apakah jadwal ini sesuai?</p>
                        <div class="proposal-actions">
                            <form method="post" action="{{ route('negotiations.accept', [$meeting, $latest]) }}">@csrf @method('PATCH')<button class="button" type="submit">Setujui jadwal ini</button></form>
                            <a class="button secondary" href="#schedule-alternative">Ajukan jadwal lain</a>
                        </div>
                        <p class="proposal-feedback">Jadwal rapat akan diperbarui setelah Anda menyetujui ajuan ini.</p>
                    </div>
                @else
                    <p class="proposal-feedback">Ajuan sudah terkirim. Menunggu tanggapan {{ $latest->proposed_party === 'company' ? 'klien' : 'perusahaan' }}.</p>
                @endif
            @endcan
        @endif
    @else
        <h2 id="proposal-heading">Belum ada ajuan jadwal</h2><p class="proposal-feedback">Kirim jadwal yang ditawarkan agar pihak penerima dapat menanggapinya.</p>
    @endif
    @if(! $isOpen)
        <p class="proposal-feedback">Negosiasi ditutup karena rapat sudah dimulai atau ditutup.</p>
    @endif
</section>
</div><section class="panel" id="schedule-alternative" tabindex="-1">
@can('negotiate', $meeting)
@if($isOpen)<h2>Ajukan jadwal lain</h2><p class="muted">Kirim waktu dan catatan kepada pihak penerima.</p>
<form method="post" action="{{ route('negotiations.store', $meeting) }}">@csrf<input type="hidden" name="version" value="{{ $meeting->version }}"><input type="hidden" name="latest_proposal_id" value="{{ $latest?->id ?? 0 }}">
<label for="starts_at">Mulai (WIB) *</label><input id="starts_at" type="datetime-local" name="starts_at" required value="{{ old('starts_at', ($latest?->starts_at ?? $meeting->starts_at)->format('Y-m-d\TH:i')) }}">
<label for="ends_at">Selesai (WIB) *</label><input id="ends_at" type="datetime-local" name="ends_at" required value="{{ old('ends_at', ($latest?->ends_at ?? $meeting->ends_at)->format('Y-m-d\TH:i')) }}">
<label for="message">Catatan (opsional)</label><textarea id="message" name="message" rows="4" maxlength="2000" placeholder="Contoh: tim kami baru tersedia setelah makan siang.">{{ old('message') }}</textarea>
<div class="form-actions"><button class="button" type="submit">Kirim ajuan jadwal</button></div></form>
@else<h2>Negosiasi ditutup</h2><p class="muted">Tidak ada perubahan jadwal untuk rapat ini.</p>
@endif
@else<h2>Pantauan Admin</h2><p class="muted">Ajuan jadwal hanya tersedia bagi pihak yang berwenang.</p>
@endcan
</section></div>
@can('chat', $meeting)
<section class="panel meeting-chat" data-meeting-chat data-url="{{ route('chat.index', $meeting) }}" data-auth-url="{{ url('/broadcasting/auth') }}" data-user-id="{{ auth()->id() }}" data-channel="meeting-chat.{{ $meeting->id }}.user.{{ auth()->id() }}">
<div class="section-heading"><div><h2>Chat jadwal rapat</h2></div><small class="muted" data-chat-status role="status">Menghubungkan…</small></div>
<p class="muted">Chat membantu membahas waktu. Persetujuan jadwal tetap melalui kartu ajuan di atas.</p>
<button class="button secondary small" type="button" data-chat-older hidden>Pesan sebelumnya</button>
<div class="chat-messages" data-chat-messages role="log" aria-label="Riwayat chat" aria-live="polite"></div>
<form><label for="chat-body">Pesan</label><textarea id="chat-body" name="body" rows="3" maxlength="2000" required @disabled($meeting->status !== 'scheduled') placeholder="Tulis waktu yang cocok untuk rapat…"></textarea><div class="form-actions"><button class="button" type="submit" @disabled($meeting->status !== 'scheduled')>Kirim pesan</button><small class="muted">Maksimal 2.000 karakter</small></div></form>
<p class="small-text muted" data-chat-error role="status"></p>
@if($meeting->status !== 'scheduled')<p class="muted">Rapat sudah ditutup. Chat hanya dapat dibaca.</p>
@endif
</section>
@endcan
<section class="panel negotiation-history"><div class="section-heading"><h2>Riwayat negosiasi</h2><span class="muted">{{ $proposals->total() }} ajuan</span></div>
@forelse($proposals as $proposal)<article class="proposal-history-row"><div class="section-heading"><strong>{{ $proposal->proposed_party === 'company' ? 'Perusahaan' : 'Klien' }} · {{ $proposal->proposer->name }}</strong><span class="badge {{ $proposal->status }}">{{ $proposal->status === 'pending' && ! $isOpen ? 'Ajuan ditutup' : App\Models\ScheduleProposal::STATUSES[$proposal->status] }}</span></div>
@include('layouts.schedule-time', ['startsAt' => $proposal->starts_at, 'endsAt' => $proposal->ends_at])
@if($proposal->message)<p class="prose">{{ $proposal->message }}</p>
@endif<p class="muted">Dikirim {{ $proposal->created_at->translatedFormat('j F Y · H:i') }} WIB</p></article>
@empty<p class="muted">Riwayat akan muncul setelah ajuan pertama dikirim.</p>
@endforelse<div class="pagination">{{ $proposals->links() }}</div></section>
@endsection







