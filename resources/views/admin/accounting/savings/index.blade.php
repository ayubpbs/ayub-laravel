@extends('admin.layout')

@section('title', 'Savings & Loans')

@section('content')
  <h1 class="ad-title">🏦 Savings, Loans &amp; Receivables</h1>

  <div class="ad-stats">
    <div class="ad-stat ad-stat-hot"><span class="n ad-green">৳ {{ number_format($summary['assets'], 2) }}</span><span class="l">Total to get / invested (assets)</span></div>
    <div class="ad-stat"><span class="n {{ $summary['liabilities'] > 0 ? 'ad-red' : '' }}">৳ {{ number_format($summary['liabilities'], 2) }}</span><span class="l">Total to pay back (borrowed)</span></div>
    <div class="ad-stat"><span class="n {{ $summary['net'] < 0 ? 'ad-red' : '' }}">{{ $summary['net'] < 0 ? '−' : '' }}৳ {{ number_format(abs($summary['net']), 2) }}</span><span class="l">Net (assets − borrowed)</span></div>
  </div>

  <div class="ad-tabs">
    <a href="{{ route('admin.accounting.savings.index', ['status' => $status]) }}" class="{{ $kind === '' ? 'on' : '' }}">All</a>
    @foreach ($kinds as $key => $k)
      <a href="{{ route('admin.accounting.savings.index', ['kind' => $key, 'status' => $status]) }}" class="{{ $kind === $key ? 'on' : '' }}">
        {{ $k['icon'] }} {{ $k['label'] }}@if (($counts[$key] ?? 0) > 0) ({{ $counts[$key] }})@endif
      </a>
    @endforeach
  </div>

  <div class="ad-tabs">
    @foreach (['open' => 'Running', 'closed' => 'Finished', 'all' => 'Everything'] as $key => $label)
      <a href="{{ route('admin.accounting.savings.index', array_filter(['kind' => $kind, 'status' => $key])) }}" class="{{ $status === $key ? 'on' : '' }}">{{ $label }}</a>
    @endforeach
  </div>

  <p style="margin:0 0 12px"><a class="ad-btn" href="{{ route('admin.accounting.savings.create', array_filter(['kind' => $kind])) }}">+ Add new</a></p>

  <section class="ad-card">
    @if ($items->isEmpty())
      <p class="ad-muted">Nothing here yet. Use “Add new” to enter a savings certificate, FDR, loan or receivable.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr>
            <th>Name</th><th>Type</th><th>Start</th><th>Due / maturity</th>
            <th class="r">Amount</th><th class="r">Received / repaid</th><th class="r">Outstanding</th><th></th>
          </tr>
          @foreach ($items as $it)
            @php
              $k   = $kinds[$it->kind] ?? $kinds['receivable'];
              $set = (float) ($it->settled_sum ?? 0);
              $out = max(0, (float) $it->principal - $set);
              $late = $out > 0 && $it->maturity_date && $it->maturity_date->isPast() && !$it->maturity_date->isToday();
              $soon = $out > 0 && $it->maturity_date && !$late && $it->maturity_date->diffInDays(now()->startOfDay(), false) >= -30;
            @endphp
            <tr>
              <td>
                <a href="{{ route('admin.accounting.savings.show', $it->id) }}"><strong>{{ $it->title }}</strong></a>
                @if ($it->party)<div class="ad-muted">{{ $it->party }}</div>@endif
              </td>
              <td>{{ $k['icon'] }} {{ $k['label'] }}</td>
              <td>{{ $it->start_date->format('d M Y') }}</td>
              <td>
                @if ($it->maturity_date)
                  {{ $it->maturity_date->format('d M Y') }}
                  @if ($late) <span class="ad-red">· overdue</span>@elseif ($soon) <span class="ad-muted">· within 30 days</span>@endif
                @else — @endif
              </td>
              <td class="r">৳ {{ number_format((float) $it->principal, 2) }}</td>
              <td class="r">{{ $set > 0 ? '৳ ' . number_format($set, 2) : '—' }}</td>
              <td class="r">
                @if ($out > 0)
                  <strong class="{{ $k['side'] === 'liability' ? 'ad-red' : '' }}">৳ {{ number_format($out, 2) }}</strong>
                @else
                  <span class="ad-muted">Finished</span>
                @endif
              </td>
              <td class="ad-actions">
                <a class="ad-link" href="{{ route('admin.accounting.savings.show', $it->id) }}">Open</a>
                <a class="ad-link" href="{{ route('admin.accounting.savings.edit', $it->id) }}">Edit</a>
              </td>
            </tr>
          @endforeach
        </table>
      </div>
      <div class="ad-pager">{{ $items->links() }}</div>
    @endif
    <p class="ad-hint">Amounts are counted at the money still outstanding. Savings certificates and FDRs are counted at the amount invested (interest is not added until you receive it).</p>
  </section>
@endsection
