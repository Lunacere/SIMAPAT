@extends('layouts.app')
@section('title', $report ? 'Laporan rapat' : (request()->routeIs('history') ? 'Riwayat rapat' : 'Rapat'))
@section('content')
<div class="page-heading"><div><h1>{{ $report ? 'Rekap kegiatan rapat' : (request()->routeIs('history') ? 'Riwayat rapat' : 'Rapat tim') }}</h1><p class="muted">Jadwal, status, dan hasil rapat yang dapat Anda akses.</p></div>
@can('create', App\Models\Meeting::class)<a class="button" href="{{ route('meetings.create') }}">Jadwalkan rapat</a>
@endcan</div>
<section class="panel responsive-panel">
@if(request()->boolean('upcoming'))<div class="filter-summary"><span>Menampilkan rapat mendatang yang masih terjadwal.</span><a class="text-link" href="{{ url()->current() }}">Lihat semua rapat</a></div>
@endif
<form class="filters meeting-filters" method="get">
@if(request()->boolean('upcoming'))<input type="hidden" name="upcoming" value="1">
@endif
<div><label for="q">Cari rapat</label><input id="q" name="q" value="{{ old('q', request('q')) }}" placeholder="Cari berdasarkan judul"></div>
<div class="filter-actions"><button class="button" type="submit">Cari rapat</button><a class="text-link" href="{{ url()->current() }}">Hapus filter</a></div>
<details class="filter-options" @if(request()->anyFilled(['from', 'to', 'status', 'participant', 'client']) || $errors->hasAny(['from', 'to', 'status', 'participant', 'client'])) open @endif><summary>Filter tambahan</summary><div class="filters-grid">
<div><label for="from">Mulai tanggal</label><input id="from" type="date" name="from" value="{{ old('from', request('from')) }}"></div>
<div><label for="to">Sampai tanggal</label><input id="to" type="date" name="to" value="{{ old('to', request('to')) }}"></div>
<div><label for="status">Status</label><select id="status" name="status"><option value="">Semua status</option>
@foreach(App\Models\Meeting::STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('status', request('status')) === $value)>{{ $label }}</option>
@endforeach</select></div>
@if(auth()->user()->role !== 'client')<div><label for="participant">Peserta</label><select id="participant" name="participant"><option value="">Semua peserta</option>
@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('participant', request('participant')) == $user->id)>{{ $user->name }}</option>
@endforeach</select></div>
<div><label for="client">Klien</label><select id="client" name="client"><option value="">Semua klien</option>
@foreach($clients as $client)<option value="{{ $client->id }}" @selected(old('client', request('client')) == $client->id)>{{ $client->name }}</option>
@endforeach</select></div>
@endif
</div><button class="button secondary" type="submit">Terapkan filter</button></details>
</form>
<div class="section-heading compact"><h2>{{ $report ? 'Rekap sesuai filter' : 'Daftar rapat' }}</h2><span class="muted">{{ $meetings->total() }} rapat</span></div>
<div class="table-wrap"><table role="table" class="responsive-table meetings-table" aria-label="Daftar rapat"><thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Rapat</th><th role="columnheader" scope="col">Jadwal (WIB)</th><th role="columnheader" scope="col">Klien</th><th role="columnheader" scope="col">Peserta</th><th role="columnheader" scope="col">Status</th><th role="columnheader" scope="col">Tindakan</th></tr></thead><tbody role="rowgroup">
@forelse($meetings as $meeting)
<tr role="row" @class(['has-report' => $report])>
<td role="cell" class="record-primary"><strong>{{ $meeting->title }}</strong><small class="record-location" title="{{ $meeting->location }}">{{ $meeting->location }}</small></td>
<td role="cell" class="record-schedule" data-label="Jadwal (WIB)">@include('layouts.schedule-time', ['startsAt' => $meeting->starts_at, 'endsAt' => $meeting->ends_at])</td>
<td role="cell" data-label="Klien">{{ $meeting->client?->name ?? 'Internal' }}</td><td role="cell" data-label="Peserta">{{ $meeting->participants_count }} peserta</td>
<td role="cell" data-label="Status"><span class="badge {{ $meeting->status }}">{{ App\Models\Meeting::STATUSES[$meeting->status] }}</span></td>
<td role="cell" class="record-actions"><a class="button secondary small" aria-label="Buka rapat {{ $meeting->title }}" href="{{ route('meetings.show', $meeting) }}">Buka rapat</a></td>
</tr>
@if($report)<tr role="row" class="report-row"><td role="cell" colspan="6"><b>Keputusan</b><p>{{ $meeting->minute?->decisions ?? 'Notulensi belum tersedia.' }}</p><div class="report-tasks"><b>Tindak lanjut</b>
@forelse($meeting->followUps as $task)<div>{{ $task->task }} · {{ $task->user->name }} · {{ $task->deadline->translatedFormat('j F Y') }} · {{ App\Models\FollowUp::STATUSES[$task->status] }}</div>
@empty<p>Tidak ada tindak lanjut.</p>
@endforelse</div></td></tr>
@endif
@empty<tr role="row"><td role="cell" colspan="6"><div class="empty">
@if(request()->anyFilled(['q', 'from', 'to', 'status', 'participant', 'client', 'upcoming']))<h3>Tidak ada rapat yang cocok</h3><p>Ubah pencarian atau hapus filter untuk melihat rapat lainnya.</p><a class="button secondary" href="{{ url()->current() }}">Hapus filter</a>
@else<h3>Belum ada rapat</h3>
@can('create', App\Models\Meeting::class)<p>Jadwalkan rapat pertama dan tentukan pesertanya.</p><a class="button" href="{{ route('meetings.create') }}">Jadwalkan rapat</a>
@else
@if(auth()->user()->role === 'client')<p>Rapat akan tampil setelah akun Anda ditautkan ke perusahaan dan Operator mengundang perusahaan tersebut.</p><a class="button secondary" href="{{ route('negotiations.index') }}">Lihat negosiasi jadwal</a>
@elseif(auth()->user()->role === 'admin')<p>Rapat akan tampil setelah Operator membuat jadwal rapat.</p>
@else<p>Rapat akan tampil setelah Anda ditambahkan sebagai peserta.</p>
@endif
@endcan
@endif
</div></td></tr>
@endforelse
</tbody></table></div><div class="pagination">{{ $meetings->links() }}</div></section>
@endsection
