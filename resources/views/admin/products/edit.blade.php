@extends('layouts.admin')
@section('title', 'প্রোডাক্ট এডিট')

@section('content')
  <h1>প্রোডাক্ট এডিট</h1>
  <div class="card">@include('admin.products._form')</div>
@endsection
