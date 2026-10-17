@extends('layouts.app')
@section('title', 'Tugas saya')
@section('content')
<div class="page-heading"><div><h1>Tugas saya</h1><p class="muted">Tindak lanjut yang menjadi tanggung jawab Anda.</p></div><span class="badge">{{ $tasks->total() }} tugas</span></div>
@if(request('status') === 'active')<div class="filter-summary"><span>Menampilkan tugas yang belum selesai dari rapat yang tidak dibatalkan.</span><a class="text-link" href="{{ route('tasks.index') }}">Lihat semua tugas</a></div>
@endif
<section class="panel responsive-panel"><div class="table-wrap"><table role="table" class="responsive-table tasks-table" aria-label="Daftar tugas saya"><thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Tugas</th><th role="columnheader" scope="col">Rapat sumber</th><th role="columnheader" scope="col">Tenggat</th><th role="columnheader" scope="col">Status</th><th role="columnheader" scope="col">Perbarui</th></tr></thead><tbody role="rowgroup">
@forelse($tasks as $task)<tr role="row"><td role="cell" class="record-primary"><strong>{{ $task->task }}</strong></td><td role="cell" class="record-schedule" data-label="Rapat sumber">
@can('view', $task->meeting)<a class="text-link" href="{{ route('meetings.show', $task->meeting) }}">{{ $task->meeting->title }}</a>
@else<span class="muted">Rapat sumber terbatas</span>
@endcan</td><td role="cell" data-label="Tenggat">{{ $task->deadline->translatedFormat('j F Y') }}
@if($task->deadline->isBefore(today()) && $task->status !== 'completed')<small class="danger-text">Melewati tenggat</small>
@endif</td><td role="cell" data-label="Status"><span class="badge {{ $task->status }}">{{ App\Models\FollowUp::STATUSES[$task->status] }}</span></td><td role="cell" class="record-actions">
@if($task->meeting->status !== 'cancelled')<form class="inline-form" method="post" action="{{ route('follow-ups.status', [$task->meeting, $task]) }}">@csrf @method('PATCH')<select name="status" aria-label="Status tugas {{ $task->id }}">
@foreach(App\Models\FollowUp::STATUSES as $value => $label)<option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
@endforeach</select><button class="button small" type="submit">Simpan status</button></form>
@else<span class="muted">Rapat dibatalkan</span>
@endif</td></tr>
@empty<tr role="row"><td role="cell" colspan="5"><div class="empty"><h3>{{ request('status') === 'active' ? 'Tidak ada tugas aktif' : 'Belum ada tugas untuk Anda' }}</h3><p>{{ request('status') === 'active' ? 'Tidak ada tugas yang perlu diperbarui saat ini.' : 'Tugas yang diberikan Operator akan tampil di sini.' }}</p>
@if(request('status') === 'active')<a class="button secondary" href="{{ route('tasks.index') }}">Lihat semua tugas</a>
@endif</div></td></tr>
@endforelse
</tbody></table></div><div class="pagination">{{ $tasks->links() }}</div></section>
@endsection
