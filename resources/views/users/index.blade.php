@extends('layouts.app')
@section('title', 'Pengguna')
@section('content')
<div class="page-heading"><div><h1>Pengguna</h1><p class="muted">Kelola akun internal dan akun klien.</p></div><a class="button" href="{{ route('users.create') }}">Tambah pengguna</a></div>
<section class="panel users-panel"><div class="section-heading"><h2>Daftar akun</h2><span class="badge">{{ $users->total() }} akun</span></div><div class="table-wrap"><table role="table" class="users-table" aria-label="Daftar pengguna"><thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Nama</th><th role="columnheader" scope="col">Username</th><th role="columnheader" scope="col">Email</th><th role="columnheader" scope="col">Peran</th><th role="columnheader" scope="col">Tindakan</th></tr></thead><tbody role="rowgroup">
@forelse($users as $user)<tr role="row"><td role="cell" class="user-name"><div class="user-identity"><span class="avatar" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span><strong>{{ $user->name }}</strong></div></td><td role="cell" data-label="Username">{{ $user->username }}</td><td role="cell" class="user-email" data-label="Email">{{ $user->email }}</td><td role="cell" data-label="Peran"><span class="badge role-{{ $user->role }}">{{ App\Models\User::ROLES[$user->role] }}</span></td><td role="cell" class="user-actions"><div class="inline-form"><a class="button secondary small" aria-label="Ubah {{ $user->name }}" href="{{ route('users.edit', $user) }}">Ubah</a>
@can('delete', $user)<form data-confirm="Hapus akun ini? Akun yang masih terkait rapat atau tugas akan ditolak." method="post" action="{{ route('users.destroy', $user) }}">@csrf @method('DELETE')<button class="button danger small" aria-label="Hapus {{ $user->name }}" type="submit">Hapus</button></form>
@endcan</div></td></tr>
@empty<tr role="row"><td role="cell" colspan="5">Belum ada pengguna.</td></tr>
@endforelse</tbody></table></div><div class="pagination">{{ $users->links() }}</div></section>
@endsection
