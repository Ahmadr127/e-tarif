@extends('errors.layout')

@section('code', '401')
@section('title', 'Belum Masuk')

@section('message')
    Anda harus masuk terlebih dahulu untuk mengakses halaman ini.
@endsection

@section('actions')
    <a class="btn btn-primary" href="{{ url('/login') }}">Masuk</a>
    <a class="btn btn-ghost" href="{{ url('/') }}">Kembali ke Beranda</a>
@endsection
