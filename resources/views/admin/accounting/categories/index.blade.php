@extends('admin.layout')

@section('title', $info[0])

@section('content')
  <h1 class="ad-title">{{ $info[2] }} {{ $info[0] }}</h1>

  <div class="ad-tabs">
    <a href="{{ route('admin.accounting.categories.income') }}" class="{{ $type === 'income' ? 'on' : '' }}">📥 Income</a>
    <a href="{{ route('admin.accounting.categories.expense') }}" class="{{ $type === 'expense' ? 'on' : '' }}">📤 Expense</a>
  </div>

  <section class="ad-card">
    <div class="ad-card-head"><h2>Your {{ strtolower($info[1]) }} categories</h2></div>
    @if ($categories->isEmpty())
      <p class="ad-muted">No category yet. Add your first one below.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Name</th><th class="r">Entries</th><th></th></tr>
          @foreach ($categories as $c)
            <tr>
              <td><strong>{{ $c->name }}</strong></td>
              <td class="r">{{ $usage[$c->id] ?? 0 }}</td>
              <td class="ad-actions">
                <a class="ad-link" href="{{ route('admin.accounting.categories.' . $type, ['edit' => $c->id]) }}#catForm">Edit</a>
                <form action="{{ route('admin.accounting.categories.destroy', [$type, $c->id]) }}" method="POST" onsubmit="return confirm('Delete this category?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="ad-link ad-del">Delete</button>
                </form>
              </td>
            </tr>
          @endforeach
        </table>
      </div>
      <p class="ad-hint">A category that is used by entries cannot be deleted. You can rename it instead.</p>
    @endif
  </section>

  <section class="ad-card" id="catForm">
    @if ($editing)
      <h2>Edit category</h2>
      <form class="ad-form" action="{{ route('admin.accounting.categories.update', [$type, $editing->id]) }}" method="POST">
        @csrf
        @method('PUT')
        <label>Category name
          <input type="text" name="name" value="{{ old('name', $editing->name) }}" maxlength="60" required>
        </label>
        <div class="ad-submit">
          <button type="submit" class="ad-btn">Save changes</button>
          <a class="ad-btn ad-btn-gray" href="{{ route('admin.accounting.categories.' . $type) }}">Cancel</a>
        </div>
      </form>
    @else
      <h2>Add category</h2>
      <form class="ad-form" action="{{ route('admin.accounting.categories.store', $type) }}" method="POST">
        @csrf
        <label>Category name
          <input type="text" name="name" value="{{ old('name') }}" maxlength="60" placeholder="{{ $type === 'income' ? 'e.g. Product sales, Service charge' : 'e.g. Shop rent, Salary, Transport' }}" required>
        </label>
        <div class="ad-submit"><button type="submit" class="ad-btn">Add category</button></div>
      </form>
    @endif
  </section>
@endsection
