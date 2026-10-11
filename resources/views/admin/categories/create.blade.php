@extends('layouts.admin')
@section('title', 'নতুন ক্যাটাগরি')

@section('content')
  <h1>নতুন ক্যাটাগরি</h1>
  <div class="card">@include('admin.categories._form')</div>
@endsection
