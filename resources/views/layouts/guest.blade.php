<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title') · SIMAPAT</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="login-page">
<section class="login-story"><a class="brand" href="{{ route('login') }}"><span class="brand-mark">S</span><span>SIMAPAT<small>Ruang koordinasi tim</small></span></a>
<div><h2>Kelola rapat dan tindak lanjut bersama tim.</h2><p>SIMAPAT menyediakan jadwal rapat, negosiasi dengan klien, dan pencatatan keputusan untuk PT Ascon.</p></div><small>Manajemen rapat · PT Ascon</small></section>
<main class="login-form"><div class="login-box">
@if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>
@endif
@include('layouts.validation-errors')
@yield('content')
</div></main></body></html>

