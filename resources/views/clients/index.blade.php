@extends('layouts.app')
@section('title', 'Klien')
@section('content')
<div class="page-heading"><div><h1>Klien</h1><p class="muted">Kontak dan perkembangan terkini perusahaan klien.</p></div>
@can('create', App\Models\Client::class)<a class="button" href="{{ route('clients.create') }}">Tambah klien</a>
@endcan</div>
<section class="panel responsive-panel"><div class="table-wrap"><table role="table" class="responsive-table clients-table" aria-label="Daftar klien"><thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Klien</th><th role="columnheader" scope="col">Kontak</th><th role="columnheader" scope="col">Informasi</th><th role="columnheader" scope="col">Perkembangan terkini</th><th role="columnheader" scope="col">Rapat</th><th role="columnheader" scope="col">Tindakan</th></tr></thead><tbody role="rowgroup">
@forelse($clients as $client)<tr role="row"><td role="cell" class="record-primary"><strong>{{ $client->name }}</strong></td><td role="cell" class="record-schedule" data-label="Kontak">{{ $client->contact ?? 'Belum diisi' }}</td><td role="cell" class="record-schedule" data-label="Informasi"><p class="prose">{{ $client->information ?? 'Belum diisi' }}</p></td><td role="cell" class="record-schedule" data-label="Perkembangan terkini"><p class="prose">{{ $client->progress ?? 'Belum diisi' }}</p></td><td role="cell" data-label="Rapat">{{ $client->meetings_count }} rapat</td><td role="cell" class="record-actions">
@can('update', $client)<div class="inline-form"><a class="button secondary small" aria-label="Ubah klien {{ $client->name }}" href="{{ route('clients.edit', $client) }}">Ubah klien</a><form data-confirm="Hapus klien ini? Klien yang masih terkait rapat akan ditolak." method="post" action="{{ route('clients.destroy', $client) }}">@csrf @method('DELETE')<button class="button danger small" aria-label="Hapus klien {{ $client->name }}" type="submit">Hapus klien</button></form></div>
@else<span class="muted">Akses baca</span>
@endcan</td></tr>
@empty<tr role="row"><td role="cell" colspan="6"><div class="empty"><h3>Belum ada klien</h3>
@can('create', App\Models\Client::class)<p>Tambahkan perusahaan klien sebelum mengaitkannya dengan rapat.</p><a class="button" href="{{ route('clients.create') }}">Tambah klien</a>
@else<p>Data klien akan tampil setelah ditambahkan oleh Operator.</p>
@endcan</div></td></tr>
@endforelse</tbody></table></div><div class="pagination">{{ $clients->links() }}</div></section>
@endsection

