@extends('layouts.app')
@section('title', __('ledger_map.title_cash'))

@php
    // One picker, as paxhitestbed's: class-5 ledger accounts, code and name.
    $select = function (string $name, $value, string $id, string $blank) use ($ledgerAccounts) {
        $html = '<select class="form-control select2" style="width: 100%;" name="'.e($name).'" id="'.e($id).'">';
        $html .= '<option value="">'.e($blank).'</option>';
        foreach ($ledgerAccounts as $a) {
            $html .= '<option value="'.$a->id.'"'.((int) $value === (int) $a->id ? ' selected' : '').'>'
                .e($a->account_code.' - '.\App\Ledger\Services\Preview::accountName($a)).'</option>';
        }

        return $html.'</select>';
    };
@endphp

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_map.title_cash')</h1>
</section>
<section class="content">
    <form action="{{ route('ledger.cash-accounts.update') }}" method="post" id="cash_accounts_form">
        @csrf
        <div class="ap-card">
            <p class="ap-muted" style="font-size: 0.9rem;">@lang('ledger_map.cash_intro')</p>

            @if($errors->any())
            <div class="alert alert-danger">@foreach(array_unique($errors->all()) as $message)<div>{{ $message }}</div>@endforeach</div>
            @endif

            <h4 style="margin-top: 18px;">@lang('ledger_map.cash_methods_title')</h4>
            <div class="row">
                @foreach($methods as $key => $m)
                <div class="form-group col-md-4 col-lg-3">
                    <label for="method_{{ $key }}">{{ $m['label'] }}</label>
                    {!! $select('methods['.$key.']', old('methods.'.$key, $m['account']), 'method_'.$key, __('ledger_map.choose')) !!}
                </div>
                @endforeach
            </div>

            <h4 style="margin-top: 18px;">@lang('ledger_map.payment_accounts')</h4>
            @if($paymentAccounts->isEmpty())
            <p class="ap-muted">@lang('ledger_map.no_payment_accounts')</p>
            @else
            <div class="table-responsive">
                <table class="table table-condensed ap-table">
                    <thead><tr><th>@lang('ledger_map.payment_account')</th><th>@lang('ledger_map.ledger_account')</th></tr></thead>
                    <tbody>
                        @foreach($paymentAccounts as $pa)
                        <tr>
                            <td>{{ $pa->name }}@if($pa->account_number)<br><small class="ap-muted">{{ $pa->account_number }}</small>@endif</td>
                            <td style="min-width: 260px;">{!! $select('links['.$pa->id.']', old('links.'.$pa->id, $pa->chart_of_account_id), 'link_'.$pa->id, __('ledger_map.use_method_account')) !!}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <div class="text-right" style="margin-top: 10px;">
                <a href="{{ route('ledger.mappings.settings') }}" class="btn btn-default"><i class="fa fa-cog"></i> @lang('ledger_map.mapping_settings')</a>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('ledger_map.save')</button>
            </div>
        </div>

        @if($comparison->isNotEmpty())
        <div class="ap-card">
            <h4 style="margin-top: 0;">@lang('ledger_map.books_beside')</h4>
            <p class="ap-muted">@lang('ledger_map.books_beside_help')</p>
            <div class="table-responsive">
                <table class="table table-condensed ap-table">
                    <thead>
                        <tr>
                            <th>@lang('ledger_map.ledger_account')</th>
                            <th>@lang('ledger_map.payment_accounts')</th>
                            <th class="ap-num">@lang('ledger_map.col_books')</th>
                            <th class="ap-num">@lang('ledger_map.col_ledger')</th>
                            <th class="ap-num">@lang('ledger_map.col_difference')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($comparison as $row)
                        <tr>
                            <td>{{ $row->account }}</td>
                            <td>{{ $row->books }}</td>
                            <td class="ap-num">{{ \App\Ledger\Ledger::money($row->book_balance) }}</td>
                            <td class="ap-num">{{ \App\Ledger\Ledger::money($row->ledger_balance) }}</td>
                            <td class="ap-num {{ abs($row->difference) >= 1 ? 'text-danger' : 'text-success' }}">{{ \App\Ledger\Ledger::money($row->difference) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </form>
</section>
@endsection
