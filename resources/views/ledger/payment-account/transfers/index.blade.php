@extends('layouts.app')
@section('title', __('ledger_accounts.fund_transfers'))
@section('css')
@include('ledger.partials.style')
@endsection

{{-- Fund transfers between payment accounts (paxhitestbed admin/payment-account/transfers/index). --}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0" style="font-size:1.1rem">@lang('ledger_accounts.fund_transfers')</h3>
            @can('payment-account-transfer-create')
            <a href="{{ route('ledger.payment-account-transfer.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> @lang('ledger_accounts.new_transfer')</a>
            @endcan
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover ap-table">
                    <thead>
                        <tr>
                            <th width="5%">@lang('ledger_accounts.sl')</th>
                            <th>@lang('ledger_accounts.date')</th>
                            <th>@lang('ledger_accounts.from_account')</th>
                            <th>@lang('ledger_accounts.to_account')</th>
                            <th class="ap-num">@lang('ledger_accounts.amount')</th>
                            <th>@lang('ledger_accounts.field_note')</th>
                            <th>@lang('ledger_accounts.created_by')</th>
                            <th>@lang('ledger_accounts.ledger')</th>
                            <th width="12%">@lang('ledger_accounts.field_action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfers as $key => $t)
                        @php $st = $states[(string) $t->id] ?? null; @endphp
                        <tr>
                            <td>{{ $transfers->firstItem() + $key }}</td>
                            <td>{{ $t->transfer_date->format('d/m/Y') }}</td>
                            <td><span class="badge bg-danger">{{ $t->fromAccount->title ?? '-' }}</span></td>
                            <td><span class="badge bg-success">{{ $t->toAccount->title ?? '-' }}</span></td>
                            <td class="ap-num"><strong>{{ $money($t->amount) }}</strong></td>
                            <td>{{ \Illuminate\Support\Str::limit($t->note, 50) }}</td>
                            <td>{{ \App\Ledger\Services\PaymentBook::userName($t->creator) }}</td>
                            <td>
                                @if($st && $st->status === 'posted')<span class="ap-chip posted">@lang('ledger_accounts.posted')</span>
                                @elseif($st && $st->status === 'failed')<span class="ap-chip failed" title="{{ $st->error }}">@lang('ledger_accounts.not_posted')</span>
                                @else<span class="ap-muted">@lang('ledger_accounts.nothing_to_post')</span>@endif
                            </td>
                            <td class="text-nowrap">
                                @if($t->attach)<a href="{{ \App\Http\Controllers\Ledger\IncomeExpenseController::fileUrl($t->attach) }}" target="_blank" class="btn btn-sm btn-info" title="@lang('ledger_accounts.view_attachment')"><i class="fas fa-paperclip"></i></a>@endif
                                @can('payment-account-transfer-edit')
                                <a href="{{ route('ledger.payment-account-transfer.edit', $t->id) }}" class="btn btn-sm btn-primary" title="@lang('ledger_accounts.btn_edit')"><i class="fas fa-edit"></i></a>
                                @endcan
                                @can('payment-account-transfer-delete')
                                <form action="{{ route('ledger.payment-account-transfer.destroy', $t->id) }}" method="POST" class="d-inline"
                                      data-review="@lang('ledger_accounts.check_delete_transfer')" data-review-label="@lang('ledger_accounts.delete_and_reverse')" data-review-class="btn-danger">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="@lang('ledger_accounts.btn_delete')"><i class="fas fa-trash"></i></button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center">@lang('ledger_accounts.no_transfers_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">{{ $transfers->links() }}</div>
    </div>
</section>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
