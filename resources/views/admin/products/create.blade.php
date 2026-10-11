@extends('layouts.admin')
@section('title', 'নতুন প্রোডাক্ট')

@section('content')
  <h1>নতুন প্রোডাক্ট</h1>
  <div class="card">@include('admin.products._form')</div>
@endsection
