{{-- The header of a budget report (paxhitestbed: title and a breadcrumb back to the budgets). Expects $title. --}}
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $title }}</h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('ledger.budget.dashboard') }}"><i class="fa fa-dashboard"></i> @lang('ledger_budget.budget_dashboard')</a></li>
        <li><a href="{{ route('ledger.budget.index') }}">@lang('ledger_budget.text_budgets')</a></li>
        <li class="active">{{ $title }}</li>
    </ol>
</section>
