{{-- Shared form: record a payment or a refund for one order. Expects $order, $due, $net, $locked, $return --}}
<form class="ad-card ad-form" method="POST" action="{{ route('admin.payments.store') }}">
  @csrf
  <input type="hidden" name="order_id" value="{{ $order->id }}">
  <input type="hidden" name="return" value="{{ $return }}">

  <div class="ad-row">
    <label>Type
      <select name="type" id="payType_{{ $order->id }}">
        @if (!$locked && $due > 0)
          <option value="payment" {{ old('type') === 'payment' ? 'selected' : '' }}>Payment received</option>
        @endif
        @if ($net > 0)
          <option value="refund" {{ old('type', $locked ? 'refund' : '') === 'refund' ? 'selected' : '' }}>Refund given back</option>
        @endif
      </select>
    </label>
    <label>Method
      <select name="method">
        @foreach (\App\Models\Payment::methods() as $k => $label)
          <option value="{{ $k }}" {{ old('method', 'bkash') === $k ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <label>Amount (৳)
      <input type="number" name="amount" min="1" value="{{ old('amount', $locked ? $net : $due) }}" required>
    </label>
  </div>

  <div class="ad-row">
    <label>Transaction ID <small>(required for bKash / Nagad / Rocket payments)</small>
      <input type="text" name="trx_id" value="{{ old('trx_id') }}" maxlength="100" autocomplete="off">
    </label>
    <label>Sender number <small>(optional)</small>
      <input type="text" name="sender_number" value="{{ old('sender_number') }}" maxlength="20">
    </label>
    <label>Date &amp; time <small>(blank = now)</small>
      <input type="datetime-local" name="paid_at" value="{{ old('paid_at') }}">
    </label>
  </div>

  <label>Note <small>(optional)</small>
    <input type="text" name="note" value="{{ old('note') }}" maxlength="255">
  </label>

  <div class="ad-submit">
    <button type="submit" class="ad-btn">Save</button>
  </div>
</form>
