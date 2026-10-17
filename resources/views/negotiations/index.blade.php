@extends('layouts.app')
@section('title', 'Negosiasi jadwal')
@section('content')
<div class="page-heading"><div><h1>Negosiasi jadwal</h1><p class="muted">Tinjau ajuan dan sepakati waktu rapat bersama.</p></div></div>
@if($awaitingMe)<div class="filter-summary" role="status"><p>Menampilkan ajuan yang perlu Anda tanggapi.</p><a class="text-link" href="{{ route('negotiations.index') }}">Tampilkan semua negosiasi</a></div>
@endif
<div class="negotiation-list">
@forelse($meetings as $meeting)
@php
    $latest = $meeting->latestProposal;
    $isOpen = $meeting->status === 'scheduled' && $meeting->starts_at->isFuture();
    $hasPending = $isOpen && $latest?->status === 'pending' && $latest->meeting_version === $meeting->version;
    $needsResponse = $hasPending && (auth()->user()->role === 'client') === ($latest->proposed_party === 'company');
    $schedules = [['Jadwal rapat saat ini', $meeting->starts_at, $meeting->ends_at]];
    if ($hasPending) {
        $schedules[] = ['Ajuan terbaru', $latest->starts_at, $latest->ends_at];
    }
@endphp
<a class="panel negotiation-card" href="{{ route('negotiations.show', $meeting) }}">
    <div class="section-heading">
        <span class="badge {{ $hasPending ? 'pending' : ($isOpen ? $latest?->status : $meeting->status) }}">
            @if(! $isOpen){{ $meeting->status === 'scheduled' ? 'Negosiasi ditutup' : App\Models\Meeting::STATUSES[$meeting->status] }}
            @elseif($needsResponse)Perlu tanggapan Anda
            @elseif($hasPending)Menunggu {{ $latest->proposed_party === 'company' ? 'klien' : 'perusahaan' }}
            @elseif($latest?->status === 'accepted')Jadwal disepakati
            @else Belum ada ajuan aktif
            @endif
        </span>
        <span class="row-arrow" aria-hidden="true">→</span>
    </div>
    <h2>{{ $meeting->title }}</h2><p class="muted">{{ $meeting->client->name }}</p>
    <dl class="schedule-summary">
        @foreach($schedules as [$label, $startsAt, $endsAt])
        <div class="{{ $hasPending && $loop->last ? 'proposal-summary' : '' }}"><dt>{{ $label }}</dt><dd>
            @include('layouts.schedule-time', ['startsAt' => $startsAt, 'endsAt' => $endsAt])
        </dd></div>
        @endforeach
    </dl>
    @if($hasPending)<p class="muted">Diajukan {{ $latest->proposed_party === 'company' ? 'perusahaan' : 'klien' }} · {{ $latest->proposer->name }}</p>
    @elseif(! $isOpen && $meeting->status === 'scheduled')<p class="muted">Rapat sudah dimulai. Ajuan baru tidak dapat dikirim.</p>
    @endif
</a>
@empty
<section class="panel empty">
    @if($awaitingMe)<h2>Tidak ada ajuan yang perlu Anda tanggapi</h2><p>Ajuan baru dari pihak lain akan tampil di sini.</p>
    @elseif(auth()->user()->role === 'client')<h2>Belum ada rapat untuk dinegosiasikan</h2><p>{{ auth()->user()->client_id ? 'Rapat akan tampil setelah perusahaan menjadwalkan rapat dengan klien Anda.' : 'Akun Anda belum ditautkan ke perusahaan klien. Hubungi Admin untuk menautkan akun.' }}</p>
    @elseif(auth()->user()->role === 'operator')<h2>Belum ada rapat dengan klien</h2><p>Jadwalkan rapat dan pilih klien untuk memulai negosiasi.</p><a class="button" href="{{ route('meetings.create') }}">Jadwalkan rapat</a>
    @else<h2>Belum ada negosiasi jadwal</h2><p>Rapat akan tampil setelah Operator menjadwalkan rapat dengan klien.</p>
    @endif
</section>
@endforelse
</div><div class="pagination">{{ $meetings->links() }}</div>
@endsection

