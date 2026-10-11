@extends('layouts.admin')
@section('title', 'ক্যাটাগরি এডিট')

@section('content')
  <h1>ক্যাটাগরি এডিট</h1>
  <div class="card">@include('admin.categories._form')</div>
@endsection
