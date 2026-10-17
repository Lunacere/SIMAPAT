@extends('layouts.app')
@section('title', $client->exists ? 'Ubah klien' : 'Tambah klien')
@section('content')
<div class="page-heading"><div><h1>{{ $client->exists ? 'Ubah klien' : 'Tambah klien' }}</h1></div></div>
<form class="panel form-panel" method="post" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}">@csrf 
@if($client->exists)@method('PUT')
@endif
<label for="name">Nama klien *</label><input id="name" name="name" required value="{{ old('name', $client->name) }}">
<label for="contact">Kontak</label><input id="contact" name="contact" value="{{ old('contact', $client->contact) }}">
<label for="information">Informasi</label><textarea id="information" name="information" rows="4">{{ old('information', $client->information) }}</textarea>
<label for="progress">Perkembangan terkini</label><textarea id="progress" name="progress" rows="4">{{ old('progress', $client->progress) }}</textarea>
<div class="form-actions"><button class="button" type="submit">Simpan klien</button><a class="button secondary" href="{{ route('clients.index') }}">Batal</a></div></form>
@endsection
