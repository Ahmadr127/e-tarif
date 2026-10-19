@extends('layouts.app')

@section('title', 'Buku Tarif')

@section('content')
<div class="max-w-2xl mx-auto">
    <x-card padding="false">
        <x-slot name="title">Buku Tarif</x-slot>

        <div class="px-6 py-10 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                <i class="bi bi-exclamation-triangle text-xl"></i>
            </div>
            <p class="mt-3 text-sm font-semibold text-gray-900">Akun Anda belum terhubung ke unit organisasi</p>
            <p class="mt-1 text-sm text-gray-500">
                Hubungi administrator untuk menghubungkan akun Anda dengan unit organisasi,
                agar buku tarif unit dapat ditampilkan.
            </p>
            <a href="{{ route('dashboard') }}" class="mt-4 inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </x-card>
</div>
@endsection
