@extends('ledger.general-ledger.pdf-layout')
@section('title', $title)
@section('report_title', $title)
@section('content')
<table>
    <thead>
        <tr>
            @foreach($columns as $i => $c)
                <th class="{{ in_array($i, $numeric) ? 'text-right' : '' }}">{{ $c }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
            <tr>
                @foreach($r as $i => $cell)
                    <td class="{{ in_array($i, $numeric) ? 'text-right' : '' }}">{{ $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) }}" class="text-center">@lang('ledger_budget.no_data_available')</td></tr>
        @endforelse
        @if($totals)
            <tr class="totals-row">
                @foreach($totals as $i => $cell)
                    <td class="{{ in_array($i, $numeric) ? 'text-right' : '' }}">{{ $cell }}</td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>
@endsection
