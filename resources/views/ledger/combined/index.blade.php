@extends('layouts.app')
@section('title', __('ledger.combined_title'))

{{-- Combined statements of a bus company (E-Ride): head office and branches side by side, and their sum. --}}
@section('content')
@include('ledger.partials.style')
@php
    $money = fn ($n) => abs($n) < 0.005 ? '-' : \App\Ledger\Ledger::money($n);
    $classes = [1 => __('ledger.combined_class_1'), 2 => __('ledger.combined_class_2'), 3 => __('ledger.combined_class_3'),
        4 => __('ledger.combined_class_4'), 5 => __('ledger.combined_class_5'), 6 => __('ledger.combined_class_6'),
        7 => __('ledger.combined_class_7'), 8 => __('ledger.combined_class_8')];
    $byClass = collect($table)->groupBy('class');
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger.combined_title')
        <small>{{ $company->name }}</small></h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-body">
            <form method="GET" action="{{ route('ledger.combined.index') }}" class="ap-filters">
                <div class="form-group">
                    <label for="start_date">@lang('ledger.combined_from')</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $from }}">
                </div>
                <div class="form-group">
                    <label for="end_date">@lang('ledger.combined_to')</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $to }}">
                </div>
                <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> @lang('ledger.combined_filter')</button></div>
                <div class="form-group"><button type="button" class="btn btn-default" onclick="window.print()"><i class="fa fa-print"></i> @lang('ledger.combined_print')</button></div>
            </form>
            <p class="ap-muted" style="margin-top: 10px;">@lang('ledger.combined_intro')</p>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-body">
            <div class="table-responsive">
                <table class="table table-condensed ap-table">
                    <thead><tr>
                        <th>@lang('ledger.combined_code')</th>
                        <th>@lang('ledger.combined_account')</th>
                        @foreach($sets as $s)<th class="ap-num">{{ $s['name'] }}</th>@endforeach
                        <th class="ap-num">@lang('ledger.combined_total')</th>
                    </tr></thead>
                    <tbody>
                    @foreach($classes as $cls => $label)
                        @continue(!isset($byClass[$cls]))
                        <tr class="ap-total-row"><td colspan="{{ count($sets) + 3 }}">{{ $label }}</td></tr>
                        @foreach($byClass[$cls] as $t)
                            <tr @if($t['liaison']) style="background:#fffbeb" @endif>
                                <td>{{ $t['code'] }}</td>
                                <td>{{ $t['name'] }}</td>
                                @foreach($sets as $s)<td class="ap-num">{{ $money($t['by'][$s['id']] ?? 0) }}</td>@endforeach
                                <td class="ap-num"><strong>{{ $money($t['total']) }}</strong>
                                    @if($t['liaison'] && abs($t['total']) >= 1)<div class="ap-muted" style="color:#b45309">@lang('ledger.combined_liaison_left')</div>@endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr class="ap-total-row">
                        <td colspan="2">@lang('ledger.combined_result')</td>
                        @foreach($sets as $s)<td class="ap-num">{{ $money($result[$s['id']] ?? 0) }}</td>@endforeach
                        <td class="ap-num"><strong>{{ $money(array_sum($result)) }}</strong></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <p class="ap-muted">@lang('ledger.combined_sign')</p>
        </div>
    </div>
</section>
@endsection
