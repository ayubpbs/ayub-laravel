{{-- Warranty claim: progress, serial numbers, inspection and solution (below the two columns) --}}
@php
  $canApprove = $ax::can('aftersales_approve');
  $canInspectW = $ax::can('claims_inspect');
  $isOpenish   = in_array($claim->status, ['open', 'approved'], true);
  $results     = \App\Models\ServiceRequest::inspectionResults();
@endphp
<section class="ad-card">
  <h2>Warranty steps</h2>
  <p style="margin:0 0 10px">
    @foreach ($warranty['steps'] as [$label, $done])
      <span class="ad-st {{ $done ? 'st-delivered' : 'st-pending' }}" style="margin-right:6px">{{ $done ? '✔' : '○' }} {{ $label }}</span>
    @endforeach
    @if ($warranty['rejected'])<span class="ad-st st-cancelled">Rejected</span>@endif
  </p>

  {{-- Serial numbers --}}
  @if ($isOpenish && $canManage)
    <form action="{{ route('admin.claims.warranty.serials', $claim->id) }}" method="POST" class="ad-form">
      @csrf
      <div class="ad-row">
        <label>Serial number of the customer's unit
          <input type="text" name="serial_number" maxlength="100" value="{{ old('serial_number', $claim->serial_number) }}">
        </label>
        @if ($claim->resolution === 'replacement')
          <label>Serial number of the replacement we send
            <input type="text" name="replacement_serial" maxlength="100" value="{{ old('replacement_serial', $claim->replacement_serial) }}">
          </label>
        @endif
      </div>
      <div class="ad-submit"><button type="submit" class="ad-btn ad-btn-gray">Save serial numbers</button></div>
    </form>
  @elseif ($claim->replacement_serial)
    <p><strong>Replacement serial number:</strong> {{ $claim->replacement_serial }}</p>
  @endif
</section>

{{-- Inspection --}}
@if ($claim->status !== 'rejected' || $claim->inspected_at)
  <section class="ad-card">
    <h2>Inspection of the returned product</h2>
    @if ($claim->inspected_at)
      <dl class="ad-dl">
        <dt>Result</dt><dd><strong>{{ $results[$claim->inspection_result] ?? $claim->inspection_result }}</strong></dd>
        <dt>Condition received</dt><dd>{!! nl2br(e($claim->received_condition)) !!}</dd>
        @if ($claim->inspection_note)<dt>Note</dt><dd>{!! nl2br(e($claim->inspection_note)) !!}</dd>@endif
        <dt>Inspected</dt><dd>{{ $claim->inspected_at->format('d M Y, h:i A') }} by {{ $claim->inspected_by_name }}</dd>
      </dl>
    @elseif ($claim->status === 'open')
      <p class="ad-muted">The claim has to be accepted first.</p>
    @elseif ($claim->status === 'approved')
      @if (!$warranty['received'])
        <div class="ad-flash ad-warn">The returned product is not recorded as received yet. Add a shipment <strong>"Coming back from the customer"</strong> with the status <strong>"Received"</strong> in the Shipments box below (for a hand delivery write "Hand delivery" as the courier). Then come back here.</div>
      @endif
      @if (!$canInspectW)
        <p class="ad-muted">Inspection needs the "Claims: inspect returned goods" permission.</p>
      @else
        <form action="{{ route('admin.claims.warranty.inspect', $claim->id) }}" method="POST" class="ad-form">
          @csrf
          <label>Condition of the product you received *
            <textarea name="received_condition" rows="2" maxlength="1000" required placeholder="e.g. Complete with box, no burn marks, switch is dead">{{ old('received_condition') }}</textarea>
          </label>
          <label>Result *
            <select name="inspection_result" required>
              <option value="">Choose…</option>
              @foreach ($results as $k => $label)
                <option value="{{ $k }}" {{ old('inspection_result') === $k ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </label>
          <label>Inspection note <small>(optional)</small>
            <textarea name="inspection_note" rows="2" maxlength="1000">{{ old('inspection_note') }}</textarea>
          </label>
          <div class="ad-submit"><button type="submit" class="ad-btn" onclick="return confirm('Save the inspection? It cannot be changed afterwards.');">Save inspection</button></div>
          <p class="ad-hint">The defective unit is never put back on the shelf and no stock is added for a warranty claim.</p>
        </form>
      @endif
    @endif
  </section>
@endif

{{-- Solution --}}
@if ($claim->status === 'approved' && $claim->inspected_at)
  <section class="ad-card">
    <h2>Solution</h2>
    @if ($claim->resolution)
      <p><strong>{{ $claim->resolutionLabel() }}</strong>
        @if ($claim->resolution === 'repair') — add the shipment that sends the repaired product back below, then finish the claim.
        @else — make the replacement order below (same product), then finish the claim.@endif
      </p>
    @elseif (!$canApprove)
      <p class="ad-muted">Choosing the solution or rejecting the claim needs the "Warranty: accept / reject / choose solution" permission.</p>
    @else
      @if ($claim->inspection_result === 'defect_confirmed')
        @if (empty($warranty['allowed']))
          <div class="ad-flash ad-warn">The warranty of this purchase does not allow a free repair or replacement. Reject the claim with the reason.</div>
        @else
          <form action="{{ route('admin.claims.warranty.resolve', $claim->id) }}" method="POST" class="ad-form">
            @csrf
            <label>What will we do? <small>(only what the warranty of this purchase allows)</small>
              <select name="resolution" required>
                @foreach ($warranty['allowed'] as $k)
                  <option value="{{ $k }}" {{ old('resolution') === $k ? 'selected' : '' }}>{{ \App\Models\ServiceRequest::warrantyResolutions()[$k] ?? $k }}</option>
                @endforeach
              </select>
            </label>
            <label>Note <small>(optional)</small>
              <input type="text" name="note" maxlength="500" value="{{ old('note') }}">
            </label>
            <div class="ad-submit"><button type="submit" class="ad-btn">Save solution</button></div>
          </form>
        @endif
      @else
        <div class="ad-flash ad-warn">The inspection did not confirm a covered defect ({{ strtolower($results[$claim->inspection_result] ?? '') }}), so no free repair or replacement can be given.</div>
      @endif

      <form action="{{ route('admin.claims.warranty.reject', $claim->id) }}" method="POST" class="ad-form" style="margin-top:12px">
        @csrf
        <label>Reject this claim <small>(reason, needed)</small>
          <input type="text" name="note" maxlength="1000" required>
        </label>
        <div class="ad-submit"><button type="submit" class="ad-btn ad-btn-gray" onclick="return confirm('Reject this warranty claim?');">✖ Reject claim</button></div>
        <p class="ad-hint">After a rejection you can still add the shipment that sends the product back to the customer.</p>
      </form>
    @endif
  </section>
@endif
