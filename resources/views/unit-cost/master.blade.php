@extends('layouts.app')

@section('title', 'Master Unit Cost')

@section('content')
@php
    $scopeLabels = [
        'global' => 'Global',
        'unit' => 'Unit',
        'period' => 'Periode',
        'unit_period' => 'Unit + Periode',
    ];
    $valueTypeLabels = [
        'decimal' => 'Desimal',
        'integer' => 'Bilangan Bulat',
        'boolean' => 'Boolean',
        'string' => 'Teks',
    ];
@endphp

<div class="w-full mx-auto space-y-4">
    @include('unit-cost.master._filters')

    @if($section === 'components')
        <x-card padding="false">
            <x-slot name="title">Master Komponen</x-slot>
            <x-slot name="subtitle">Definisi komponen dipakai ulang oleh seluruh periode. Nilai dan rumus tidak disimpan di sini.</x-slot>
            <x-slot name="actions">
                <a href="{{ route('calculations.master.components.create') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                    <i class="bi bi-plus-lg"></i> Tambah Komponen
                </a>
            </x-slot>

            <div class="p-4">
                @include('unit-cost.master._components')
            </div>
        </x-card>
    @elseif($section === 'parameters')
        <x-card padding="false">
            <x-slot name="title">Master Parameter</x-slot>
            <x-slot name="subtitle">Definisi parameter, kode variabel, satuan, dan scope. Nilai dan rumus disimpan per periode.</x-slot>
            <x-slot name="actions">
                <a href="{{ route('calculations.master.parameters.create') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                    <i class="bi bi-plus-lg"></i> Tambah Parameter
                </a>
            </x-slot>

            <div class="p-4">
                @include('unit-cost.master._parameters')
            </div>
        </x-card>
    @else
        <x-card padding="false">
            <x-slot name="title">Riwayat Versi Formula</x-slot>
            <x-slot name="subtitle">Setiap perubahan teks rumus pada periode membuat versi baru. Versi lama tidak diubah.</x-slot>

            @include('unit-cost.master._formula-history')
        </x-card>
    @endif
</div>
@endsection
