<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Admin Login — {{ config('shop.name') }}</title>
  <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
</head>
<body class="ad-login-body">
  <div class="ad-login">
    <h1>⚙️ {{ config('shop.name') }}</h1>
    <p class="ad-muted">Admin login</p>

    @if (session('error'))
      <div class="ad-flash ad-err">{{ session('error') }}</div>
    @endif

    @if ($disabled)
      <div class="ad-flash ad-warn">
        <strong>The admin panel is not enabled yet.</strong><br>
        Add these two lines at the end of your project's <code>.env</code> file:
        <pre>ADMIN_USER=admin
ADMIN_PASSWORD=a_strong_password_here</pre>
        Then stop <code>php artisan serve</code>, start it again and refresh this page.
      </div>
    @else
      <form action="{{ route('admin.login.submit') }}" method="POST" class="ad-form">
        @csrf
        <label>Username
          <input type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username">
        </label>
        <label>Password
          <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="ad-btn ad-btn-block">Login</button>
      </form>
    @endif
  </div>
</body>
</html>
