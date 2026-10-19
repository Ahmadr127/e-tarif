@extends('errors.layout')

@php
    // View cadangan untuk semua 4xx yang tidak punya halaman khusus
    // (mis. 402, 405, 422). Kode & pesan mengikuti status dari exception.
    $statusCode = $exception?->getStatusCode() ?? 400;

    $messages = [
        400 => 'Permintaan tidak dapat diproses. Periksa kembali data yang dikirim, lalu coba lagi.',
        401 => 'Anda harus masuk terlebih dahulu untuk membuka halaman ini.',
        403 => 'Anda tidak memiliki hak akses untuk membuka halaman ini.',
        404 => 'Halaman yang Anda cari tidak ditemukan. Periksa kembali alamat URL, atau kembali ke halaman utama.',
        405 => 'Metode permintaan tidak diizinkan untuk halaman ini.',
        419 => 'Sesi Anda sudah berakhir. Muat ulang halaman, lalu coba lagi.',
        429 => 'Terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar, lalu coba lagi.',
    ];
@endphp

@section('code', $statusCode)
@section('title', 'Permintaan Gagal')
@section('message', $messages[$statusCode] ?? 'Permintaan Anda tidak dapat diproses. Silakan kembali ke halaman utama dan coba lagi.')
