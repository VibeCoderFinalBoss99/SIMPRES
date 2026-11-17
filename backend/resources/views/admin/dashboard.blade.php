@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p>Selamat datang, {{ auth()->user()->name }}.</p>
        </div>
    </div>
@endsection
