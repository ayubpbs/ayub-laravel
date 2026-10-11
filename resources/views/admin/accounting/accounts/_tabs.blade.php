@php
    $tabs = [
        'cash'  => ['💵', 'Cash',  route('admin.accounting.accounts.show', 'cash')],
        'bank'  => ['🏦', 'Bank',  route('admin.accounting.banks.index')],
        'bkash' => ['📱', 'bKash', route('admin.accounting.accounts.show', 'bkash')],
        'nagad' => ['📲', 'Nagad', route('admin.accounting.accounts.show', 'nagad')],
    ];
@endphp
<div class="ad-tabs">
  @foreach ($tabs as $key => $t)
    <a href="{{ $t[2] }}" class="{{ $key === $active ? 'on' : '' }}">{{ $t[0] }} {{ $t[1] }}</a>
  @endforeach
</div>
