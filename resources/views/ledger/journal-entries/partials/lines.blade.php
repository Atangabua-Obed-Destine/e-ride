{{--
    The journal entry lines (paxhitestbed create / edit). Rows are drawn by
    partials/lines_script from the accounts and the starting lines.
    $posted: the entry is posted (edit page): read-only rows, no buttons.
--}}
@php($cur = session('currency')['symbol'] ?? '')
<div class="row" style="margin-top: 15px;">
    <div class="col-md-12">
        <h4>{{ __('ledger_je.journal_entry_lines') }}</h4>
        <div class="table-responsive">
            <table class="table table-bordered je-lines" id="entry-lines-table">
                <thead>
                    <tr>
                        <th width="5%">{{ __('ledger_je.line') }}</th>
                        <th width="30%">{{ __('ledger_je.account') }} <span class="text-danger">*</span></th>
                        <th width="30%">{{ __('ledger_je.description') }}</th>
                        <th width="12%">{{ __('ledger_je.debit') }}</th>
                        <th width="12%">{{ __('ledger_je.credit') }}</th>
                        @if(empty($posted))<th width="8%">{{ __('ledger_je.action') }}</th>@endif
                    </tr>
                </thead>
                <tbody id="entry-lines-body">
                    @if(!empty($posted))
                    @foreach($entry->lines as $i => $line)
                    <tr class="entry-line">
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td><input type="text" class="form-control input-sm" readonly
                                   value="{{ $line->account ? $line->account->account_code.' - '.\App\Ledger\Services\Preview::accountName($line->account) : '#'.$line->account_id }}"></td>
                        <td><input type="text" class="form-control input-sm" readonly value="{{ $line->description }}"></td>
                        <td><input type="text" class="form-control input-sm text-right" readonly value="{{ $line->debit > 0 ? \App\Ledger\Ledger::money($line->debit) : 0 }}"></td>
                        <td><input type="text" class="form-control input-sm text-right" readonly value="{{ $line->credit > 0 ? \App\Ledger\Ledger::money($line->credit) : 0 }}"></td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right"><strong>{{ __('ledger_je.total') }}:</strong></td>
                        <td class="text-right"><strong id="total-debit">{{ \App\Ledger\Ledger::money($entry->total_debit ?? 0) }}</strong> {{ $cur }}</td>
                        <td class="text-right"><strong id="total-credit">{{ \App\Ledger\Ledger::money($entry->total_credit ?? 0) }}</strong> {{ $cur }}</td>
                        @if(empty($posted))<td></td>@endif
                    </tr>
                    @if(empty($posted))
                    <tr id="balance-row" style="display: none;">
                        <td colspan="6" class="text-center">
                            <span class="label label-danger" style="font-size: 90%;">
                                <i class="fa fa-exclamation-triangle"></i>
                                {{ __('ledger_je.entry_not_balanced') }}: {{ __('ledger_je.difference') }} = <span id="difference">0</span> {{ $cur }}
                            </span>
                        </td>
                    </tr>
                    @endif
                </tfoot>
            </table>
        </div>
        @if(empty($posted))
        <button type="button" class="btn btn-success btn-sm" id="add-line-btn"><i class="fa fa-plus"></i> {{ __('ledger_je.add_line') }}</button>
        @endif
    </div>
</div>
