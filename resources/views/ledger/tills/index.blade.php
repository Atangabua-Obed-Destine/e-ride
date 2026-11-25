@extends('layouts.app')
@section('title', __('ledger_tills.title'))

{{-- Counter tills (E-Ride, bus books): each counter agent's cash, counted through the review dialog. --}}
@section('content')
@include('ledger.partials.style')
@php($money = fn ($n) => \App\Ledger\Ledger::money($n))
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_tills.title')
        <small>@lang('ledger_tills.subtitle')</small></h1>
</section>
<section class="content">
    @if ($errors->any())
        <div class="ap-note is-bad" style="margin-bottom: 14px;">{{ $errors->first() }}</div>
    @endif

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_tills.in_the_tills')</h3>
        </div>
        <div class="box-body">
            <p class="ap-muted">@lang('ledger_tills.intro')</p>
            <div class="table-responsive">
                <table class="table table-condensed ap-table">
                    <thead><tr>
                        <th>@lang('ledger_tills.agent')</th>
                        <th class="ap-num">@lang('ledger_tills.cash_in_till')</th>
                        <th>@lang('ledger_tills.last_count')</th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    @forelse($holdings as $agent => $held)
                        <tr>
                            <td>{{ $names[$agent] ?? $agent }}</td>
                            <td class="ap-num">{{ $money($held) }} FCFA</td>
                            <td>{{ isset($last[$agent]) ? \Carbon\Carbon::parse($last[$agent])->format('d/m/Y') : __('ledger_tills.never') }}</td>
                            <td class="text-right">
                                @can('till-count-create')
                                <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#countModal{{ $loop->index }}">
                                    <i class="fa fa-coins"></i> @lang('ledger_tills.count')
                                </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="ap-muted">@lang('ledger_tills.no_agents')</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_tills.counts')</h3>
        </div>
        <div class="box-body">
            <div class="table-responsive">
                <table class="table table-condensed ap-table">
                    <thead><tr>
                        <th>@lang('ledger_tills.date')</th>
                        <th>@lang('ledger_tills.agent')</th>
                        <th class="ap-num">@lang('ledger_tills.expected')</th>
                        <th class="ap-num">@lang('ledger_tills.counted')</th>
                        <th class="ap-num">@lang('ledger_tills.difference')</th>
                        <th>@lang('ledger_tills.put_into')</th>
                        <th>@lang('ledger_tills.entry')</th>
                        <th>@lang('ledger_tills.status')</th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    @forelse($counts as $c)
                        <tr>
                            <td>{{ $c->count_date->format('d/m/Y') }}</td>
                            <td>{{ $names[$c->agent_id] ?? $c->agent_id }}</td>
                            <td class="ap-num">{{ $money($c->expected) }}</td>
                            <td class="ap-num">{{ $money($c->counted) }}</td>
                            <td class="ap-num" @if((float) $c->difference < 0) style="color:#b91c1c" @endif>{{ (float) $c->difference > 0 ? '+' : '' }}{{ $money($c->difference) }}</td>
                            <td>{{ $c->toAccount ? $c->toAccount->account_code.' '.$c->toAccount->account_name : '' }}</td>
                            <td>@if($c->journalEntry)<a href="{{ route('ledger.journal-entries.show', $c->journal_entry_id) }}">{{ $c->journalEntry->entry_number }}</a>@endif</td>
                            <td>
                                <span class="ap-chip {{ $c->status === 'void' ? 'void' : 'posted' }}">{{ __('ledger_tills.st_'.$c->status) }}</span>
                                @if($c->note)<div class="ap-muted">{{ $c->note }}</div>@endif
                                @if($c->void_reason)<div class="ap-muted">{{ $c->void_reason }}</div>@endif
                            </td>
                            <td class="text-right">
                                @if($c->status === 'posted')
                                @can('till-count-void')
                                <button type="button" class="btn btn-default btn-xs" data-toggle="modal" data-target="#voidModal{{ $c->id }}"><i class="fa fa-undo"></i> @lang('ledger_tills.void')</button>
                                @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="ap-muted">@lang('ledger_tills.no_counts')</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $counts->links() }}
        </div>
    </div>

    @foreach($holdings as $agent => $held)
    <div class="modal fade" id="countModal{{ $loop->index }}" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form data-review="{{ __('ledger_tills.review_count') }}" data-review-label="{{ __('ledger_tills.count_and_post') }}"
                      action="{{ route('ledger.tills.count') }}" method="POST">
                    @csrf
                    <input type="hidden" name="agent_id" value="{{ $agent }}">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('ledger_tills.cancel') }}"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">@lang('ledger_tills.count_of', ['agent' => $names[$agent] ?? $agent])</h4>
                    </div>
                    <div class="modal-body">
                        <p>@lang('ledger_tills.till_holds', ['amount' => $money($held)])</p>
                        <div class="form-group">
                            <label for="counted{{ $loop->index }}">@lang('ledger_tills.counted_cash')</label>
                            <input type="number" min="0" step="1" class="form-control" id="counted{{ $loop->index }}" name="counted" value="{{ max(0, (float) $held) }}" required>
                        </div>
                        <div class="form-group">
                            <label for="to{{ $loop->index }}">@lang('ledger_tills.put_into')</label>
                            <select class="form-control" id="to{{ $loop->index }}" name="to_account_id" required>
                                @foreach($destinations as $d)
                                    <option value="{{ $d->id }}" @selected($d->id == $safe)>{{ $d->account_code }} - {{ \App\Ledger\Services\Preview::accountName($d) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date{{ $loop->index }}">@lang('ledger_tills.date')</label>
                            <input type="date" class="form-control" id="date{{ $loop->index }}" name="count_date" value="{{ $today }}" max="{{ $today }}" required>
                        </div>
                        <div class="form-group">
                            <label for="note{{ $loop->index }}">@lang('ledger_tills.note')</label>
                            <textarea class="form-control" id="note{{ $loop->index }}" name="note" rows="2" maxlength="500"></textarea>
                            <span class="help-block">@lang('ledger_tills.note_help')</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">@lang('ledger_tills.cancel')</button>
                        <button type="submit" class="btn btn-primary">@lang('ledger_tills.count')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endforeach

    @foreach($counts as $c)
    @if($c->status === 'posted')
    <div class="modal fade" id="voidModal{{ $c->id }}" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form data-review="{{ __('ledger_tills.review_void') }}" data-review-label="{{ __('ledger_tills.void') }}" data-review-class="btn-danger"
                      action="{{ route('ledger.tills.void', $c->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('ledger_tills.cancel') }}"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">@lang('ledger_tills.void_count')</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="reason{{ $c->id }}">@lang('ledger_tills.reason')</label>
                            <textarea class="form-control" id="reason{{ $c->id }}" name="reason" rows="2" maxlength="500" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">@lang('ledger_tills.cancel')</button>
                        <button type="submit" class="btn btn-danger">@lang('ledger_tills.void')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
    @endforeach

    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
