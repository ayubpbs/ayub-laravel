@extends('admin.layout')

@section('title', $title)

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">{{ $title }}</h1>
  </div>

  <section class="ad-card">
    <p class="ad-muted">This report is not ready yet. It will be added in the next step.</p>
  </section>
@endsection
