@extends('errors.layout')

@php
    // View cadangan untuk semua 5xx yang tidak punya halaman khusus (mis. 502, 504).
    $statusCode = $exception?->getStatusCode() ?? 500;

    $messages = [
        500 => 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.',
        502 => 'Server menerima respons yang tidak valid dari layanan lain. Silakan coba beberapa saat lagi.',
        503 => 'Layanan sedang tidak tersedia (misalnya karena pemeliharaan). Silakan coba beberapa saat lagi.',
        504 => 'Server membutuhkan waktu terlalu lama untuk merespons. Silakan coba beberapa saat lagi.',
    ];
@endphp

@section('code', $statusCode)
@section('title', 'Terjadi Kesalahan Server')
@section('message', $messages[$statusCode] ?? 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.')
