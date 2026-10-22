@extends('layouts.app')

@section('title', 'Edit Periode Unit Cost')

@section('content')
<div class="w-full max-w-4xl mx-auto space-y-4">
    @include('unit-cost.periods._form')
</div>
@endsection
