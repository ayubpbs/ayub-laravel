@extends('admin.layout')

@section('title', 'Access denied')

@section('content')
  <section class="ad-card" style="max-width:560px">
    <h1 class="ad-title">🔒 Access denied</h1>
    <p>Your account does not have permission to open this page. If you need it, please ask the owner to give you access.</p>
    <div class="ad-submit">
      @if (!empty($home))
        <a class="ad-btn" href="{{ $home }}">Go to my page</a>
      @endif
      <form action="{{ route('admin.logout') }}" method="POST">
        @csrf
        <button type="submit" class="ad-btn ad-btn-gray">Logout</button>
      </form>
    </div>
  </section>
@endsection
