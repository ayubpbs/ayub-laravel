{{-- Warranty claim: policy, dates and the accept / reject decision (second column of the claim page) --}}
@php
  $canApprove = $ax::can('aftersales_approve');
  $stateChip  = ['in_warranty' => 'st-delivered', 'expired' => 'st-cancelled', 'not_covered' => 'st-cancelled'];
  $stateText  = ['in_warranty' => 'In warranty', 'expired' => 'Expired', 'not_covered' => 'No warranty'];
  $srcText    = ['product' => "this product's own policy", 'shop' => 'shop default', 'backfilled' => "today's product settings (old order: please check)", 'continued' => 'continues the original warranty'];
@endphp
<section class="ad-card">
  <h2>🛡 Warranty <span class="ad-st {{ $stateChip[$claim->warranty_state] ?? 'st-pending' }}">{{ $stateText[$claim->warranty_state] ?? ucfirst((string) $claim->warranty_state) }}</span></h2>
  <dl class="ad-dl">
    <dt>Policy</dt>
    <dd>{{ \App\Support\WarrantyPolicy::typeLabel($claim->warranty_type) }}@if ($claim->warranty_days) · {{ $claim->warranty_days }} days @endif
      <br><small class="ad-muted">{{ $srcText[$claim->warranty_source] ?? $claim->warranty_source }}</small></dd>
    <dt>Counted from</dt>
    <dd>{{ $claim->warranty_start_at ? $claim->warranty_start_at->format('d M Y') : '—' }}
      <small class="ad-muted">({{ $claim->warranty_start_source === 'admin' ? 'date entered by ' . ($claim->opened_by_name ?: 'staff') : 'delivery date of the order' }})</small></dd>
    <dt>Valid until</dt><dd>{{ $claim->warranty_expires_at ? $claim->warranty_expires_at->format('d M Y') : '—' }}</dd>
    @if ($claim->warranty_terms)
      <dt>Terms</dt><dd>{!! nl2br(e($claim->warranty_terms)) !!}</dd>
    @endif
    @if ($claim->symptoms)
      <dt>Symptoms</dt><dd>{!! nl2br(e($claim->symptoms)) !!}</dd>
    @endif
    <dt>Serial number</dt><dd>{{ $claim->serial_number ?: '—' }}</dd>
  </dl>

  @if ($claim->status === 'open')
    <h3 style="margin:14px 0 6px">Decision</h3>
    @if (!$canApprove)
      <p class="ad-muted">Accepting or rejecting a warranty claim needs the "Warranty: accept / reject / choose solution" permission.</p>
    @else
      <form action="{{ route('admin.claims.warranty.accept', $claim->id) }}" method="POST" class="ad-form">
        @csrf
        <label>Note <small>(needed for Reject)</small>
          <textarea name="note" rows="2" maxlength="1000">{{ old('note') }}</textarea>
        </label>
        <div class="ad-submit">
          <button type="submit" class="ad-btn">✔ Accept for inspection</button>
          <button type="submit" class="ad-btn ad-btn-gray" formaction="{{ route('admin.claims.warranty.reject', $claim->id) }}" onclick="return confirm('Reject this warranty claim?');">✖ Reject</button>
        </div>
        <p class="ad-hint">Accepting only lets the product come in for inspection. Repair or replacement is decided after the inspection.</p>
      </form>
    @endif
  @endif
</section>
