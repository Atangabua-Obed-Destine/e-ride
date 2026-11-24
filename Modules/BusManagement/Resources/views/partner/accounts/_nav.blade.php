@php
    $accTabs = [
        ['bus-partner.accounts', translate('Overview')],
        ['bus-partner.accounts.spending', translate('Spending')],
        ['bus-partner.accounts.money', translate('Money')],
        ['bus-partner.accounts.cash', translate('Agent cash')],
        ['bus-partner.accounts.reports', translate('Reports')],
        ['bus-partner.accounts.journal', translate('Journal')],
        ['bus-partner.accounts.settings', translate('Settings')],
    ];
@endphp
<div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h1 class="fs-5 fw-bold m-0">{{ translate('Accounts') }}</h1>
    @isset($books)<span class="b-hint">{{ translate(key: 'Books of {branch} since {date}', replace: ['branch' => $busAgency->displayName(), 'date' => $books->starts_on->format('d/m/Y')]) }}</span>@endisset
</div>
<nav class="acc-sub" aria-label="{{ translate('Accounts') }}">
    @foreach($accTabs as [$name, $label])
        <a href="{{ route($name) }}" class="{{ request()->routeIs($name) || ($name === 'bus-partner.accounts.journal' && request()->routeIs('bus-partner.accounts.document')) ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</nav>
