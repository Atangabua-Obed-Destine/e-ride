{{-- A bill's state in words as well as colour (paxhitestbed supplier-bill/partials/chip). --}}
<span class="ap-chip {{ $state }}">{{ __('ledger_accounts.state_'.$state) }}</span>
@if(!empty($overdue))
    <span class="ap-chip overdue">@lang('ledger_accounts.overdue')</span>
@endif
