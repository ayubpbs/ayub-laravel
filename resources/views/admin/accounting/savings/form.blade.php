@extends('admin.layout')

@php
  $editing = !empty($item);
  $k = $kinds[$kind];
@endphp
@section('title', $editing ? 'Edit ' . $k['label'] : 'Add savings / loan')

@section('content')
  <p class="ad-crumbs"><a href="{{ $editing ? route('admin.accounting.savings.show', $item->id) : route('admin.accounting.savings.index') }}">← Back</a></p>
  <h1 class="ad-title">{{ $k['icon'] }} {{ $editing ? 'Edit ' . $k['label'] : 'Add savings / loan / receivable' }}</h1>

  <section class="ad-card">
    <form class="ad-form" method="POST"
          action="{{ $editing ? route('admin.accounting.savings.update', $item->id) : route('admin.accounting.savings.store') }}">
      @csrf
      @if ($editing) @method('PUT') @endif

      @if (!$editing)
        <label>Type
          <select name="kind" id="kindSel" required>
            @foreach ($kinds as $key => $kk)
              <option value="{{ $key }}" data-moves="{{ $kk['moves_at_start'] ? 1 : 0 }}" data-asset="{{ $kk['side'] === 'asset' ? 1 : 0 }}"
                      data-party="{{ $kk['party_label'] }}" data-hint="{{ $kk['title_hint'] }}"
                      {{ old('kind', $kind) === $key ? 'selected' : '' }}>{{ $kk['icon'] }} {{ $kk['label'] }} ({{ $kk['bn'] }})</option>
            @endforeach
          </select>
        </label>
      @endif

      <div class="ad-row">
        <label>Name / title
          <input type="text" name="title" id="fTitle" value="{{ old('title', $editing ? $item->title : '') }}" maxlength="80" placeholder="{{ $k['title_hint'] }}" required>
        </label>
        <label><span id="partyLbl">{{ $k['party_label'] }}</span> <small>(optional)</small>
          <input type="text" name="party" value="{{ old('party', $editing ? $item->party : '') }}" maxlength="80">
        </label>
      </div>

      <div class="ad-row">
        <label>Amount (৳)
          <input type="number" name="principal" value="{{ old('principal', $editing ? $item->principal : '') }}" step="0.01" min="0.01" {{ ($editing && $locked) ? 'disabled' : 'required' }}>
        </label>
        <label>Certificate / FDR no. <small>(optional)</small>
          <input type="text" name="reference_no" value="{{ old('reference_no', $editing ? $item->reference_no : '') }}" maxlength="40">
        </label>
        <label>Interest rate % <small>(optional, for information)</small>
          <input type="number" name="interest_rate" value="{{ old('interest_rate', $editing ? $item->interest_rate : '') }}" step="0.01" min="0" max="100">
        </label>
      </div>

      <div class="ad-row">
        <label>Start date
          <input type="date" name="start_date" id="startDate" value="{{ old('start_date', $editing ? $item->start_date->toDateString() : now()->toDateString()) }}" required>
        </label>
        <label>Due / maturity date <small>(optional)</small>
          <input type="date" name="maturity_date" value="{{ old('maturity_date', $editing && $item->maturity_date ? $item->maturity_date->toDateString() : '') }}">
        </label>
      </div>

      <label>Note <small>(optional)</small>
        <input type="text" name="note" value="{{ old('note', $editing ? $item->note : '') }}" maxlength="255">
      </label>

      @if (!$editing)
        <div id="acctBox">
          <label><span id="acctLbl">Money paid from</span> <small>(optional)</small>
            <select name="account_id">
              <option value="">— Do not change any account balance —</option>
              @foreach ($accounts as $a)
                <option value="{{ $a['id'] }}" {{ (string) old('account_id') === (string) $a['id'] ? 'selected' : '' }}>{{ $a['label'] }}</option>
              @endforeach
            </select>
          </label>
          <p class="ad-hint">Choose an account only if this money has <strong>not</strong> already been taken out of (or added to) that account's balance. Then the account balance is changed now and the total stays correct. If you leave it empty, only this list is updated.</p>
        </div>
      @endif

      <div class="ad-submit">
        <button type="submit" class="ad-btn">{{ $editing ? 'Save changes' : 'Save' }}</button>
        <a class="ad-btn ad-btn-gray" href="{{ $editing ? route('admin.accounting.savings.show', $item->id) : route('admin.accounting.savings.index') }}">Cancel</a>
      </div>
    </form>
    @if ($editing && $locked)
      <p class="ad-hint">The amount cannot be changed because an account entry was made from it. To correct it, delete this item and add it again.</p>
    @endif
  </section>

  @if (!$editing)
  <script>
    (function () {
      var sel = document.getElementById('kindSel'); if (!sel) return;
      function sync() {
        var o = sel.options[sel.selectedIndex];
        document.getElementById('partyLbl').textContent = o.dataset.party;
        document.getElementById('fTitle').placeholder = o.dataset.hint;
        document.getElementById('acctBox').style.display = o.dataset.moves === '1' ? '' : 'none';
        document.getElementById('acctLbl').textContent = o.dataset.asset === '1' ? 'Money paid from' : 'Money received into';
      }
      sel.addEventListener('change', sync); sync();
      // default the date to the browser's local day (the server clock may be in another time zone)
      var d = document.getElementById('startDate');
      if (d && !{{ old('start_date') ? 'true' : 'false' }}) {
        d.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
      }
    })();
  </script>
  @endif
@endsection
