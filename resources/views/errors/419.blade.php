@extends('errors.layout')

@section('code', '419')
@section('title', 'Sesi Kedaluwarsa')

@section('message')
    Sesi Anda telah berakhir karena halaman ini terbuka terlalu lama. Muat ulang halaman, lalu coba lagi.
@endsection

@section('actions')
    <button type="button" class="btn btn-primary" onclick="window.location.reload()">Muat Ulang Halaman</button>
    <a class="btn btn-ghost" href="{{ url('/login') }}">Masuk Kembali</a>
@endsection
