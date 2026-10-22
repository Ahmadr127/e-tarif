@extends('layouts.app')

@section('title', $componentModel->exists ? 'Edit Komponen Unit Cost' : 'Tambah Komponen Unit Cost')

@section('content')
@php
    $isEdit = $componentModel->exists;
    $action = $isEdit
        ? route('calculations.master.components.update', $componentModel)
        : route('calculations.master.components.store');
@endphp

<div class="w-full max-w-4xl mx-auto space-y-4">
    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('calculations.master') }}" class="font-semibold text-sp-primary hover:underline">Master</a>
        <i class="bi bi-chevron-right text-xs"></i>
        <span class="font-semibold text-gray-700">{{ $isEdit ? 'Edit Komponen' : 'Tambah Komponen' }}</span>
    </div>

    <x-card padding="false">
        <x-slot name="title">{{ $isEdit ? 'Edit Komponen' : 'Tambah Komponen' }}</x-slot>
        <x-slot name="subtitle">Komponen mengelompokkan parameter unit cost. Kode komponen harus unik.</x-slot>
        <x-slot name="actions">
            <a href="{{ route('calculations.master') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </x-slot>

        <form method="POST" action="{{ $action }}" class="p-4 space-y-4">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <div class="font-semibold mb-1">Komponen belum dapat disimpan:</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="code" class="block text-sm font-semibold text-sp-navy mb-1">Kode Komponen <span class="text-red-500">*</span></label>
                    <input type="text" id="code" name="code" required maxlength="80"
                           value="{{ old('code', $componentModel->code) }}"
                           placeholder="contoh: SDM"
                           class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white font-mono focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                    @error('code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="name" class="block text-sm font-semibold text-sp-navy mb-1">Nama Komponen <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" required maxlength="255"
                           value="{{ old('name', $componentModel->name) }}"
                           placeholder="contoh: Sumber Daya Manusia"
                           class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                    @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="category" class="block text-sm font-semibold text-sp-navy mb-1">Kategori</label>
                    <input type="text" id="category" name="category" maxlength="80"
                           value="{{ old('category', $componentModel->category) }}"
                           placeholder="contoh: Biaya"
                           class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                    @error('category')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="sort_order" class="block text-sm font-semibold text-sp-navy mb-1">Urutan</label>
                    <input type="number" id="sort_order" name="sort_order" min="0" max="999999"
                           value="{{ old('sort_order', $componentModel->sort_order ?? 0) }}"
                           class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                    @error('sort_order')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-semibold text-sp-navy mb-1">Deskripsi</label>
                    <textarea id="description" name="description" rows="3" maxlength="2000"
                              class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">{{ old('description', $componentModel->description) }}</textarea>
                    @error('description')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2">
                    <input type="hidden" name="is_active" value="0">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <input type="checkbox" name="is_active" value="1"
                               @checked(old('is_active', $componentModel->is_active ?? true))
                               class="h-4 w-4 rounded border-gray-300 text-sp-primary focus:ring-sp-primary/30">
                        Aktif
                    </label>
                    @error('is_active')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
                <a href="{{ route('calculations.master') }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">Batal</a>
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                    <i class="bi bi-save"></i> Simpan
                </button>
            </div>
        </form>
    </x-card>
</div>
@endsection
