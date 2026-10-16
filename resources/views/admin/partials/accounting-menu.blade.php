@php
    $on = fn ($p) => request()->routeIs($p);
    $acct = fn ($t) => $on('admin.accounting.accounts.show') && request()->route('type') === $t;

    $acc = [
        ['label' => 'Dashboard', 'url' => route('admin.accounting.dashboard'), 'on' => $on('admin.accounting.dashboard')],
        ['label' => 'Income', 'on' => $on('admin.accounting.income.*'), 'children' => [
            ['label' => 'All Income', 'url' => route('admin.accounting.income.index'),  'on' => $on('admin.accounting.income.index')],
            ['label' => 'Add Income', 'url' => route('admin.accounting.income.create'), 'on' => $on('admin.accounting.income.create')],
        ]],
        ['label' => 'Expense', 'on' => $on('admin.accounting.expense.*'), 'children' => [
            ['label' => 'All Expenses', 'url' => route('admin.accounting.expense.index'),  'on' => $on('admin.accounting.expense.index')],
            ['label' => 'Add Expense',  'url' => route('admin.accounting.expense.create'), 'on' => $on('admin.accounting.expense.create')],
        ]],
        ['label' => 'Customers', 'on' => $on('admin.accounting.customers.*'), 'children' => [
            ['label' => 'Customer Due',    'url' => route('admin.accounting.customers.due'),     'on' => $on('admin.accounting.customers.due')],
            ['label' => 'Payment Receive', 'url' => route('admin.accounting.customers.receive'), 'on' => $on('admin.accounting.customers.receive')],
        ]],
        ['label' => 'Supplier Payment', 'url' => route('admin.accounting.suppliers.payment'), 'on' => $on('admin.accounting.suppliers.*')],
        ['label' => 'Accounts', 'on' => ($on('admin.accounting.accounts.*') || $on('admin.accounting.banks.*')), 'children' => [
            ['label' => 'Cash',  'url' => route('admin.accounting.accounts.show', 'cash'),  'on' => $acct('cash')],
            ['label' => 'Bank',  'url' => route('admin.accounting.banks.index'),  'on' => $on('admin.accounting.banks.*')],
            ['label' => 'bKash', 'url' => route('admin.accounting.accounts.show', 'bkash'), 'on' => $acct('bkash')],
            ['label' => 'Nagad', 'url' => route('admin.accounting.accounts.show', 'nagad'), 'on' => $acct('nagad')],
        ]],
        ['label' => 'Savings & Loans', 'on' => $on('admin.accounting.savings.*'), 'children' => [
            ['label' => 'Overview',  'url' => route('admin.accounting.savings.index'),  'on' => $on('admin.accounting.savings.index') || $on('admin.accounting.savings.show') || $on('admin.accounting.savings.edit')],
            ['label' => 'Add New',   'url' => route('admin.accounting.savings.create'), 'on' => $on('admin.accounting.savings.create')],
        ]],
        ['label' => 'Money Transfer', 'url' => route('admin.accounting.transfers.index'), 'on' => $on('admin.accounting.transfers.*')],
        ['label' => 'Transactions / Ledger', 'url' => route('admin.accounting.ledger.index'), 'on' => $on('admin.accounting.ledger.*')],
        ['label' => 'Reports', 'on' => $on('admin.accounting.reports.*'), 'children' => [
            ['label' => 'Profit & Loss',   'url' => route('admin.accounting.reports.profit_loss'),   'on' => $on('admin.accounting.reports.profit_loss')],
            ['label' => 'Income Report',   'url' => route('admin.accounting.reports.income'),        'on' => $on('admin.accounting.reports.income')],
            ['label' => 'Expense Report',  'url' => route('admin.accounting.reports.expense'),       'on' => $on('admin.accounting.reports.expense')],
            ['label' => 'Cash Flow',       'url' => route('admin.accounting.reports.cash_flow'),     'on' => $on('admin.accounting.reports.cash_flow')],
            ['label' => 'Customer Due',    'url' => route('admin.accounting.reports.customer_due'),  'on' => $on('admin.accounting.reports.customer_due')],
        ]],
        ['label' => 'Categories', 'on' => $on('admin.accounting.categories.*'), 'children' => [
            ['label' => 'Income Categories',  'url' => route('admin.accounting.categories.income'),  'on' => $on('admin.accounting.categories.income')],
            ['label' => 'Expense Categories', 'url' => route('admin.accounting.categories.expense'), 'on' => $on('admin.accounting.categories.expense')],
        ]],
        ['label' => 'Accounting Settings', 'url' => route('admin.accounting.settings.edit'), 'on' => $on('admin.accounting.settings.*')],
    ];
    // Hand the Accounting menu to the topbar sub-navigation: admin/partials/subnav.blade.php
    \Illuminate\Support\Facades\View::share('adminNavAccounting', $acc);
@endphp

<details class="sb-group" {{ $on('admin.accounting.*') ? 'open' : '' }}>
  <summary class="sb-link {{ $on('admin.accounting.*') ? 'on' : '' }}">
    <span class="sb-ico">💰</span>
    <span class="sb-text">Accounting</span>
    <span class="sb-caret">›</span>
  </summary>
  <div class="sb-sub">
    @foreach ($acc as $s)
      @if (!empty($s['children']))
        <details class="sb-nest" {{ !empty($s['on']) ? 'open' : '' }}>
          <summary class="sb-sublink {{ !empty($s['on']) ? 'on' : '' }}">
            <span class="sb-text">{{ $s['label'] }}</span>
            <span class="sb-caret">›</span>
          </summary>
          <div class="sb-nest-items">
            @foreach ($s['children'] as $c)
              <a class="sb-sublink {{ !empty($c['on']) ? 'on' : '' }}" href="{{ $c['url'] }}"><span class="sb-text">{{ $c['label'] }}</span></a>
            @endforeach
          </div>
        </details>
      @else
        <a class="sb-sublink {{ !empty($s['on']) ? 'on' : '' }}" href="{{ $s['url'] }}"><span class="sb-text">{{ $s['label'] }}</span></a>
      @endif
    @endforeach
  </div>
</details>

<style>
  .sb-nest > summary { list-style: none; cursor: pointer; display: flex; align-items: center; justify-content: space-between; }
  .sb-nest > summary::-webkit-details-marker { display: none; }
  .sb-nest > summary .sb-caret { transition: transform .15s; }
  .sb-nest[open] > summary .sb-caret { transform: rotate(90deg); }
  .sb-nest-items { padding-left: 14px; }
</style>
