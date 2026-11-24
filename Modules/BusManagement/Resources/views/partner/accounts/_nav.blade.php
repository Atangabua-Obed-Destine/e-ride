@php
    // The same screens serve a branch's books (branch portal) and the head office's own books.
    $accTabs = array_values(array_filter([
        $isCompanyBooks ? ['bus-company.accounts', translate('Branches side by side')] : null,
        [$acc, $isCompanyBooks ? translate('Head office books') : translate('Overview')],
        [$acc . '.spending', translate('Spending')],
        [$acc . '.money', translate('Money')],
        $isCompanyBooks ? null : [$acc . '.cash', translate('Agent cash')],
        $carriesBuses ? [$acc . '.buses', translate('Buses')] : null,
        [$acc . '.reports', translate('Reports')],
        [$acc . '.journal', translate('Journal')],
        [$acc . '.settings', translate('Settings')],
    ]));
@endphp
<div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h1 class="fs-5 fw-bold m-0">{{ translate('Accounts') }}</h1>
    @isset($books)<span class="b-hint">{{ translate(key: 'Books of {branch} since {date}', replace: ['branch' => $accOwnerName, 'date' => $books->starts_on->format('d/m/Y')]) }}</span>@endisset
</div>
<nav class="acc-sub" aria-label="{{ translate('Accounts') }}">
    @foreach($accTabs as [$name, $label])
        <a href="{{ route($name) }}" class="{{ request()->routeIs($name) || ($name === $acc . '.journal' && request()->routeIs($acc . '.document')) || ($name === $acc . '.money' && request()->routeIs($acc . '.reconcile')) ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</nav>
